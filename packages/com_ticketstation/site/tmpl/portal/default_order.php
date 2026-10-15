<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

/** @var object $order One order of Portal::orders() */
$order  = $this->order;
$esc    = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

// Unrouted, like the other task links: the SEF router would drop the order parameter
$link = static fn (string $task) => $esc(\Joomla\CMS\Uri\Uri::root(true) . '/index.php?option=com_ticketstation&controller=portal&task=' . $task . '&order=' . (int) $order->ordercode);

$partly = !$order->refunded && $order->valid < $order->items;

?>
<article class="ts-card ts-portal__order<?php echo $order->upcoming ? '' : ' ts-portal__order--past'; ?>">

    <header class="ts-portal__order-head">
        <h3 class="ts-card__title">
            <?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_ORDER', '<span class="ts-order-code">' . $esc($order->ordercode) . '</span>'); ?>
        </h3>

        <?php if ($order->refunded) { ?>
            <span class="ts-badge ts-badge--soldout"><?php echo Text::_('COM_TICKETSTATION_PORTAL_STATUS_REFUNDED'); ?></span>
        <?php } elseif ($partly) { ?>
            <span class="ts-badge ts-badge--few"><?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_STATUS_PARTLY', $order->items - $order->valid, $order->items); ?></span>
        <?php } ?>
    </header>

    <p class="ts-portal__meta ts-text-muted">
        <?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_PLACED', $esc(Date::long($order->orderdate))); ?>
        &middot;
        <?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_TOTAL', $esc($order->total)); ?>
        <?php if ($order->email !== '') { ?>
            <br /><?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_PLACED_WITH', $esc($order->email)); ?>
        <?php } ?>
    </p>

    <?php foreach ($order->events as $event) { ?>
        <div class="ts-portal__event">
            <p class="ts-portal__event-name"><strong><?php echo $esc($event->eventname); ?></strong></p>
            <p class="ts-portal__meta ts-text-muted">
                <?php echo $esc(Date::long($event->startdate, true)); ?>
                <?php if ($event->venue !== '') { ?>
                    &middot; <?php echo $esc($event->venue); ?>
                <?php } ?>
            </p>

            <ul class="ts-portal__tickets">
                <?php foreach ($event->tickets as $ticket) { ?>
                    <li<?php echo $ticket->invalid >= $ticket->quantity ? ' class="ts-portal__ticket--invalid"' : ''; ?>>
                        <?php echo $esc($ticket->quantity . ' × ' . $ticket->name); ?>
                        <?php if ($ticket->seats) { ?>
                            <span class="ts-text-muted">(<?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_SEATS', $esc(implode(', ', $ticket->seats))); ?>)</span>
                        <?php } ?>
                        <?php if ($ticket->invalid > 0 && !$order->refunded) { ?>
                            <span class="ts-text-muted">&ndash; <?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_TICKETS_INVALID', $ticket->invalid); ?></span>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    <?php } ?>

    <?php if ($order->file !== '' || $order->calendar || $order->invoice !== '') { ?>
        <div class="ts-actions">
            <?php if ($order->file !== '') { ?>
                <a class="ts-btn ts-btn--primary" href="<?php echo $link('download'); ?>"><?php echo Text::_('COM_TICKETSTATION_PORTAL_DOWNLOAD'); ?></a>
            <?php } ?>

            <?php if ($order->calendar) { ?>
                <a class="ts-btn ts-btn--secondary" href="<?php echo $link('calendar'); ?>" download><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ADD_TO_CALENDAR'); ?></a>
            <?php } ?>

            <?php if ($order->invoice !== '') { ?>
                <a class="ts-btn ts-btn--secondary" href="<?php echo $link('invoice'); ?>"><?php echo Text::_('COM_TICKETSTATION_PORTAL_INVOICE'); ?></a>
            <?php } ?>
        </div>
    <?php } ?>

    <?php if ($order->wallet !== '') { ?>
        <div class="ts-wallet ts-portal__wallet">
            <?php echo $order->wallet; ?>
        </div>
    <?php } ?>

    <?php if ($order->pick) {
        $action = fn (string $task) => $esc(\Joomla\CMS\Router\Route::_('index.php?option=com_ticketstation&task=portal.' . $task . ($this->itemid ? '&Itemid=' . $this->itemid : '')));
        $many   = count($order->pick) > 1;
        // The event is named per ticket only when the order has tickets for more than one
        $multi  = count(array_unique(array_map(static fn ($t) => $t->eventname . '|' . $t->startdate, $order->pick))) > 1;
        $uid    = 'ts-pick-' . (int) $order->ordercode;
        ?>
        <details class="ts-portal__pick">
            <summary><?php echo Text::_('COM_TICKETSTATION_PORTAL_SELECT_TITLE'); ?></summary>

            <form class="ts-form" action="<?php echo $action('selection'); ?>" method="post">
                <?php if ($many) { ?>
                    <p class="ts-text-muted"><?php echo Text::_('COM_TICKETSTATION_PORTAL_SELECT_HINT'); ?></p>
                    <ul class="ts-portal__picklist">
                        <?php foreach ($order->pick as $i => $ticket) { ?>
                            <li>
                                <label for="<?php echo $uid . '-' . $i; ?>">
                                    <input type="checkbox" id="<?php echo $uid . '-' . $i; ?>" name="tickets[]" value="<?php echo (int) $ticket->orderid; ?>" checked />
                                    <?php echo $esc($ticket->ticket); ?>
                                    <?php if ($multi) { ?>
                                        <span class="ts-text-muted">&ndash; <?php echo $esc($ticket->eventname); ?>, <?php echo $esc(Date::long($ticket->startdate)); ?></span>
                                    <?php } ?>
                                </label>
                            </li>
                        <?php } ?>
                    </ul>
                <?php } else { ?>
                    <input type="hidden" name="tickets[]" value="<?php echo (int) $order->pick[0]->orderid; ?>" />
                <?php } ?>

                <input type="hidden" name="order" value="<?php echo (int) $order->ordercode; ?>" />

                <div class="ts-actions">
                    <?php if ($many) { ?>
                        <button type="submit" class="ts-btn ts-btn--secondary"><?php echo Text::_('COM_TICKETSTATION_PORTAL_DOWNLOAD_SELECTED'); ?></button>
                    <?php } ?>
                    <?php if ($order->mailto !== '') { ?>
                        <button type="submit" class="ts-btn ts-btn--secondary" formaction="<?php echo $action('mailself'); ?>"><?php echo Text::sprintf($many ? 'COM_TICKETSTATION_PORTAL_MAIL_SELF' : 'COM_TICKETSTATION_PORTAL_MAIL_SELF_ONE', $esc($order->mailto)); ?></button>
                    <?php } ?>
                </div>

                <?php if ($order->share) { ?>
                    <fieldset class="ts-portal__other">
                        <legend><?php echo Text::_('COM_TICKETSTATION_PORTAL_MAIL_OTHER_TITLE'); ?></legend>
                        <p class="ts-text-muted ts-portal__other-intro"><?php echo Text::_('COM_TICKETSTATION_PORTAL_MAIL_OTHER_INTRO'); ?></p>

                        <div class="ts-field">
                            <label class="ts-label" for="<?php echo $uid; ?>-to"><?php echo Text::_('COM_TICKETSTATION_PORTAL_RECIPIENT'); ?></label>
                            <input class="ts-input" type="email" id="<?php echo $uid; ?>-to" name="recipient" autocomplete="off" />
                        </div>
                        <div class="ts-field">
                            <label class="ts-label" for="<?php echo $uid; ?>-note"><?php echo Text::_('COM_TICKETSTATION_PORTAL_NOTE'); ?></label>
                            <textarea class="ts-input" id="<?php echo $uid; ?>-note" name="note" rows="2" maxlength="200"></textarea>
                        </div>

                        <div class="ts-actions">
                            <button type="submit" class="ts-btn ts-btn--primary" formaction="<?php echo $action('mailother'); ?>"><?php echo Text::_($many ? 'COM_TICKETSTATION_PORTAL_MAIL_OTHER_BUTTON' : 'COM_TICKETSTATION_PORTAL_MAIL_OTHER_BUTTON_ONE'); ?></button>
                        </div>
                    </fieldset>
                <?php } ?>

                <?php echo \Joomla\CMS\HTML\HTMLHelper::_('form.token'); ?>
            </form>
        </details>
    <?php } ?>

</article>
