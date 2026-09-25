<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_ticketstation_basket
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

/**
 * Variables provided by Dispatcher::getLayoutData():
 *
 * @var  \Joomla\Registry\Registry  $params       Module parameters
 * @var  \stdClass                  $module       Module object
 * @var  string                     $displayMode  'mini' or 'full'
 * @var  integer                    $itemCount    Tickets in the cart
 * @var  string                     $cartUrl      URL of the cart view
 * @var  boolean                    $hidden       Render hidden (empty cart and "hide when empty" is on)
 * @var  object                     $totals       Price strings, only set in full mode
 */

// The id and the "display" toggling are a contract with com_ticketstation: its event views
// show/hide #ticketstation_basket_module and rewrite #basket-item-count after a ticket is added.
// jQuery resolves an id to the first element on the page, so only the mini basket may carry it:
// a full basket next to a mini one would never be reached by the component. The full basket is
// driven by media/js/basket.js through the data-basket-module hook instead.
?>
<div<?php echo $displayMode === 'mini' ? ' id="ticketstation_basket_module"' : ''; ?> data-basket-module="<?php echo $displayMode; ?>"
     class="ticketstation_basket_module mod-ticketstation-basket mod-ticketstation-basket--<?php echo $displayMode; ?><?php echo $params->get('moduleclass_sfx') ? ' ' . htmlspecialchars((string) $params->get('moduleclass_sfx'), ENT_QUOTES, 'UTF-8') : ''; ?>"
     style="display: <?php echo $hidden ? 'none' : 'block'; ?>;">
    <?php require ModuleHelper::getLayoutPath($module->module, 'default_' . $displayMode); ?>
</div>
