<?php
/**
 * @package     Ticketstation
 * @subpackage  pkg_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerScript;

/**
 * Installer script for the Ticketstation package.
 *
 * After a successful install or update it shows the release notes of the installed version as a
 * system message on the page Joomla returns to. build\build.ps1 puts them in the package as release-notes.md,
 * taken from release-notes\<version>.md in the repository; the same file is the text of the
 * GitHub release. A package without that file (e.g. a release candidate) shows nothing extra.
 */
class pkg_ticketstationInstallerScript extends InstallerScript
{
    /**
     * The version of the package that is being replaced, null when there was none.
     *
     * @var string|null
     */
    private $oldVersion = null;

    public function preflight($type, $parent)
    {
        if ($type === 'update') {
            $db = Factory::getContainer()->get('DatabaseDriver');

            try {
                $manifest = $db->setQuery(
                    $db->getQuery(true)
                        ->select($db->quoteName('manifest_cache'))
                        ->from($db->quoteName('#__extensions'))
                        ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
                        ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_ticketstation'))
                )->loadResult();

                $this->oldVersion = (string) (json_decode((string) $manifest)->version ?? '') ?: null;
            } catch (\Throwable $e) {
                $this->oldVersion = null;
            }
        }

        return true;
    }

    public function postflight($type, $parent)
    {
        if ($type !== 'install' && $type !== 'update') {
            return true;
        }

        // The plugins are installed disabled; the Scheduled Tasks (task plugin) and the Apple Wallet
        // web service (system plugin) need them on.
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->update($db->quoteName('#__extensions'))
                ->set($db->quoteName('enabled') . ' = 1')
                ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                ->where($db->quoteName('folder') . ' IN (' . $db->quote('task') . ', ' . $db->quote('system') . ')')
                ->where($db->quoteName('element') . ' = ' . $db->quote('ticketstation'))
        )->execute();

        // The first install of a version with payment plugins, or an update from one before: bring the Mollie settings along.
        if ($type === 'install' || $this->oldVersion === null || version_compare($this->oldVersion, '2.25.0-rc3', '<')) {
            $this->setUpMolliePlugin($db);
        }

        // The old Mollie settings table is not used any more (since 2.25.0-rc3), and Joomla's database
        // check reports its columns against the old update files. Removed once its settings are safe.
        $this->dropOldMollieTable($db);

        $file = $parent->getParent()->getPath('source') . '/release-notes.md';

        if (is_file($file)) {
            // As a system message, not as echoed output: Joomla keeps echoed output for the Update page's
            // message block, but when this was the last pending update that page shows its empty state,
            // which has no such block, so the notes were dropped.
            Factory::getApplication()->enqueueMessage(
                '<div class="ticketstation-release-notes text-start">' . $this->markdownToHtml(file_get_contents($file)) . '</div>',
                'notice'
            );
        }

