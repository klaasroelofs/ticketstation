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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticketcleaner;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Administrator\Helper\WaitingList;

/**
 * The tickets of the cart with their totals, with buttons to change the quantity or remove a
 * line. Shown on the cart page and on the combined checkout page; its wrapper (#ts-cart-lines) is
 * replaced after a change, see the scripts of those pages.
 *
 * Rendered with LayoutHelper::render('cart_lines', [...], null, ['component' => 'com_ticketstation', 'client' => 0]),
 * so a template can override it in templates/<template>/html/layouts/com_ticketstation/cart_lines.php.
 *
 * @var  array  $displayData  'items'     => the order rows (Order::getOrdersInCart())
 *                            'waiters'   => the waiting-list rows of the cart
 *                            'coords'    => the seat coordinates of the order rows
 *                            'config'    => the Configuration
 *                            'totals'    => OrderTotals::get() of the cart
 *                            'ordercode' => the cart's ordercode
 *                            'showDate'  => show the date and time under each line (default true; the
 *                                           combined checkout leaves it out to keep the summary short)
 */

$app       = Factory::getApplication();
$itemRows  = $displayData['items'];
$waitRows  = $displayData['waiters'];
$config    = $displayData['config'];
$totals    = $displayData['totals'];
$ordercode = (int) $displayData['ordercode'];
$showDate  = $displayData['showDate'] ?? true;

$fees     = $totals->fees;
$discount = $totals->discount;
$ordertotal = $totals->total;

$itemid  = TicketstationFunctions::getSiteItemid();
$items   = count($itemRows);
$waiters = count($waitRows);

$price = fn ($amount) => (new TicketstationFunctions)->showprice($config->priceformat, $amount, $config->valuta);
$token = Session::getFormToken();
## Task links are not routed: the SEF router would turn a ticketid into a path segment and
## drop it. The tasks find the menu item for their redirect themselves.
$task  = fn ($query) => htmlspecialchars(Uri::root(true) . '/index.php?option=com_ticketstation&controller=order&' . $query . '&' . $token . '=1', ENT_QUOTES, 'UTF-8');

## One cart line per ticket type, with its quantity; a seat is a line of its own, by seat number.
$lines = Order::cartLines($itemRows, $displayData['coords'] ?? []);

## Until when the tickets stay reserved (only while the cart has rows the Ticketcleaner removes)
$reservedUntil = Ticketcleaner::reservedUntil($ordercode);

## The seats of one ticket type share one cart line (their list folds out), so many seats do not
## make the cart endless. The lines are sorted, so those seats sit together. A single seat stays a
## line of its own.
$entries = [];

foreach ($lines as $line) {
    $last = $entries ? $entries[count($entries) - 1] : null;

    if ($line->seated && $last && $last->seated
        && $last->lines[0]->rows[0]->ticketid == $line->rows[0]->ticketid
        && $last->lines[0]->rows[0]->eventid == $line->rows[0]->eventid) {
        $last->lines[] = $line;
    } else {
        $entries[] = (object) ['seated' => (bool) $line->seated, 'lines' => [$line]];
    }
}

if ($reservedUntil) {
    $local = Factory::getDate('@' . $reservedUntil)->setTimezone(new DateTimeZone($app->get('offset') ?: 'UTC'));
    $today = Factory::getDate('now')->setTimezone(new DateTimeZone($app->get('offset') ?: 'UTC'))->format('Y-m-d', true);

    $reservedUntilText = $local->format('Y-m-d', true) === $today
        ? Text::sprintf('COM_TICKETSTATION_TIME_OCLOCK', $local->format('H:i', true))
        : Date::long($local->format('Y-m-d H:i:s', true), true);
}

$trashIcon = '<svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M6.5 1h3a1 1 0 0 1 1 1v1H14a.5.5 0 0 1 0 1h-.54l-.8 9.6A1.5 1.5 0 0 1 11.17 15H4.83a1.5 1.5 0 0 1-1.5-1.4L2.54 4H2a.5.5 0 0 1 0-1h3.5V2a1 1 0 0 1 1-1zm0 2h3V2h-3v1zM6 6.5a.5.5 0 0 0-1 .03l.3 5.5a.5.5 0 0 0 1-.06L6 6.5zm4.97.03a.5.5 0 0 0-1-.06l-.3 5.5a.5.5 0 1 0 1 .06l.3-5.5zM8 6a.5.5 0 0 0-.5.5v5.5a.5.5 0 0 0 1 0V6.5A.5.5 0 0 0 8 6z"/></svg>';

