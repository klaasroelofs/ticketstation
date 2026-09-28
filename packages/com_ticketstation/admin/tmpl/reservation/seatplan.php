<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Admin equivalent of site/tmpl/seatedevent/default.php, wired to
 * ReservationController::makeReservation()/removeSeat()/finishSeats() (admin session) instead
 * of OrderseatedController (site session). Simple seat-by-seat selection only - the multi-seat
 * "pick a price per seat" step that the frontend has for row/section tickets is out of scope.
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatChart;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

HTMLHelper::_('jquery.framework');

// The same scalable chart as the frontend seat picker (site assets/css/seatmap.css).
SeatChart::loadAssets();

// Which seat_sector ids are already part of THIS reservation (so they render as "mine" /
// removable, rather than as booked-by-someone-else).
$mySeats = [];

foreach ($this->summary as $row)
{
    if ($row->seat_sector)
    {
        $mySeats[(int) $row->seat_sector] = true;
    }
}

$chartOwner      = (int) $this->ticket->ticketid;
$chartSettings   = SeatChart::settings($chartOwner);
$chartBackground = SeatChart::background($chartSettings, $chartOwner);
$chartShapes     = SeatChart::shapes($chartSettings);
$chartCanvas     = SeatChart::canvas($chartSettings, $this->seats, $chartBackground, $chartShapes);
?>

<div class="btn-toolbar mb-3" role="toolbar">
    <a class="btn btn-danger" href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=cancel&' . \Joomla\CMS\Session\Session::getFormToken() . '=1') ?>">
        <span class="icon-cancel" aria-hidden="true"></span> <?= Text::_('JTOOLBAR_CANCEL') ?>
    </a>
    <a class="btn btn-primary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=controlpanel') ?>">
        <span class="icon-home" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT') ?>
    </a>
    <a class="btn btn-secondary ms-2" href="<?= Route::_('index.php?option=com_ticketstation&view=reservation') ?>">
        <span class="icon-arrow-left" aria-hidden="true"></span> <?= Text::_('COM_TICKETSTATION_RESERVATION_BACK') ?>
    </a>
</div>

<style>
    .seat-element {
        cursor: pointer;
    }
    .seat-element.seat-mine {
        background-color: #FFA500 !important;
        color: #fff !important;
        border-color: #000 !important;
        cursor: pointer;
    }
    .seat-element.seat-taken {
        cursor: no-drop;
    }
    .seat-element.seat-blocked {
        cursor: pointer;
    }
</style>

<div class="card mt-3 rounded-to">
    <h3 class="card-header"><?= Text::_('COM_TICKETSTATION_RESERVATION_STEP2B_TITLE') ?></h3>
    <div class="card-body">

        <p>
            <strong><?= htmlspecialchars($this->ticket->ticketname, ENT_QUOTES, 'UTF-8') ?></strong>
            &mdash; <?= TicketstationFunctions::showprice($this->config->priceformat, $this->ticket->ticketprice, $this->config->valuta) ?>
            &mdash; <?= (int) $this->available ?> <?= Text::_('COM_TICKETSTATION_AVAILABLE') ?>
        </p>

        <div class="row">
            <div class="col-xl-8">

                <div id="ajaxMessage" class="alert alert-danger" style="display:none;"></div>

                <?php if (!empty($this->categories)) : ?>
                    <div class="mb-3" style="max-width: 400px;">
                        <label for="price-category" class="form-label"><?= Text::_('COM_TICKETSTATION_RESERVATION_PRICE_CATEGORY') ?></label>
                        <select id="price-category" class="form-select">
                            <?php foreach ($this->categories as $category) : ?>
                                <option value="<?= (int) $category->ticketid ?>"><?= htmlspecialchars($category->ticketname, ENT_QUOTES, 'UTF-8') ?> &ndash; <?= TicketstationFunctions::showprice($this->config->priceformat, $category->ticketprice, $this->config->valuta) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text"><?= Text::_('COM_TICKETSTATION_RESERVATION_PRICE_CATEGORY_DESC') ?></div>
                    </div>
                <?php endif; ?>

                <div>
                    <?= SeatChart::open($chartCanvas, $chartBackground, $chartSettings, $chartShapes, $this->seats) ?>
                        <?php foreach ($this->seats as $row) :
                            $mine       = isset($mySeats[(int) $row->id]);
                            $lineHeight = '';

                            // A blocked seat can still be booked here (after a confirmation); it
                            // shows grey, and goes back to grey when it is removed again.
                            $blocked = (int) $row->blocked === 1;
                            $taken   = ! $mine && $row->booked > 0 && ! ($blocked && (int) $row->orderid === 0);

                            if ($taken)
                            {
                                $seatClass  = 'seat-element seat-taken';
                                $style      = 'color:#fff; border-color:#000; ' . $lineHeight;
                                $background = 'background-color:#FF0000;';
                            }
                            elseif ($blocked)
                            {
                                $seatClass  = 'seat-element seat-blocked';
                                $style      = 'color:#fff; border-color:#000; ' . $lineHeight;
                                $background = 'background-color:#888888;';
                            }
                            else
                            {
                                $seatClass  = 'seat-element';
                                $style      = 'color:#' . htmlspecialchars($row->font_color, ENT_QUOTES, 'UTF-8')
                                    . '; border-color:#' . htmlspecialchars($row->border_color, ENT_QUOTES, 'UTF-8') . '; ' . $lineHeight;
                                $bg         = $row->background_color !== '' ? '#' . htmlspecialchars($row->background_color, ENT_QUOTES, 'UTF-8') : '#e1fdda';
                                $background = 'background-color:' . $bg . ';';
                            }

                            if ($mine)
                            {
                                $seatClass .= ' seat-mine';
                            }
                            ?>
                            <div id="seat-<?= (int) $row->id ?>" class="<?= $seatClass ?>"
                                 data-mine="<?= $mine ? '1' : '0' ?>" data-taken="<?= $taken ? '1' : '0' ?>" data-blocked="<?= $blocked ? '1' : '0' ?>"
                                 style="<?= SeatChart::seatStyle($row, $chartCanvas) . $background . $style ?>">
                                <?php if ((int) $row->type === 1) : ?>
                                    <?= htmlspecialchars($row->row_name . $row->seatid, ENT_QUOTES, 'UTF-8') ?>
                                <?php else : ?>
                                    <strong><?= htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8') ?></strong>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?= SeatChart::close() ?>
                </div>

            </div>

            <div class="col-xl-4">
                <h4><?= Text::_('COM_TICKETSTATION_RESERVATION_CART_TITLE') ?></h4>
                <ul id="cart-list" class="list-group mb-3">
                    <?php foreach ($this->summary as $row) : ?>
                        <?php if ($row->seat_sector) : ?>
                            <?php $seat = $row->seat_row_name !== '' ? $row->seat_row_name . $row->seat_number : $row->seat_number; ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center" id="cart-item-<?= (int) $row->seat_sector ?>" data-id="<?= (int) $row->seat_sector ?>">
                                <?= htmlspecialchars($seat, ENT_QUOTES, 'UTF-8') ?>
                                <a href="#" class="remove-seat btn btn-sm btn-outline-danger" data-id="<?= (int) $row->seat_sector ?>">&times;</a>
                            </li>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </ul>

                <a id="finish-seats-btn" class="btn btn-primary <?= count($mySeats) < 1 ? 'disabled' : '' ?>"
                   href="<?= Route::_('index.php?option=com_ticketstation&controller=reservation&task=finishSeats') ?>">
                    <?= Text::_('COM_TICKETSTATION_RESERVATION_CONTINUE') ?>
                </a>
            </div>
        </div>

    </div>