        return true;
    }

    /**
     * Sets up the Mollie payment plugin the first time, and only then (an admin who switched it
     * off, or changed its settings, keeps that). Sites that had Mollie before payment providers
     * were plugins bring their settings along: the API keys, test mode, methods, description and
     * language go to the plugin, the currency and the choice to take online payments through
     * Mollie (or not at all, when "Online payments" was off) to the Ticketstation settings. The
     * old settings table is removed afterwards (dropOldMollieTable). A new installation gets the plugin's defaults
     * and Mollie as the provider, as the plugin is installed disabled.
     */
    private function setUpMolliePlugin($db)
    {
        try {
            $plugin = $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName(['extension_id', 'params']))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('ticketstationpayment'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('mollie'))
            )->loadObject();

            if (!$plugin) {
                return;
            }

            $stored = json_decode((string) $plugin->params, true);

            // Keys were entered in the plugin already: leave them alone.
            if (!empty($stored['api_key']) || !empty($stored['api_key_test'])) {
                return;
            }

            $params = [
                'api_key'         => '',
                'api_key_test'    => '',
                'test_mode'       => 0,
                'payment_methods' => ['ideal'],
                'description'     => 'Ordernumber:',
                'locale'          => 'en_GB',
                'mark_pending'    => 1,
            ];

            try {
                $old = $db->setQuery(
                    $db->getQuery(true)
                        ->select('*')
                        ->from($db->quoteName('#__ticketstation_mollie'))
                        ->where($db->quoteName('configid') . ' = 1')
                )->loadObject();
            } catch (\Throwable $e) {
                $old = null;
            }

            if ($old) {
                $params = [
                    'api_key'         => (string) ($old->api_key ?? ''),
                    'api_key_test'    => (string) ($old->api_key_test ?? ''),
                    'test_mode'       => (int) ($old->test_mode ?? 0),
                    'payment_methods' => array_values(array_filter(explode(',', (string) ($old->payment_methods ?? 'ideal')))) ?: ['ideal'],
                    'description'     => (string) ($old->description ?? 'Ordernumber:'),
                    'locale'          => (string) (($old->mollie_language ?? '') ?: 'en_GB'),
                    'mark_pending'    => (int) ($old->change_payment_state ?? 1),
                ];

                $db->setQuery(
                    $db->getQuery(true)
                        ->update($db->quoteName('#__ticketstation_config'))
                        ->set($db->quoteName('payment_provider') . ' = ' . $db->quote(($old->enabled ?? 1) == 1 ? 'mollie' : ''))
                        ->set($db->quoteName('payment_currency') . ' = ' . $db->quote(strtoupper((string) (($old->currency ?? '') ?: 'EUR'))))
                        ->where($db->quoteName('configid') . ' = 1')
                )->execute();
            }

            $db->setQuery(
                $db->getQuery(true)
                    ->update($db->quoteName('#__extensions'))
                    ->set($db->quoteName('params') . ' = ' . $db->quote(json_encode($params)))
                    ->set($db->quoteName('enabled') . ' = 1')
                    ->where($db->quoteName('extension_id') . ' = ' . (int) $plugin->extension_id)
            )->execute();

            // The list of enabled plugins may be cached.
            Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)
                ->createCacheController('callback', ['defaultgroup' => 'com_plugins'])
                ->clean();
        } catch (\Throwable $e) {
            Factory::getApplication()->enqueueMessage('The Mollie settings could not be moved to the Mollie plugin: ' . $e->getMessage(), 'warning');
        }
    }

    /**
     * Removes the old Mollie settings table, which payment plugins made redundant. Its API keys
     * are never lost: the table only goes when the Mollie plugin holds keys of its own, or when
     * the table held none.
     */
    private function dropOldMollieTable($db)
    {
        try {
            $table = $db->replacePrefix('#__ticketstation_mollie');

            if (!in_array($table, $db->getTableList(), true)) {
                return;
            }

            $params = json_decode((string) $db->setQuery(
                $db->getQuery(true)
                    ->select($db->quoteName('params'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
                    ->where($db->quoteName('folder') . ' = ' . $db->quote('ticketstationpayment'))
                    ->where($db->quoteName('element') . ' = ' . $db->quote('mollie'))
            )->loadResult(), true);

            $pluginHasKeys = !empty($params['api_key']) || !empty($params['api_key_test']);

            $old = $db->setQuery($db->getQuery(true)->select('*')->from($db->quoteName('#__ticketstation_mollie')))->loadObjectList();

            $oldHasKeys = false;

            foreach ($old as $row) {
                if (trim((string) ($row->api_key ?? '')) !== '' || trim((string) ($row->api_key_test ?? '')) !== '') {
                    $oldHasKeys = true;
                }
            }

            if ($oldHasKeys && !$pluginHasKeys) {
                Factory::getApplication()->enqueueMessage(
                    'The old Mollie settings table was kept: its API keys are not in the Mollie plugin yet. Enter them in the plugin (Payments), then remove the table #__ticketstation_mollie.',
                    'warning'
                );

                return;
            }

            $db->setQuery('DROP TABLE ' . $db->quoteName($table))->execute();
        } catch (Throwable $e) {
            // Only a tidy-up: a table that stays is harmless
        }
    }

    /**
     * Converts the small Markdown subset the release notes use: ## and ### headings, "- " list
     * items, paragraphs, **bold**, *italic*, `code` and [links](https://...).
     */
    private function markdownToHtml($markdown)
    {
        $html  = '';
        $list  = false;
        $para  = [];
        $lines = preg_split('/\R/', trim($markdown));

        $flushPara = function () use (&$html, &$para) {
            if ($para) {
                $html .= '<p>' . $this->inline(implode(' ', $para)) . '</p>';
                $para  = [];
            }
        };

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (preg_match('/^- (.*)$/', $line, $m)) {
                $flushPara();

                if (!$list) {
                    $html .= '<ul>';
                    $list  = true;
                }

                $html .= '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }

            if ($list) {
                $html .= '</ul>';
                $list  = false;
            }

            if (preg_match('/^(#{2,3}) (.*)$/', $line, $m)) {
                $flushPara();
                $tag   = strlen($m[1]) === 2 ? 'h3' : 'h4';
                $html .= "<$tag>" . $this->inline($m[2]) . "</$tag>";
            } elseif ($line === '') {
                $flushPara();
            } else {
                $para[] = $line;
            }
        }

        $flushPara();

        if ($list) {
            $html .= '</ul>';
        }

        return $html;
    }

    private function inline($text)
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);

        return preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2" target="blank" rel="noopener">$1</a>', $text);
    }
}
