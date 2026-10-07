<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

## Getting the global DB session
$session = Factory::getApplication()->getSession();
## Gettig the orderid if there is one.
$ordercode = $session->get('ordercode');

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_ORDER_DETAILS') . ' - ' . $app->get('sitename') );
TicketstationFunctions::addSiteStylesheet();
$document->addScript('components/com_ticketstation/assets/javascripts/emailsuggest.js', ['version' => 'auto'], ['defer' => true]);
$document->addScript('components/com_ticketstation/assets/javascripts/checkout-form.js', ['version' => 'auto'], ['defer' => true]);

## Redirection link in JRoute:
$itemid = TicketstationFunctions::getSiteItemid();
$gotocart = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));


?>

<div class="ticketstation ticketstation--checkout">

    <?php echo LayoutHelper::render('steps', ['current' => 3], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_ORDER_DETAILS'); ?></h1>
    </div>

    <p class="ts-intro"><?php echo Text::_('COM_TICKETSTATION_CREATEACCOUNT_NOW2'); ?></p>

    <?php echo LayoutHelper::render('checkout_notices', ['view' => $this], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

    <form id="general" class="ts-form" action="<?php echo Route::_('index.php?option=com_ticketstation&controller=checkout' . ($itemid ? '&Itemid=' . $itemid : '')); ?>" method="post" name="general">

        <?php echo LayoutHelper::render('checkout_fields', ['view' => $this], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

        <div class="ts-actions">
            <a class="ts-btn ts-btn--secondary ts-btn--back" href="<?php echo $gotocart; ?>">
                <?php echo Text::_('COM_TICKETSTATION_BACK'); ?>
            </a>

            <button type="submit" class="ts-btn ts-btn--primary ts-btn--next" id="ts-checkout-submit"><?php echo Text::_('COM_TICKETSTATION_CONTINUE'); ?></button>
        </div>

        <input type="hidden" name="option" value="com_ticketstation" />
        <input type="hidden" name="controller" value="checkout" />
        <input type="hidden" name="task" value="save" />
        <?php echo HTMLHelper::_( 'form.token' ); ?>

    </form>

</div>
