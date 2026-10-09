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
use Ticketstation\Component\Ticketstation\Administrator\Helper\History;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Invoice;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Transaction;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TestData;

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
            ->where(TestData::condition('test'))
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
     * Deletes everything that was made in test mode: the orders with their tickets, payments,
     * invoices, remarks and refunds (the same clean-up as removing an order in the Box Office), the
     * customers and the waiting-list signups. Live data is not touched, whatever mode the shop is in.
     *
     * @return  object  orders and customers: how many were deleted.
     */
    public function deleteTestData(): object
    {
        $db      = $this->getDatabase();
        $summary = TestData::summary();

        $query = $db->getQuery(true)
            ->select('DISTINCT ' . $db->quoteName('ordercode'))
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('test') . ' = 1');
        $db->setQuery($query);

        $codes = array_map('strval', $db->loadColumn());

        // The orders the ticketcleaner removed leave only their history behind.
        $codes = array_values(array_unique(array_merge($codes, array_map('strval', array_keys(History::getAutoRemovedGhosts(null, 1))))));
        $codes = array_values(array_filter($codes, 'ctype_digit'));

        if ($codes) {
            $boxoffice = $this->getMVCFactory()->createModel('Boxoffice', 'Administrator', ['ignore_request' => true]);
            $boxoffice->removeTickets($codes);
        }

        // What can be left without an order: invoices, payments and attempts of orders that were already gone.
        foreach ([['invoices', 'ordercode'], ['transactions', 'orderid'], ['transactions_temp', 'ordercode']] as [$table, $column]) {
            $query = $db->getQuery(true)
                ->select('DISTINCT ' . $db->quoteName($column))
                ->from($db->quoteName('#__ticketstation_' . $table))
                ->where($db->quoteName('test') . ' = 1');
            $db->setQuery($query);

            foreach ($db->loadColumn() as $code) {
                if ($table === 'invoices') {
                    (new Invoice)->remove($code);
                } else {
                    (new Transaction)->remove($code);
                }
            }
        }

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_waitinglist'))
            ->where($db->quoteName('test') . ' = 1');
        $db->setQuery($query)->execute();

        // A test customer goes when no order refers to them any more.
        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_clients'))
            ->where($db->quoteName('test') . ' = 1')
            ->where('NOT EXISTS (SELECT 1 FROM ' . $db->quoteName('#__ticketstation_orders', 'o') . ' WHERE ' . $db->quoteName('o.userid') . ' = ' . $db->quoteName('#__ticketstation_clients.clientid') . ')');
        $db->setQuery($query)->execute();

        TestData::reset();

        return (object) ['orders' => $summary->orders, 'customers' => $summary->customers];
    }

    /**
     * Switches the whole shop into or out of test mode. The payment provider follows: its plugin
     * gets the state in the CollectProvidersEvent and uses its test or live environment.
     */
    public function setTestMode(bool $on): void
    {
        $db    = $this->getDatabase();
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_config'))
            ->set($db->quoteName('test_mode') . ' = ' . ($on ? 1 : 0))
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