</div>

<script>
    (function ($) {
        'use strict';

        function showMessage(msg) {
            $('#ajaxMessage').text(msg).show();
            setTimeout(function () { $('#ajaxMessage').hide(); }, 4000);
        }

        function updateFinishButton() {
            var count = $('#cart-list li').length;
            $('#finish-seats-btn').toggleClass('disabled', count < 1);
        }

        // CSRF token name/value pair for this session, sent with every write request below.
        var csrfTokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';

        $(document).ready(function () {

            $('#glassbox').on('click', '.seat-element', function () {
                var $seat = $(this);

                if ($seat.data('taken') == 1 || $seat.data('mine') == 1) {
                    return;
                }

                if ($seat.data('blocked') == 1 && !window.confirm(<?= json_encode(Text::_('COM_TICKETSTATION_RESERVATION_BOOK_BLOCKED_CONFIRM')) ?>)) {
                    return;
                }

                var id = $seat.attr('id').replace('seat-', '');

                $.ajax({
                    type: 'post',
                    url: 'index.php?option=com_ticketstation&controller=reservation&task=makeReservation&format=raw',
                    data: {id: id, categoryid: $('#price-category').val() || 0, [csrfTokenName]: 1},
                    dataType: 'json'
                }).done(function (result) {
                    if (result.error === '0') {
                        $seat.addClass('seat-mine').data('mine', 1);
                        $('#cart-list').append(
                            '<li class="list-group-item d-flex justify-content-between align-items-center" id="cart-item-' + id + '" data-id="' + id + '">'
                            + $('<div>').text(result.seatid).html()
                            + ' <a href="#" class="remove-seat btn btn-sm btn-outline-danger" data-id="' + id + '">&times;</a></li>'
                        );
                        updateFinishButton();
                    } else {
                        showMessage(result.msg);
                    }
                }).fail(function () {
                    showMessage('<?= Text::_('COM_TICKETSTATION_RESERVATION_SAVE_FAILED') ?>');
                });
            });

            $('#cart-list').on('click', '.remove-seat', function (e) {
                e.preventDefault();

                var id = $(this).data('id');

                $.ajax({
                    type: 'post',
                    url: 'index.php?option=com_ticketstation&controller=reservation&task=removeSeat&format=raw',
                    data: {id: id, [csrfTokenName]: 1},
                    dataType: 'json'
                }).done(function (result) {
                    if (result.error === '0') {
                        $('#seat-' + id).removeClass('seat-mine').data('mine', 0);
                        $('#cart-item-' + id).remove();
                        updateFinishButton();
                    } else {
                        showMessage(result.msg);
                    }
                }).fail(function () {
                    showMessage('<?= Text::_('COM_TICKETSTATION_RESERVATION_SAVE_FAILED') ?>');
                });
            });

            $('#finish-seats-btn').on('click', function (e) {
                if ($(this).hasClass('disabled')) {
                    e.preventDefault();
                }
            });

        });
    })(jQuery);
</script>