?>
<!-- Replaced as a whole after a quantity change (see the script below) -->
<div id="ts-cart-lines">

    <?php if ($reservedUntil) { ?>
        <div class="ts-alert ts-reserved-until">
            <svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zm0 1.5a5.5 5.5 0 1 1 0 11 5.5 5.5 0 0 1 0-11zM7.75 4a.75.75 0 0 0-.75.75V8.3l2.47 2.47a.75.75 0 0 0 1.06-1.06L8.5 7.68V4.75A.75.75 0 0 0 7.75 4z"/></svg>
            <span><?php echo Text::sprintf('COM_TICKETSTATION_RESERVED_UNTIL', '<strong>' . $reservedUntilText . '</strong>'); ?></span>
        </div>
    <?php } ?>

    <table class="ts-table ts-summary ts-cart" id="cart">

        <?php if ($items != 0) { ?>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('COM_TICKETSTATION_EVENT_INFORMATION'); ?></th>
                    <th scope="col" class="ts-price"><?php echo Text::_('COM_TICKETSTATION_PRICE'); ?></th>
                </tr>
            </thead>
        <?php } ?>

        <tbody>
            <?php foreach ($entries as $entry) {

                $line      = $entry->lines[0];
                $row       = $line->rows[0];
                $quantity  = $line->quantity;
                $lineTotal = $line->total;
                $name      = htmlspecialchars($row->eventname . ' - ' . $row->ticketname, ENT_QUOTES, 'UTF-8');

                ## Within the ticket's minimum and maximum per order
                $canDecrease = $quantity > max(1, (int) $row->min_qty);
                $canIncrease = $row->max_qty == 0 || $quantity < (int) $row->max_qty;

                ## Back to the page the ticket was ordered on (the parent ticket's): the seat
                ## chart, e.g. to change a seat, or the ticket page.
                $owner     = (int) ($row->owner_ticketid ?? $row->ticketid);
                $ticketUrl = Route::_(((int) ($row->owner_seatplans ?? 0) === 1
                    ? 'index.php?option=com_ticketstation&view=seatedevent&cid=' . $owner
                    : 'index.php?option=com_ticketstation&view=event&id=' . $owner) . ($itemid ? '&Itemid=' . $itemid : ''));
                $ticketTip = Text::_((int) ($row->owner_seatplans ?? 0) === 1 ? 'COM_TICKETSTATION_CART_TO_SEATPLAN' : 'COM_TICKETSTATION_CART_TO_TICKETPAGE');
                ?>

                <?php if (count($entry->lines) > 1) {
                    $seatLines = $entry->lines;
                    $seatCount = count($seatLines);
                    $seatTotal = array_sum(array_column($seatLines, 'total'));
                    $seatNames = array_map(fn ($seat) => htmlspecialchars($seat->seat, ENT_QUOTES, 'UTF-8'), $seatLines);
                    $preview   = implode(', ', array_slice($seatNames, 0, 4)) . ($seatCount > 4 ? ', &hellip;' : '');
                    ?>

                <tr class="ts-summary__item ts-summary__item--seats">
                    <td>
                        <span class="ts-summary__name">
                            <a class="ts-summary__link" href="<?php echo $ticketUrl; ?>" title="<?php echo $ticketTip; ?>"><?php echo $name; ?></a>
                        </span>

                        <?php if ($showDate) { ?>
                            <span class="ts-summary__date"><?php echo Date::long($row->startdate, true); ?></span>
                        <?php } ?>

                        <details class="ts-seats" data-seats="<?php echo (int) $row->ticketid; ?>">
                            <summary class="ts-seats__summary">
                                <span class="ts-seats__count"><?php echo Text::plural('COM_TICKETSTATION_CART_SEATS', $seatCount); ?></span>
                                <span class="ts-qty__unit">&times; <?php echo $price($row->price); ?></span>
                                <span class="ts-seats__preview"><?php echo $preview; ?></span>
                            </summary>

                            <ul class="ts-seats__list">
                                <?php foreach ($seatLines as $seatLine) {
                                    $seatName = htmlspecialchars($seatLine->seat, ENT_QUOTES, 'UTF-8'); ?>
                                    <li class="ts-seats__item">
                                        <span class="ts-seats__label"><?php echo Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . $seatName; ?></span>
                                        <a class="ts-btn ts-btn--danger ts-btn--icon ts-btn--remove" data-cart-remove
                                           href="<?php echo $task('task=remove&orderid=' . (int) $seatLine->rows[0]->orderid); ?>"
                                           title="<?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?>">
                                            <?php echo $trashIcon; ?>
                                            <span class="ts-visually-hidden"><?php echo Text::sprintf('COM_TICKETSTATION_REMOVE_SEAT_NAMED', $seatName); ?></span>
                                        </a>
                                    </li>
                                <?php } ?>
                            </ul>
                        </details>
                    </td>
                    <td class="ts-price">
                        <span class="ts-summary__amount"><?php echo $price($seatTotal); ?></span>
                    </td>
                </tr>

                <?php continue; } ?>

                <tr class="ts-summary__item">
                    <td>
                        <span class="ts-summary__name">
                            <a class="ts-summary__link" href="<?php echo $ticketUrl; ?>" title="<?php echo $ticketTip; ?>"><?php echo $name; ?></a>

                            <?php if ($line->seated) { ?>
                                <?php echo ' - ' . Text::_('COM_TICKETSTATION_SEATNUMBER') . ': ' . htmlspecialchars($line->seat, ENT_QUOTES, 'UTF-8'); ?>
                            <?php } ?>
                        </span>

                        <?php if ($showDate) { ?>
                            <span class="ts-summary__date"><?php echo Date::long($row->startdate, true); ?></span>
                        <?php } ?>

                        <?php if (!$line->seated) { ?>
                            <span class="ts-qty" role="group" aria-label="<?php echo Text::_('COM_TICKETSTATION_QUANTITY'); ?>">
                                <?php if ($canDecrease) { ?>
                                    <a class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn" data-cart-remove
                                       href="<?php echo $task('task=remove&orderid=' . (int) end($line->rows)->orderid); ?>"
                                       aria-label="<?php echo Text::sprintf('COM_TICKETSTATION_QTY_DECREASE', $name); ?>">&minus;</a>
                                <?php } else { ?>
                                    <span class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn is-disabled" aria-hidden="true">&minus;</span>
                                <?php } ?>

                                <span class="ts-qty__value"><?php echo $quantity; ?></span>

                                <button type="button" class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn" data-cart-add
                                        data-ticketid="<?php echo (int) $row->ticketid; ?>" data-eventid="<?php echo (int) $row->eventid; ?>"
                                        aria-label="<?php echo Text::sprintf('COM_TICKETSTATION_QTY_INCREASE', $name); ?>"<?php echo $canIncrease ? '' : ' disabled'; ?>>+</button>

                                <span class="ts-qty__unit">&times; <?php echo $price($row->price); ?></span>
                            </span>
                        <?php } ?>
                    </td>
                    <td class="ts-price">
                        <span class="ts-summary__price-cell">
                            <?php $removeUrl = $line->seated ? $task('task=remove&orderid=' . (int) $row->orderid) : $task('task=removeTicket&ticketid=' . (int) $row->ticketid); ?>
                            <a class="ts-btn ts-btn--danger ts-btn--icon ts-btn--remove" data-cart-remove href="<?php echo $removeUrl; ?>"
                               title="<?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?>">
                                <?php echo $trashIcon; ?>
                                <span class="ts-visually-hidden"><?php echo $line->seated ? Text::_('COM_TICKETSTATION_REMOVE') : Text::sprintf('COM_TICKETSTATION_REMOVE_LINE', $name); ?></span>
                            </a>
                            <span class="ts-summary__amount"><?php echo $price($lineTotal); ?></span>
                        </span>
                    </td>
                </tr>

            <?php } ?>

            <?php if ($waiters != 0) { ?>
                <tr class="ts-summary__waiting">
                    <td colspan="2">
                        <div class="ts-alert ts-waitinglist-note">
                            <?php echo Text::_('COM_TICKETSTATION_ITEMS_ON_WAITINGLIST'); ?><br />
                            <?php echo Text::_('COM_TICKETSTATION_A_PAYMENT_REQUEST_WILL_BE_SENT'); ?>
                        </div>
                    </td>
                </tr>
            <?php } ?>

            <?php ## One line per ticket with a quantity, like the tickets; each signup stays a row of its own
            foreach (WaitingList::cartLines($waitRows) as $wait) {
                $waitName    = htmlspecialchars($wait->eventname . ' - ' . $wait->ticketname, ENT_QUOTES, 'UTF-8');
                $canDecrease = $wait->quantity > 1;
                $canIncrease = $wait->max_qty == 0 || $wait->quantity < $wait->max_qty; ?>

                <tr class="ts-summary__item ts-summary__item--waiting">
                    <td>
                        <span class="ts-summary__name"><?php echo $waitName; ?></span>

                        <?php if ($showDate) { ?>
                            <span class="ts-summary__date"><?php echo Date::long($wait->startdate, true); ?></span>
                        <?php } ?>

                        <span class="ts-qty" role="group" aria-label="<?php echo Text::_('COM_TICKETSTATION_QUANTITY'); ?>">
                            <?php if ($canDecrease) { ?>
                                <a class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn" data-cart-remove
                                   href="<?php echo $task('task=removeWaitingOne&ticketid=' . $wait->ticketid); ?>"
                                   aria-label="<?php echo Text::sprintf('COM_TICKETSTATION_QTY_DECREASE', $waitName); ?>">&minus;</a>
                            <?php } else { ?>
                                <span class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn is-disabled" aria-hidden="true">&minus;</span>
                            <?php } ?>

                            <span class="ts-qty__value"><?php echo $wait->quantity; ?></span>

                            <button type="button" class="ts-btn ts-btn--secondary ts-btn--icon ts-qty__btn" data-cart-add data-task="waitinglist"
                                    data-ticketid="<?php echo $wait->ticketid; ?>" data-eventid="<?php echo $wait->eventid; ?>"
                                    aria-label="<?php echo Text::sprintf('COM_TICKETSTATION_QTY_INCREASE', $waitName); ?>"<?php echo $canIncrease ? '' : ' disabled'; ?>>+</button>
                        </span>
                    </td>
                    <td class="ts-price">
                        <a class="ts-btn ts-btn--danger ts-btn--icon ts-btn--remove" data-cart-remove title="<?php echo Text::_('COM_TICKETSTATION_REMOVE'); ?>" href="<?php echo $task('task=removeWaiting&ticketid=' . $wait->ticketid); ?>">
                            <?php echo $trashIcon; ?>
                            <span class="ts-visually-hidden"><?php echo Text::sprintf('COM_TICKETSTATION_REMOVE_LINE', $waitName); ?></span>
                        </a>
                    </td>
                </tr>

            <?php } ?>
        </tbody>

        <tfoot>
            <tr class="ts-summary__subtotal">
                <th scope="row"><?php echo Text::_('COM_TICKETSTATION_SUBTOTAL'); ?></th>
                <td class="ts-price"><?php echo $price($totals->tickets); ?></td>
            </tr>

            <?php if ($discount > 0): ?>
                <tr class="ts-summary__discount">
                    <th scope="row"><?php echo Text::_('COM_TICKETSTATION_DISCOUNT'); ?><?php if ($totals->discount_type == 1):?> (<?php echo (float) $totals->discount_amount;?>%)<?php endif; ?></th>
                    <td class="ts-price">- <?php echo $price($discount); ?></td>
                </tr>
            <?php endif; ?>

            <?php if ($fees > 0 && $totals->fee_type != OrderTotals::FEE_NONE): ?>
                <tr class="ts-summary__fees">
                    <th scope="row"><?php echo Text::_('COM_TICKETSTATION_FEES'); ?><?php echo OrderTotals::feeLabel($totals, $price); ?></th>
                    <td class="ts-price"><?php echo $price($fees); ?></td>
                </tr>
            <?php endif; ?>

            <tr class="ts-summary__total">
                <th scope="row"><?php echo Text::_('COM_TICKETSTATION_CART_TOTAL'); ?></th>
                <td class="ts-price"><?php echo $price($ordertotal); ?></td>
            </tr>
        </tfoot>
    </table>

</div>
