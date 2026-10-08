<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die('Restricted Access');

use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * The reservation so far, grouped like the cart of the checkout: a ticket without a seat is one
 * line with its quantity; a seated ticket is named once, with its seats listed below it. Shown on
 * every step of the reservation wizard, and refreshed by the seat chart after each change.
 *
 * @var  array  $displayData  'lines'      => ReservationModel::getReservationLines()
 *                            'config'     => priceformat and valuta
 *                            'chart'      => the ticket whose seat chart is open (0 on other steps):
 *                                            only its seats can be removed or switched here
 *                            'categories' => the price categories of that chart
 *                            'total'      => show the total (default false)
 *                            'return'     => the step this is shown on, to come back to after removing a line
 */

$lines      = $displayData['lines'];
$config     = $displayData['config'];
$chart      = (int) ($displayData['chart'] ?? 0);
$categories = $displayData['categories'] ?? [];
$showTotal  = !empty($displayData['total']);
$return     = (string) ($displayData['return'] ?? 'default');

$price = fn ($amount) => TicketstationFunctions::showprice($config->priceformat, $amount, $config->valuta);
$e     = fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
$token = Session::getFormToken() . '=1';
$total = array_sum(array_column($lines, 'total'));
$remove = fn (string $what) => Route::_('index.php?option=com_ticketstation&controller=reservation&task=removeRow&' . $what . '&return=' . $return . '&' . $token);
?>
<div id="reservation-lines" data-rows="<?= (int) array_sum(array_column($lines, 'quantity')) ?>">
    <?php if (!$lines) : ?>
        <p class="text-muted mb-0"><?= Text::_('COM_TICKETSTATION_RESERVATION_NOTHING_YET') ?></p>
    <?php else : ?>
        <ul class="list-group mb-3">
            <?php foreach ($lines as $line) : ?>
                <li class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <strong><?= $e($line->eventname . ' - ' . $line->ticketname) ?></strong>
                            <?php if ($line->date) : ?>
                                <div class="small text-muted"><?= $e(Date::long($line->date) . ", " . date("H:i", strtotime($line->date))) ?></div>
                            <?php endif; ?>
                        </div>
                        <strong class="text-nowrap"><?= $price($line->total) ?></strong>
                    </div>

                    <?php if (!$line->seated) : ?>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="small text-muted"><?= (int) $line->quantity ?> &times; <?= $price($line->unit) ?></span>
                            <a href="<?= $remove('ticketid=' . (int) $line->ticketid) ?>" class="btn btn-sm btn-outline-danger"
                               title="<?= $e(Text::sprintf('COM_TICKETSTATION_RESERVATION_REMOVE_LINE', $line->ticketname)) ?>">&times;</a>
                        </div>
                    <?php else : ?>
                        <div class="small text-muted">
                            <?= Text::plural('COM_TICKETSTATION_RESERVATION_SEATS', count($line->seats)) ?> &times; <?= $price($line->unit) ?>
                        </div>

                        <ul class="list-unstyled mb-0 mt-1">
                            <?php foreach ($line->seats as $seat) : ?>
                                <li class="d-flex justify-content-between align-items-center gap-2 py-1"
                                    id="cart-item-<?= (int) $seat->sector ?>" data-id="<?= (int) $seat->sector ?>">
                                    <span>
                                        <?= Text::_('COM_TICKETSTATION_RESERVATION_SEAT') ?> <?= $e($seat->label) ?>
                                        <?php if ($line->owner === $chart && $seat->free && $categories) : ?>
                                            <select class="form-select form-select-sm d-inline-block w-auto ms-2 seat-category"
                                                    data-orderid="<?= (int) $seat->orderid ?>"
                                                    aria-label="<?= $e(Text::sprintf('COM_TICKETSTATION_RESERVATION_CATEGORY_OF_SEAT', $seat->label)) ?>">
                                                <?php foreach ($categories as $category) : ?>
                                                    <option value="<?= (int) $category->ticketid ?>"<?= (int) $category->ticketid === $seat->ticketid ? ' selected' : '' ?>>
                                                        <?= $e($category->ticketname) ?> &ndash; <?= $price($category->ticketprice) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        <?php endif; ?>
                                    </span>
                                    <?php // On the open chart the seat is released by script, so the chart stays as it is ?>
                                    <a href="<?= $line->owner === $chart ? '#' : $remove('orderid=' . (int) $seat->orderid) ?>"
                                       class="<?= $line->owner === $chart ? 'remove-seat ' : '' ?>btn btn-sm btn-outline-danger" data-id="<?= (int) $seat->sector ?>"
                                       title="<?= $e(Text::sprintf('COM_TICKETSTATION_RESERVATION_REMOVE_SEAT', $seat->label)) ?>">&times;</a>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <?php if ($chart && $line->owner !== $chart) : ?>
                            <a class="small" href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=selectTicket&ticketid=' . (int) $line->owner . '&' . $token) ?>">
                                <?= Text::_('COM_TICKETSTATION_RESERVATION_OPEN_CHART') ?>
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>

            <?php if ($showTotal) : ?>
                <li class="list-group-item d-flex justify-content-between">
                    <strong><?= Text::_('COM_TICKETSTATION_ORDER_TOTAL') ?></strong>
                    <strong><?= $price($total) ?></strong>
                </li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
</div>
