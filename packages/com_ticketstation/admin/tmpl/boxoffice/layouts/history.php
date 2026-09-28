<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * The History of an order, newest first and grouped per day: on the order screen and on the
 * read-only screen of an order the automatic cleanup removed.
 *
 * @var  array  $displayData  history: the entries of History::getForOrder()
 */

use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$history = $displayData['history'] ?? [];

$history_icons = [
    'order_created'           => ['fa-plus-circle', 'primary'],
    'transaction_created'     => ['fa-credit-card', 'secondary'],
    'payment_initiated'       => ['fa-credit-card', 'info'],
    'order_paid'              => ['fa-check-circle', 'success'],
    'order_status_pending'    => ['fa-clock', 'warning'],
    'order_status_refunded'   => ['fa-reply', 'info'],
    'order_status_unpaid'     => ['fa-times-circle', 'danger'],
    'payment_failed'          => ['fa-times-circle', 'danger'],
    'payment_cancelled'       => ['fa-ban', 'secondary'],
    'payment_expired'         => ['fa-hourglass-end', 'secondary'],
    'payment_duplicate'       => ['fa-exclamation-triangle', 'danger'],
    'payment_refund_reported' => ['fa-reply', 'warning'],
    'tickets_generated'       => ['fa-ticket-alt', 'secondary'],
    'tickets_sent'            => ['fa-paper-plane', 'info'],
    'tickets_send_failed'     => ['fa-exclamation-triangle', 'danger'],
    'ticket_copy_sent'        => ['fa-paper-plane', 'info'],
    'confirmation_sent'       => ['fa-envelope', 'info'],
    'payment_reminder_sent'   => ['fa-bell', 'warning'],
    'ticket_scanned'          => ['fa-qrcode', 'success'],
    'ticket_scan_reset'       => ['fa-qrcode', 'secondary'],
    'ticket_blacklisted'      => ['fa-ban', 'danger'],
    'ticket_unblocked'        => ['fa-check', 'success'],
    'ticket_removed'          => ['fa-trash', 'danger'],
    'order_removed'           => ['fa-trash', 'danger'],
    'order_removed_auto'      => ['fa-broom', 'secondary'],
    'order_published'         => ['fa-eye', 'success'],
    'order_unpublished'       => ['fa-eye-slash', 'secondary'],
    'remark_updated'          => ['fa-comment', 'secondary'],
    'remark_removed'          => ['fa-comment-slash', 'secondary'],
    'invoice_created'         => ['fa-euro-sign', 'secondary'],
    'invoice_sent'            => ['fa-euro-sign', 'info'],
    'invoice_send_failed'     => ['fa-exclamation-triangle', 'danger'],
];

?>

<?php if (empty($history)) { ?>
    <p class="text-muted mb-0"><?= Text::_('COM_TICKETSTATION_HISTORY_EMPTY') ?></p>
<?php } else { ?>

    <div class="ts-history">
        <?php
        $current_day = null;

        foreach (array_reverse($history) as $entry) {

            // $entry->created is stored in UTC (History::log()); convert to the site/user timezone for display.
            $day = Date::_($entry->created, 'l d F Y', true);

            if ($day !== $current_day) {
                $current_day = $day;
                ?>
                <div class="ts-history-day"><?= $day; ?></div>
                <?php
            }

            [$icon, $color] = $history_icons[$entry->event_type] ?? ['fa-circle', 'secondary'];
            ?>
            <div class="ts-history-row">
                <div class="ts-history-time"><?= Date::_($entry->created, 'H:i'); ?></div>
                <div class="ts-history-icon text-<?= $color; ?>"><span class="fa <?= $icon; ?>" aria-hidden="true"></span></div>
                <div class="ts-history-message">
                    <?= htmlspecialchars($entry->message, ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($entry->actor) { ?>
                        <span class="ts-history-actor"><?= Text::_('COM_TICKETSTATION_HISTORY_BY') ?> <?= htmlspecialchars($entry->actor, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php } ?>
                </div>
            </div>
            <?php
        }
        ?>
    </div>

<?php } ?>

<style>
    .ts-history-day {
        font-weight: 600;
        margin: 1.25rem 0 0.5rem;
        padding-bottom: 0.25rem;
        border-bottom: 1px solid var(--border-color, #dee2e6);
    }

    .ts-history-day:first-child {
        margin-top: 0;
    }

    .ts-history-row {
        display: flex;
        align-items: baseline;
        gap: 0.75rem;
        padding: 0.35rem 0;
        border-bottom: 1px solid rgba(0,0,0,.05);
    }

    .ts-history-time {
        flex: 0 0 3.5rem;
        color: #6c757d;
        font-variant-numeric: tabular-nums;
    }

    .ts-history-icon {
        flex: 0 0 1.25rem;
        text-align: center;
    }

    .ts-history-message {
        flex: 1 1 auto;
    }

    .ts-history-actor {
        color: #6c757d;
        font-size: 0.85em;
        margin-left: 0.5rem;
    }
</style>
