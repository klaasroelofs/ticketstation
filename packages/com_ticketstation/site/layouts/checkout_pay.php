<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * The payment block of the combined checkout page: the payment methods, the line about the terms,
 * the notes for an order of nothing or with online payments off, and the button that places the
 * order. It sits inside the details form and is replaced after the cart changes, because what it
 * shows depends on the total.
 *
 * Rendered with LayoutHelper::render('checkout_pay', [...], null, ['component' => 'com_ticketstation', 'client' => 0]),
 * so a template can override it in templates/<template>/html/layouts/com_ticketstation/checkout_pay.php.
 *
 * @var  array  $displayData  'hasItems'   => whether there are tickets to pay (false: waiting list only)
 *                            'totals'     => OrderTotals::get() of the cart
 *                            'config'     => the Configuration
 *                            'paymentsOn' => whether online payments are switched on
 *                            'methods'    => the payment methods to choose from (empty: no choice)
 *                            'selected'   => the id of the method to preselect (optional)
 */

$hasItems   = $displayData['hasItems'];
$config     = $displayData['config'];
$paymentsOn = $displayData['paymentsOn'];
$ordertotal = $displayData['totals']->total;

$price  = fn ($amount) => (new TicketstationFunctions)->showprice($config->priceformat, $amount, $config->valuta);
$render = fn ($layout, $data) => LayoutHelper::render($layout, $data, null, ['component' => 'com_ticketstation', 'client' => 0]);

?>
<div id="ts-checkout-pay" class="ts-checkout__pay">

    <?php if ($hasItems && $ordertotal > 0 && $paymentsOn && $displayData['methods']) { ?>
        <div class="ts-checkout__payment">
            <?php echo $render('payment_methods', ['methods' => $displayData['methods'], 'selected' => $displayData['selected'] ?? '']); ?>
        </div>
    <?php } ?>

    <?php if ($hasItems) {
        echo $render('payment_terms', ['config' => $config]);
    } ?>

    <?php if ($hasItems && $ordertotal == 0) { ?>
        <p class="ts-note"><?php echo Text::sprintf('COM_TICKETSTATION_ZERO_TOTAL', $price(0)); ?></p>
    <?php } ?>

    <?php if ($hasItems && !$paymentsOn && $ordertotal > 0) { ?>
        <div class="ts-alert ts-alert--danger"><?php echo Text::_('COM_TICKETSTATION_ONLINE_PAYMENTS_OFF_ORDER'); ?></div>
    <?php } ?>

    <div class="ts-actions">
        <?php if (!$hasItems) { ?>
            <button type="submit" class="ts-btn ts-btn--primary ts-btn--next" id="ts-checkout-submit"><?php echo Text::_('COM_TICKETSTATION_JOIN_WAITINGLIST'); ?></button>
        <?php } elseif ($ordertotal > 0 && !$paymentsOn) { ?>
            <?php ## Paid tickets while online payments are off: nothing to pay with. ?>
        <?php } elseif ($ordertotal > 0) { ?>
            <button type="submit" class="ts-btn ts-btn--primary ts-btn--next" id="ts-checkout-submit"><?php echo Text::sprintf('COM_TICKETSTATION_PAY_AMOUNT', $price($ordertotal)); ?></button>
        <?php } else { ?>
            <button type="submit" class="ts-btn ts-btn--primary ts-btn--next" id="ts-checkout-submit"><?php echo Text::_('COM_TICKETSTATION_PLACE_ORDER'); ?></button>
        <?php } ?>
    </div>

</div>
