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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * The tickets of an order with the totals under them, as shown on the payment page and next to the
 * details form of the combined checkout page.
 *
 * Rendered with LayoutHelper::render('order_summary', [...], null, ['component' => 'com_ticketstation', 'client' => 0]),
 * so a template can override it in templates/<template>/html/layouts/com_ticketstation/order_summary.php.
 *
 * @var  array  $displayData  'items'  => the order rows (Order::getOrdersInCart())
 *                            'coords' => the seat coordinates of those rows
 *                            'config' => the Configuration
 *                            'totals' => OrderTotals::get() of the order
 */

$items  = $displayData['items'];
$config = $displayData['config'];
$totals = $displayData['totals'];

$price = fn ($amount) => (new TicketstationFunctions)->showprice($config->priceformat, $amount, $config->valuta);

?>
<table class="ts-table ts-summary" id="cart">
    <thead>
        <tr>
            <th scope="col"><?php echo Text::_('COM_TICKETSTATION_EVENT_INFORMATION'); ?></th>
            <th scope="col" class="ts-price"><?php echo Text::_('COM_TICKETSTATION_PRICE'); ?></th>
        </tr>
    </thead>

    <tbody>
        <?php ## One line per ticket type with its quantity, seats by seat number
        foreach (Order::cartLines($items, $displayData['coords'] ?? []) as $line) {
            $row = $line->rows[0]; ?>

            <tr class="ts-summary__item">
                <td>
                    <span class="ts-summary__name">
                        <?php echo htmlspecialchars($row->eventname . ' - ' . $row->ticketname, ENT_QUOTES, 'UTF-8'); ?>

                        <?php if ($line->seated) { ?>
                            <?php echo ' - ' . Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . htmlspecialchars($line->seat, ENT_QUOTES, 'UTF-8'); ?>
                        <?php } ?>
                    </span>

                    <span class="ts-summary__date"><?php echo Date::long($row->startdate, true); ?></span>

                    <?php if (!$line->seated) { ?>
                        <span class="ts-summary__qty"><?php echo $line->quantity; ?> &times; <?php echo $price($row->price); ?></span>
                    <?php } ?>
                </td>
                <td class="ts-price"><?php echo $price($line->total); ?></td>
            </tr>

        <?php } ?>
    </tbody>

    <tfoot>
        <tr class="ts-summary__subtotal">
            <th scope="row"><?php echo Text::_('COM_TICKETSTATION_SUBTOTAL'); ?></th>
            <td class="ts-price"><?php echo $price($totals->tickets); ?></td>
        </tr>

        <?php if ($totals->discount != 0) { ?>
            <tr class="ts-summary__discount">
                <th scope="row"><?php echo Text::_('COM_TICKETSTATION_DISCOUNT'); ?><?php if ($totals->discount_type == 1) { ?> (<?php echo (float) $totals->discount_amount; ?>%)<?php } ?></th>
                <td class="ts-price">- <?php echo $price($totals->discount); ?></td>
            </tr>
        <?php } ?>

        <?php ## No fees row when there is no fee: switched off, or nothing to pay (a free order). ?>
        <?php if ($totals->fees > 0 && $totals->fee_type != OrderTotals::FEE_NONE) { ?>
            <tr class="ts-summary__fees">
                <th scope="row"><?php echo Text::_('COM_TICKETSTATION_FEES'); ?><?php echo OrderTotals::feeLabel($totals, $price); ?></th>
                <td class="ts-price"><?php echo $price($totals->fees); ?></td>
            </tr>
        <?php } ?>

        <tr class="ts-summary__total">
            <th scope="row"><?php echo Text::_('COM_TICKETSTATION_ORDERTOTAL'); ?></th>
            <td class="ts-price"><?php echo $price($totals->total); ?></td>
        </tr>
    </tfoot>
</table>
