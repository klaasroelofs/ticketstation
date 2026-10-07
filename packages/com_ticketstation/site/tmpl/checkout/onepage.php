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
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Site\Service\CartPage;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

## The combined checkout: the cart, the details and the payment on one page (Configuration >
## Checkout layout). Chosen by the Checkout view when that setting is on. After a change of the
## cart or a coupon the script (checkout.js) swaps in fresh cart lines and a fresh payment block.

$app      = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_CHECKOUT_PAGE_TITLE') . ' - ' . $app->get('sitename'));
TicketstationFunctions::addSiteStylesheet();
$document->addScript('components/com_ticketstation/assets/javascripts/emailsuggest.js', ['version' => 'auto'], ['defer' => true]);
$document->addScript('components/com_ticketstation/assets/javascripts/checkout.js', ['version' => 'auto'], ['defer' => true]);

$itemid   = TicketstationFunctions::getSiteItemid();
$shop_on  = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));
$render   = fn ($layout, $data) => LayoutHelper::render($layout, $data, null, ['component' => 'com_ticketstation', 'client' => 0]);
$page     = $this->page;
$base     = Uri::root(true) . '/index.php?option=com_ticketstation';

?>

<div class="ticketstation ticketstation--checkout ticketstation--onepage">

    <?php echo $render('steps', ['current' => 2]); ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_PAGE_TITLE'); ?></h1>
    </div>

    <?php echo $render('checkout_notices', ['view' => $this]); ?>

    <div class="ts-checkout"
         data-refresh-url="<?php echo htmlspecialchars($base . '&controller=cart&task=refresh&format=raw', ENT_QUOTES, 'UTF-8'); ?>"
         data-buy-url="<?php echo htmlspecialchars($base . '&controller=order&task=buyticket&format=raw', ENT_QUOTES, 'UTF-8'); ?>"
         data-coupon-url="<?php echo htmlspecialchars($base . '&controller=checkout&task=coupon&format=raw', ENT_QUOTES, 'UTF-8'); ?>"
         data-token="<?php echo htmlspecialchars(Session::getFormToken(), ENT_QUOTES, 'UTF-8'); ?>"
         data-ordercode="<?php echo (int) $page->ordercode; ?>"
         data-failed="<?php echo htmlspecialchars(Text::_('COM_TICKETSTATION_REQUEST_FAILED'), ENT_QUOTES, 'UTF-8'); ?>">

        <aside class="ts-checkout__aside" aria-labelledby="ts-checkout-order">
            <section class="ts-card ts-checkout__order">
                <h2 class="ts-subtitle" id="ts-checkout-order"><?php echo Text::_('COM_TICKETSTATION_YOUR_ORDER'); ?></h2>

                <!-- Problems from a change in the background; don't remove -->
                <div id="ts-cart-message" class="ts-message" role="status" aria-live="polite" hidden></div>

                <?php echo CartPage::renderLines($page); ?>

                <?php if ($this->config->use_coupons) { ?>
                    <form action="<?php echo Route::_('index.php?option=com_ticketstation' . ($itemid ? '&Itemid=' . $itemid : '')); ?>" method="post" id="ts-coupon-form" class="ts-coupon">
                        <label class="ts-label" for="couponcode"><?php echo Text::_('COM_TICKETSTATION_COUPON_CODE'); ?></label>

                        <div class="ts-inline-form">
                            <input name="couponcode" id="couponcode" type="text" class="ts-input" maxlength="50" autocomplete="off" aria-describedby="ts-coupon-message" />
                            <button type="submit" class="ts-btn ts-btn--secondary"><?php echo Text::_('COM_TICKETSTATION_SUBMIT_COUPON'); ?></button>
                        </div>

                        <div id="ts-coupon-message" class="ts-message" role="status" aria-live="polite" hidden></div>

                        <input type="hidden" name="task" value="coupon" />
                        <input type="hidden" name="controller" value="checkout" />
                        <input type="hidden" name="option" value="com_ticketstation" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>
                <?php } ?>

                <p class="ts-checkout__change"><a href="<?php echo $shop_on; ?>"><?php echo Text::_('COM_TICKETSTATION_CONTINUE_SHOPPING'); ?></a></p>
            </section>
        </aside>

        <div class="ts-checkout__main">

            <p class="ts-intro"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_COMBINED_INTRO'); ?></p>

            <form id="general" class="ts-form" action="<?php echo Route::_('index.php?option=com_ticketstation&controller=checkout' . ($itemid ? '&Itemid=' . $itemid : '')); ?>" method="post" name="general">

                <?php echo $render('checkout_fields', ['view' => $this]); ?>

                <?php if ($this->config->show_remark_field == 1) { ?>
                    <div class="ts-field ts-remarks">
                        <label class="ts-label" for="remarks"><?php echo Text::_('COM_TICKETSTATION_ENTER_REMARKS'); ?></label>
                        <textarea class="ts-textarea" rows="3" id="remarks" name="remarks" maxlength="255"><?php echo htmlspecialchars($this->customerNote, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p id="chars-remaining" class="ts-field__hint ts-chars-remaining" aria-live="polite"><?php echo Text::_('COM_TICKETSTATION_REMAINING'); ?> <?php echo 255 - mb_strlen($this->customerNote); ?></p>
                    </div>
                <?php } ?>

                <?php echo CartPage::renderPay($page); ?>

                <input type="hidden" name="option" value="com_ticketstation" />
                <input type="hidden" name="controller" value="checkout" />
                <input type="hidden" name="task" value="save" />
                <input type="hidden" name="pay" value="1" />
                <?php echo HTMLHelper::_('form.token'); ?>

            </form>

        </div>
    </div>

</div>
