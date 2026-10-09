<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_mollie
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Mollie\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Payment\ProviderRegistry;
use Ticketstation\Plugin\TicketstationPayment\Mollie\Helper\MollieCurrencies;
use Ticketstation\Plugin\TicketstationPayment\Mollie\Helper\MolliePaymentMethods;

/**
 * The payment methods to offer: one checkbox per method, with a note for methods that are not
 * active in the Mollie account behind the entered API key, and for methods that only work in euros.
 */
class MethodsField extends FormField
{
    protected $type = 'Methods';

    protected function getInput()
    {
        $chosen   = MolliePaymentMethods::fromConfig($this->value);
        $currency = class_exists(ProviderRegistry::class) ? ProviderRegistry::currency() : 'EUR';

        // Ask Mollie with the key the checkout uses (the test key while the shop is in test mode), so a
        // chosen method that is not activated in the Mollie Dashboard can be flagged (Mollie would refuse it).
        $testMode = class_exists(ProviderRegistry::class) && ProviderRegistry::testMode();
        $key      = (string) $this->form->getValue($testMode ? 'api_key_test' : 'api_key', 'params', '');
        $active   = MolliePaymentMethods::activeInMollie(trim($key));

        $html = '<div id="' . $this->id . '">';

        foreach (MolliePaymentMethods::METHODS as $method => $label) {
            $id = $this->id . '_' . $method;

            $html .= '<div class="form-check">'
                . '<input class="form-check-input" type="checkbox" name="' . $this->name . '[]" id="' . $id . '" value="' . $method . '"'
                . (in_array($method, $chosen, true) ? ' checked' : '') . '>'
                . '<label class="form-check-label" for="' . $id . '">' . htmlspecialchars(MolliePaymentMethods::label($method), ENT_QUOTES, 'UTF-8');

            if ($method === 'creditcard') {
                $html .= ' ' . Text::_('PLG_TICKETSTATIONPAYMENT_MOLLIE_METHOD_CREDITCARD_GOOGLEPAY');
            }

            $html .= '</label>';

            if ($active !== null && !in_array($method, $active, true)) {
                $html .= ' <span class="badge ' . (in_array($method, $chosen, true) ? 'bg-danger' : 'bg-secondary') . ' ms-1">'
                    . Text::_('PLG_TICKETSTATIONPAYMENT_MOLLIE_METHOD_NOT_ACTIVE') . '</span>';
            } elseif ($method === 'creditcard' && $active !== null && !in_array('googlepay', $active, true)) {
                $html .= ' <span class="badge bg-secondary ms-1">' . Text::_('PLG_TICKETSTATIONPAYMENT_MOLLIE_GOOGLEPAY_NOT_ACTIVE') . '</span>';
            }

            if (!MollieCurrencies::supportsMethod($currency, $method)) {
                $html .= ' <span class="badge bg-secondary ms-1">' . Text::_('PLG_TICKETSTATIONPAYMENT_MOLLIE_METHOD_EURO_ONLY') . '</span>';
            }

            $html .= '</div>';
        }

        if ($active === null) {
            $html .= '<div class="form-text">' . Text::_('PLG_TICKETSTATIONPAYMENT_MOLLIE_METHODS_UNKNOWN') . '</div>';
        }

        return $html . '</div>';
    }
}
