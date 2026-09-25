<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_ticketstation_basket
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @var  integer  $itemCount  Tickets in the cart
 * @var  string   $cartUrl    URL of the cart view
 * @var  object   $totals     Price strings: subtotal, fees, total; showFees is false when transaction costs are switched off
 *
 * The count is not displayed separately (the table has it). The hidden counter stands in for
 * #basket-item-count when no mini basket is on the page: com_ticketstation's JavaScript writes the
 * new count into that id after a ticket is added, and media/js/basket.js watches it to refresh the
 * table. The script gives this span the id only if nothing else on the page has it, so the id is
 * never duplicated.
 */
?>
<span data-basket-count hidden><?php echo $itemCount; ?></span>

<div class="ticketstation-basket-details" data-basket-details>
    <?php if ($itemCount > 0) : ?>
        <?php if ($orderCount > 0) : ?>
            <table class="ticketstation-basket-table">
                <tr>
                    <td><?php echo $orderCount . ' ' . Text::_($orderCount > 1 ? 'MOD_TICKETSTATION_BASKET_TICKETS' : 'MOD_TICKETSTATION_BASKET_TICKET'); ?></td>
                    <td><?php echo $totals->subtotal; ?></td>
                </tr>
                <?php if ($totals->showFees) : ?>
                    <tr>
                        <td><?php echo Text::_('MOD_TICKETSTATION_BASKET_FEES'); ?></td>
                        <td><?php echo $totals->fees; ?></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td><strong><?php echo Text::_('MOD_TICKETSTATION_BASKET_ORDERTOTAL'); ?></strong></td>
                    <td><strong><?php echo $totals->total; ?></strong></td>
                </tr>
            </table>
        <?php endif; ?>
        <?php if ($waitingCount > 0) : ?>
            <p class="ticketstation-basket-waiting"><?php echo $waitingCount . ' ' . Text::_($waitingCount > 1 ? 'MOD_TICKETSTATION_BASKET_TICKETS' : 'MOD_TICKETSTATION_BASKET_TICKET') . ' ' . Text::_('MOD_TICKETSTATION_BASKET_ON_WAITINGLIST'); ?></p>
        <?php endif; ?>
    <?php else : ?>
        <p class="ticketstation-basket-empty"><?php echo Text::_('MOD_TICKETSTATION_BASKET_EMPTY'); ?></p>
    <?php endif; ?>
</div>

<div class="ticketstation-basket-actions" data-basket-actions<?php echo $itemCount > 0 ? '' : ' hidden'; ?>>
    <a class="ticketstation-basket-button" href="<?php echo $cartUrl; ?>"><?php echo Text::_('MOD_TICKETSTATION_BASKET_VIEW_CART'); ?></a>
</div>
