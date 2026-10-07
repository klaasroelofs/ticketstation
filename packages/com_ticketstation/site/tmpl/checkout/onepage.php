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

## The combined checkout: the details, the order and the payment on one page (Configuration >
## Checkout layout). Chosen by the Checkout view when that setting is on.

$app      = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_CHECKOUT_PAGE_TITLE') . ' - ' . $app->get('sitename'));
TicketstationFunctions::addSiteStylesheet();
$document->addScript('components/com_ticketstation/assets/javascripts/emailsuggest.js', ['version' => 'auto'], ['defer' => true]);

$itemid    = TicketstationFunctions::getSiteItemid();
$gotocart  = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));
$render    = fn ($layout, $data) => LayoutHelper::render($layout, $data, null, ['component' => 'com_ticketstation', 'client' => 0]);
$price     = fn ($amount) => (new TicketstationFunctions)->showprice($this->config->priceformat, $amount, $this->config->valuta);

$ordertotal   = $this->totals->total;
$hasItems     = count($this->items) > 0;
$waitingCount = count($this->waiters);

?>

<div class="ticketstation ticketstation--checkout ticketstation--onepage">

    <?php echo $render('steps', ['current' => 3]); ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_PAGE_TITLE'); ?></h1>
    </div>

    <?php echo $render('checkout_notices', ['view' => $this]); ?>

    <div class="ts-checkout">

        <aside class="ts-checkout__aside" aria-labelledby="ts-checkout-order">
            <section class="ts-card ts-checkout__order">
                <h2 class="ts-subtitle" id="ts-checkout-order"><?php echo Text::_('COM_TICKETSTATION_YOUR_ORDER'); ?></h2>

                <?php if ($hasItems) {
                    echo $render('order_summary', ['items' => $this->items, 'coords' => $this->coords ?? [], 'config' => $this->config, 'totals' => $this->totals]);
                } ?>

                <?php if ($waitingCount) { ?>
                    <div class="ts-alert ts-alert--warning ts-waitinglist-notice">
                        <p><strong><?php echo Text::_($hasItems ? 'COM_TICKETSTATION_PLEASE_CONFIRM_WAITINGLIST_TIKETS' : 'COM_TICKETSTATION_ITEMS_ON_WAITINGLIST'); ?></strong></p>
                        <p><?php echo Text::_($hasItems ? 'COM_TICKETSTATION_PLEASE_CONFIRM_WAITINGLIST_TIKETS_DESC' : 'COM_TICKETSTATION_A_PAYMENT_REQUEST_WILL_BE_SENT'); ?></p>
                        <ul class="ts-checkout__waiting">
                            <?php foreach ($this->waiters as $row) { ?>
                                <li><?php echo htmlspecialchars($row->eventname . ' - ' . $row->ticketname, ENT_QUOTES, 'UTF-8'); ?></li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>

                <p class="ts-checkout__change"><a href="<?php echo $gotocart; ?>"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_CHANGE_ORDER'); ?></a></p>
            </section>
        </aside>

        <div class="ts-checkout__main">

            <p class="ts-intro"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_COMBINED_INTRO'); ?></p>

            <form id="general" class="ts-form" action="<?php echo Route::_('index.php?option=com_ticketstation&controller=checkout' . ($itemid ? '&Itemid=' . $itemid : '')); ?>" method="post" name="general">

                <?php echo $render('checkout_fields', ['view' => $this]); ?>

                <?php if ($hasItems && $ordertotal > 0 && $this->paymentsOn && $this->methods) { ?>
                    <div class="ts-checkout__payment">
                        <?php echo $render('payment_methods', ['methods' => $this->methods]); ?>
                    </div>
                <?php } ?>

                <?php if ($hasItems) {
                    echo $render('payment_terms', ['config' => $this->config]);
                } ?>

                <?php if ($hasItems && $ordertotal == 0) { ?>
                    <p class="ts-note"><?php echo Text::sprintf('COM_TICKETSTATION_ZERO_TOTAL', $price(0)); ?></p>
                <?php } ?>

                <?php if ($hasItems && !$this->paymentsOn && $ordertotal > 0) { ?>
                    <div class="ts-alert ts-alert--danger"><?php echo Text::_('COM_TICKETSTATION_ONLINE_PAYMENTS_OFF_ORDER'); ?></div>
                <?php } ?>

                <div class="ts-actions">
                    <a class="ts-btn ts-btn--secondary ts-btn--back" href="<?php echo $gotocart; ?>">
                        <?php echo Text::_('COM_TICKETSTATION_BACK'); ?>
                    </a>

                    <?php if (!$hasItems) { ?>
                        <button type="submit" class="ts-btn ts-btn--primary ts-btn--next" id="ts-checkout-submit"><?php echo Text::_('COM_TICKETSTATION_JOIN_WAITINGLIST'); ?></button>
                    <?php } elseif ($ordertotal > 0 && !$this->paymentsOn) { ?>
                        <?php ## Paid tickets while online payments are off: nothing to pay with. ?>
                    <?php } elseif ($ordertotal > 0) { ?>
                        <button type="submit" class="ts-btn ts-btn--primary ts-btn--next" id="ts-checkout-submit"><?php echo Text::sprintf('COM_TICKETSTATION_PAY_AMOUNT', $price($ordertotal)); ?></button>
                    <?php } else { ?>
                        <button type="submit" class="ts-btn ts-btn--primary ts-btn--next" id="ts-checkout-submit"><?php echo Text::_('COM_TICKETSTATION_PLACE_ORDER'); ?></button>
                    <?php } ?>
                </div>

                <input type="hidden" name="option" value="com_ticketstation" />
                <input type="hidden" name="controller" value="checkout" />
                <input type="hidden" name="task" value="save" />
                <input type="hidden" name="pay" value="1" />
                <?php echo HTMLHelper::_('form.token'); ?>

            </form>

        </div>
    </div>

</div>

<script>
    // One click only: a double click on the pay button must not place the order twice.
    document.addEventListener('DOMContentLoaded', function () {
        var form   = document.getElementById('general');
        var button = document.getElementById('ts-checkout-submit');

        if (!form || !button) { return; }

        form.addEventListener('submit', function () {
            setTimeout(function () { button.disabled = true; }, 0);
        });

        // Back button after the payment provider: the page returns from the cache with the button off
        window.addEventListener('pageshow', function (event) {
            if (event.persisted) { button.disabled = false; }
        });
    });
</script>
