<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_dashboard
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Price;

/**
 * @var  array  $orders  DashboardData::latestOrders(): ordercode, orderdate, name, eventname, events, tickets, total
 */

$esc = static fn($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
?>
<?php if (!$orders) { ?>
    <p class="mb-0 text-muted"><?php echo Text::_('MOD_TICKETSTATION_DASHBOARD_ORDERS_NONE'); ?></p>
<?php } else { ?>
    <ul class="list-unstyled mb-2">
        <?php foreach ($orders as $order) { ?>
            <li class="mb-3">
                <div class="d-flex justify-content-between align-items-baseline gap-2">
                    <a href="index.php?option=com_ticketstation&amp;view=boxoffice&amp;controller=boxoffice&amp;task=edit&amp;cid=<?php echo $esc(rawurlencode($order->ordercode)); ?>"><?php echo $esc(trim((string) $order->name) !== '' ? $order->name : $order->ordercode); ?></a>
                    <span class="text-nowrap fw-semibold"><?php echo Price::_($order->total); ?></span>
                </div>
                <div class="small">
                    <?php echo $esc($order->eventname); ?><?php echo $order->events > 1 ? ' ' . $esc(Text::sprintf('MOD_TICKETSTATION_DASHBOARD_ORDER_MORE_EVENTS', $order->events - 1)) : ''; ?>
                </div>
                <div class="small text-muted">
                    <?php echo HTMLHelper::_('date', $order->orderdate, 'j M, H:i'); ?>
                    &middot; <?php echo Text::plural('MOD_TICKETSTATION_DASHBOARD_ORDER_TICKETS', $order->tickets); ?>
                    &middot; <?php echo $esc($order->ordercode); ?>
                </div>
            </li>
        <?php } ?>
    </ul>
    <a href="index.php?option=com_ticketstation&amp;view=boxoffice" class="small"><?php echo Text::_('MOD_TICKETSTATION_DASHBOARD_ORDERS_ALL'); ?></a>
<?php } ?>
