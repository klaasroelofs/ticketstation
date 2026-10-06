<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;

/**
 * The Payments screen: the provider that takes online payments, the payment currency and the
 * payment plugins that are installed.
 */
class PaymentsModel extends BaseDatabaseModel
{
    /**
     * The provider and the currency that are set now.
     */
    public function getData(): object
    {
        return (object) [
            'payment_provider' => ProviderRegistry::activeId(),
            'payment_currency' => ProviderRegistry::currency(),
        ];
    }

    /**
     * The installed payment plugins, enabled or not.
     *
     * @return  object[]  extension_id, element, name, version, enabled, available (the plugin offers its provider), warnings.
     */
    public function getPlugins(): array
    {
        // The registry runs queries of its own: ask it first, as the driver keeps one query at a time.
        $providers = ProviderRegistry::all();
        $activeId  = ProviderRegistry::activeId();
        $language  = Factory::getApplication()->getLanguage();
        $plugins   = [];

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select($db->quoteName(['extension_id', 'element', 'enabled', 'manifest_cache']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote(ProviderRegistry::GROUP))
            ->order($db->quoteName('element'));

        $db->setQuery($query);

        $rows = $db->loadObjectList();

        foreach ($rows as $row) {
            $manifest = new Registry($row->manifest_cache);

            // The plugin's own name, from its system language file.
            $language->load('plg_' . ProviderRegistry::GROUP . '_' . $row->element . '.sys', JPATH_PLUGINS . '/' . ProviderRegistry::GROUP . '/' . $row->element)
                || $language->load('plg_' . ProviderRegistry::GROUP . '_' . $row->element . '.sys', JPATH_ADMINISTRATOR);

            $provider = $providers[$row->element] ?? null;

            $plugins[] = (object) [
                'extension_id' => (int) $row->extension_id,
                'element'      => $row->element,
                'name'         => Text::_((string) $manifest->get('name', $row->element)),
                'description'  => Text::_((string) $manifest->get('description', '')),
                'version'      => (string) $manifest->get('version', ''),
                'enabled'      => (int) $row->enabled === 1,
                'available'    => $provider !== null,
                'active'       => $activeId === $row->element,
                'configured'   => $provider !== null && $provider->isConfigured(),
                'warnings'     => $provider !== null && $activeId === $row->element ? $provider->getHealthWarnings() : [],
            ];
        }

        return $plugins;
    }

    /**
     * The number of orders waiting for payment.
     */
    public function getPending(): int
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(DISTINCT ' . $db->quoteName('ordercode') . ')')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('paid') . ' = 3');
        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Stores the provider that takes online payments ('' for none) and the currency.
     *
     * @throws  \RuntimeException  with a message for the admin when the provider isn't available.
     */
    public function store(string $provider, string $currency): void
    {
        $provider = trim($provider);

        $chosen = $provider !== '' ? ProviderRegistry::get($provider) : null;

        if ($provider !== '' && $chosen === null) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_PAYMENTS_PROVIDER_NOT_AVAILABLE'));
        }

        $currency = strtoupper(trim($currency));

        if (!isset(PaymentCurrencies::CURRENCIES[$currency])) {
            $currency = ProviderRegistry::currency();
        }

        // The provider must be able to collect in this currency.
        if ($chosen !== null && !isset(PaymentCurrencies::forProvider($chosen)[$currency])) {
            throw new \RuntimeException(Text::sprintf('COM_TICKETSTATION_PAYMENTS_CURRENCY_NOT_SUPPORTED', $chosen->getTitle(), $currency));
        }

        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_config'))
            ->set($db->quoteName('payment_provider') . ' = ' . $db->quote($provider))
            ->set($db->quoteName('payment_currency') . ' = ' . $db->quote($currency))
            ->where($db->quoteName('configid') . ' = 1');

        $db->setQuery($query)->execute();

        ProviderRegistry::reset();
    }

    /**
     * Switches a payment plugin on or off, as Joomla's own plugin manager does.
     *
     * @throws  \RuntimeException
     */
    public function setPluginEnabled(int $extensionId, int $enabled): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('extension_id') . ' = ' . $extensionId)
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote(ProviderRegistry::GROUP));
        $db->setQuery($query);

        // Only payment plugins can be switched here.
        if ((int) $db->loadResult() !== 1) {
            throw new \RuntimeException(Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_NOT_FOUND'));
        }

        $model = Factory::getApplication()->bootComponent('plugins')->getMVCFactory()
            ->createModel('Plugin', 'Administrator', ['ignore_request' => true]);

        $pks = [$extensionId];

        if (!$model->publish($pks, $enabled)) {
            throw new \RuntimeException((method_exists($model, 'getError') ? (string) $model->getError() : '') ?: Text::_('COM_TICKETSTATION_PAYMENTS_PLUGIN_NOT_SWITCHED'));
        }

        ProviderRegistry::reset();
    }
}
