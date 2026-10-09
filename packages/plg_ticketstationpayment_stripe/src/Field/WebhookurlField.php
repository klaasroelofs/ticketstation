<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_stripe
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Stripe\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * Shows the address Stripe has to report to, and the events to send there. Both are entered in the
 * Stripe Dashboard (Developers > Webhooks), which is where the signing secret below comes from.
 */
class WebhookurlField extends FormField
{
    protected $type = 'Webhookurl';

    /** The reports the plugin does something with. */
    private const EVENTS = [
        'checkout.session.completed',
        'checkout.session.async_payment_succeeded',
        'checkout.session.async_payment_failed',
        'checkout.session.expired',
        'charge.refunded',
        'charge.refund.updated',
        'charge.dispute.created',
        'charge.dispute.closed',
    ];

    protected function getInput()
    {
        $url = Uri::root() . 'index.php?option=com_ticketstation&controller=payment&task=webhook&provider=stripe';

        return '<input type="text" class="form-control" id="' . $this->id . '" value="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" readonly onclick="this.select()">'
            . '<div class="form-text">' . Text::_('PLG_TICKETSTATIONPAYMENT_STRIPE_WEBHOOK_EVENTS') . '</div>'
            . '<pre class="mb-0 mt-1"><code>' . implode("\n", self::EVENTS) . '</code></pre>';
    }
}
