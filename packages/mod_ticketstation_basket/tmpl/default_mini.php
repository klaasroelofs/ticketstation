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
 * @var  \Joomla\Registry\Registry  $params     Module parameters
 * @var  integer                    $itemCount  Tickets in the cart
 * @var  string                     $cartUrl    URL of the cart view
 */

$iconClass = htmlspecialchars((string) $params->get('minibasket_icon_class', 'fa fa-shopping-basket'), ENT_QUOTES, 'UTF-8');
?>
<div class="ticketstation-minibasket-button">
    <span class="basket-item-info">
        <a class="link" href="<?php echo $cartUrl; ?>" aria-label="<?php echo Text::_('MOD_TICKETSTATION_BASKET_VIEW_CART'); ?>">
            <i class="<?php echo $iconClass; ?>" aria-hidden="true"></i>
            <span id="basket-item-count" class="basket-item-count"><?php echo $itemCount; ?></span>
        </a>
    </span>
</div>
