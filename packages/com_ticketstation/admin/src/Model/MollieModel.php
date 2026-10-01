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
use Ticketstation\Component\Ticketstation\Administrator\Helper\MollieCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Helper\MolliePaymentMethods;

/**
 * Ticketstation Configuration Model
 * @since 0.4.0
 */
class MollieModel extends BaseDatabaseModel
{
    /**
     * @var mixed
     * @since version
     */
    public $data;

    /**
     * @var mixed
     * @since version
     */

    function getData() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
                    ->select('*')
                    ->from($db->quoteName('#__ticketstation_mollie'))
                    ->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $this->data = $db->loadObject();

        return $this->data;
    }

    function store($data)
    {
        $table = $this->getTable();

        // With online payments switched off the Mollie settings are greyed out and not sent:
        // only the switch is stored, so switching back on finds everything as it was.
        if (isset($data['enabled']) && $data['enabled'] == '0') {
            try
            {
                return $table->load(1) && $table->bind(['enabled' => 0]) && $table->store();
            }
            catch (\Exception $e)
            {
                Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

                return false;
            }
        }

        // Keep the existing API keys when the submitted value is empty, so a stray
        // browser autofill/generated password on the field can't wipe out the stored key.
        $existing = $this->getData();

        foreach (['api_key', 'api_key_test'] as $field) {
            if (empty($data[$field]) && isset($existing->$field)) {
                $data[$field] = $existing->$field;
            }
        }

        $app = Factory::getApplication();

        $currency = strtoupper((string) ($data['currency'] ?? ''));

        if (!isset(MollieCurrencies::CURRENCIES[$currency])) {
            $currency = MollieCurrencies::fromConfig($existing->currency ?? '');
        }

        $data['currency'] = $currency;

        // The payment methods arrive as a checkbox list. Methods Mollie doesn't offer in the
        // chosen currency are switched off. A list that would leave some customers without
        // a way to pay keeps the stored methods (and currency) instead.
        $chosen  = MolliePaymentMethods::filter((array) ($data['payment_methods'] ?? []));
        $methods = MollieCurrencies::filterMethods($currency, $chosen);

        if (MolliePaymentMethods::isUsable($methods)) {
            $data['payment_methods'] = implode(',', $methods);

            $dropped = array_diff($chosen, $methods);

            if ($dropped) {
                $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_MOLLIE_METHODS_EURO_ONLY_DROPPED',
                    implode(', ', array_map([MolliePaymentMethods::class, 'label'], $dropped))), 'warning');
            }
        } elseif (MolliePaymentMethods::isUsable($chosen)) {
            unset($data['payment_methods'], $data['currency']);
            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_MOLLIE_CURRENCY_NO_METHODS', $currency), 'warning');
        } else {
            unset($data['payment_methods']);
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_MOLLIE_PAYMENT_METHODS_INVALID'), 'warning');
        }

        // Bind, check and store. Table methods return false or throw on a failure.
        try
        {
            return $table->bind($data) && $table->check() && $table->store();
        }
        catch (\Exception $e)
        {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

            return false;
        }
    }
}