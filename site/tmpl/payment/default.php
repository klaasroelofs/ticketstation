<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

## Getting the global DB session
$session = Factory::getApplication()->getSession();
## Gettig the orderid if there is one.
$ordercode = $session->get('ordercode');

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_STEP_PAYMENT') . ' - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );

$itemid = TicketstationFunctions::getSiteItemid();
$shop_on = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));
$gotocheckout = Route::_('index.php?option=com_ticketstation&view=checkout' . ($itemid ? '&Itemid=' . $itemid : ''));

$getamount = new getAmount();

$ordertotal = $getamount->_getAmount($ordercode);
$fees 		= $getamount->_getFees($ordercode);
$discount 	= $getamount->_getDiscount($ordercode);

$count = count($this->items);

## Tickets still on the waiting list for this ordercode (the view returns a one-row list).
$waiting = (int) ($this->waitlist[0]->total ?? 0);

$genderLabels = [
    '1' => Text::_('COM_TICKETSTATION_MR'),
    '2' => Text::_('COM_TICKETSTATION_MRS'),
];

## Terms and privacy statement links (Configuration > Company); a link that is not set is left out
$termsLinks = [];

foreach (['terms_url' => 'COM_TICKETSTATION_TERMS_AND_CONDITIONS', 'privacy_url' => 'COM_TICKETSTATION_PRIVACY_STATEMENT'] as $field => $label) {
    $url = Config::toAbsoluteLink($this->config->$field ?? '');

    if ($url !== '') {
        $termsLinks[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_($label) . '</a>';
    }
}
?>

<div class="ticketstation ticketstation--payment">

    <?php if ($count != 0) { ?>
        <?php echo LayoutHelper::render('steps', ['current' => 4], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>
    <?php } ?>

    <?php if ($count == 0) { ?>

        <div class="page-header">
            <h1 class="ts-page-title"><?= Text::_($waiting > 0 ? 'COM_TICKETSTATION_WAITINGLIST_REGISTERED' : 'COM_TICKETSTATION_YOUR_CART_EMPTY'); ?></h1>
        </div>

        <section class="ts-card ts-empty">
            <p><?= Text::_($waiting > 0 ? 'COM_TICKETSTATION_WAITINGLIST_CHECK_MAIL' : 'COM_TICKETSTATION_GO_TO_UPCOMING'); ?></p>

            <div class="ts-actions">
                <a class="ts-btn ts-btn--primary" href="<?php echo $shop_on; ?>">
                    <?= Text::_('COM_TICKETSTATION_AVAILABLE_EVENTS'); ?>
                </a>
            </div>
        </section>

    <?php } else { ?>

        <div class="page-header">
            <h1 class="ts-page-title"><?= Text::_('COM_TICKETSTATION_CHECK_AND_PAY'); ?></h1>
        </div>

        <?php if ($waiting > 0) { ?>
            <div class="ts-alert ts-alert--warning ts-waitinglist-notice">
                <p><strong><?= Text::_('COM_TICKETSTATION_PLEASE_CONFIRM_WAITINGLIST_TIKETS'); ?></strong></p>
                <p><?= Text::_('COM_TICKETSTATION_PLEASE_CONFIRM_WAITINGLIST_TIKETS_DESC'); ?></p>
            </div>
        <?php } ?>

        <p class="ts-intro"><?= Text::_( 'COM_TICKETSTATION_YOUR_PAYMENT_TEXT' ); ?></p>

        <section class="ts-card ts-order">
            <h2 class="ts-order__number"><?= Text::sprintf('COM_TICKETSTATION_ORDER_NUMBER_N', '<strong class="ts-order-code">' . $ordercode . '</strong>'); ?></h2>

            <h3 class="ts-subtitle"><?= Text::_('COM_TICKETSTATION_ORDER_DETAILS'); ?></h3>

            <dl class="ts-meta">
                <?php if($this->config->show_salutation != 0 && isset($genderLabels[$this->items[0]->gender])): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_YOUR_GENDER'); ?></dt>
                    <dd><?= $genderLabels[$this->items[0]->gender]; ?></dd>
                <?php endif; ?>

                <dt><?= Text::_('COM_TICKETSTATION_NAME'); ?></dt>
                <dd><?= htmlspecialchars($this->items[0]->firstname . ' ' . $this->items[0]->name, ENT_QUOTES, 'UTF-8'); ?></dd>

                <?php if($this->config->show_address != 0 ): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_ADDRESS'); ?></dt>
                    <dd><?= htmlspecialchars($this->items[0]->address, ENT_QUOTES, 'UTF-8'); ?></dd>
                <?php endif; ?>

                <?php if($this->config->show_secondaddress != 0 ): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_ADDRESS_2'); ?></dt>
                    <dd><?= htmlspecialchars($this->items[0]->address2, ENT_QUOTES, 'UTF-8'); ?></dd>
                <?php endif; ?>

                <?php if($this->config->show_thirdaddress != 0 ): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_ADDRESS_3'); ?></dt>
                    <dd><?= htmlspecialchars($this->items[0]->address3, ENT_QUOTES, 'UTF-8'); ?></dd>
                <?php endif; ?>

                <?php if($this->config->show_zipcode != 0 ): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_YOUR_ZIPCODE'); ?></dt>
                    <dd><?= htmlspecialchars($this->items[0]->zipcode, ENT_QUOTES, 'UTF-8'); ?></dd>
                <?php endif; ?>

                <?php if($this->config->show_city != 0 ): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_CITY'); ?></dt>
                    <dd><?= htmlspecialchars($this->items[0]->city, ENT_QUOTES, 'UTF-8'); ?></dd>
                <?php endif; ?>

                <?php if($this->config->show_country != 0 && !empty($this->items[0]->country) ): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_YOUR_COUNTRY'); ?></dt>
                    <dd><?= htmlspecialchars($this->items[0]->country, ENT_QUOTES, 'UTF-8'); ?></dd>
                <?php endif; ?>

                <?php if($this->config->show_phone != 0 ): ?>
                    <dt><?= Text::_('COM_TICKETSTATION_YOUR_PHONE'); ?></dt>
                    <dd><?= htmlspecialchars($this->items[0]->phonenumber, ENT_QUOTES, 'UTF-8'); ?></dd>
                <?php endif; ?>

                <dt><?= Text::_('COM_TICKETSTATION_YOUR_EMAIL'); ?></dt>
                <dd><?= htmlspecialchars($this->items[0]->emailaddress, ENT_QUOTES, 'UTF-8'); ?></dd>
            </dl>
        </section>

        <table class="ts-table ts-summary" id="cart">
            <thead>
                <tr>
                    <th scope="col"><?= Text::_('COM_TICKETSTATION_EVENT_INFORMATION'); ?></th>
                    <th scope="col" class="ts-price"><?= Text::_('COM_TICKETSTATION_PRICE'); ?></th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($this->items as $row) { ?>

                    <tr id="row-<?= $row->orderid; ?>" class="ts-summary__item">
                        <td>
                            <span class="ts-summary__name">
                                <?= $row->eventname; ?> - <?= $row->ticketname; ?>

                                <?php if ($row->requires_seat == '1') { ?>

                                    <?php $checkrefresh = checkSeat($row->orderid, $this->coords); ?>

                                    <?php if (strpos($checkrefresh, 'Array') !== false) { ?>
                                        <?= '<script>parent.window.location.reload(true);</script>'; ?>
                                    <?php } ?>

                                    <?= ' - '.Text::_( 'COM_TICKETSTATION_SEATNUMBER' ).': '.checkSeat($row->orderid, $this->coords);?>

                                <?php } ?>
                            </span>

                            <span class="ts-summary__date"><?= Text::_( 'COM_TICKETSTATION_DATE' ); ?>: <?= date ($this->config->dateformat, strtotime($row->startdate)); ?></span>
                        </td>
                        <td class="ts-price"><?= (new TicketstationFunctions)->showprice($this->config->priceformat ,$row->ticketprice,$this->config->valuta); ?></td>
                    </tr>

                <?php } ?>
            </tbody>

            <tfoot>
                <tr class="ts-summary__subtotal">
                    <th scope="row"><?= Text::_('COM_TICKETSTATION_SUBTOTAL'); ?></th>
                    <td class="ts-price"><?= (new TicketstationFunctions)->showprice($this->config->priceformat , ($ordertotal-$fees)+$discount, $this->config->valuta); ?></td>
                </tr>

                <?php if($discount != 0) { ?>
                    <tr class="ts-summary__discount">
                        <th scope="row"><?= Text::_('COM_TICKETSTATION_DISCOUNT'); ?><?php if ($this->items[0]->discount_type == 1):?> (<?= $this->items[0]->discount_amount;?>%)<?php endif; ?></th>
                        <td class="ts-price">- <?= (new TicketstationFunctions)->showprice($this->config->priceformat , $discount, $this->config->valuta); ?></td>
                    </tr>
                <?php } ?>

                <?php if ($this->config->variable_transcosts != 2) { ?>
                    <tr class="ts-summary__fees">
                        <th scope="row"><?= Text::_('COM_TICKETSTATION_FEES'); ?><?php if ($this->config->variable_transcosts == '1') { ?> (<?= $this->config->transcosts ?>%)<?php } ?></th>
                        <td class="ts-price"><?= (new TicketstationFunctions)->showprice($this->config->priceformat , $fees, $this->config->valuta); ?></td>
                    </tr>
                <?php } ?>

                <tr class="ts-summary__total">
                    <th scope="row"><?= Text::_('COM_TICKETSTATION_ORDERTOTAL'); ?></th>
                    <td class="ts-price"><?= (new TicketstationFunctions)->showprice($this->config->priceformat , $ordertotal, $this->config->valuta); ?></td>
                </tr>
            </tfoot>
        </table>

        <?php if (count($termsLinks) == 2) { ?>
            <p class="ts-terms"><?= Text::sprintf('COM_TICKETSTATION_AGREE_TO_TERMS', $termsLinks[0], $termsLinks[1]); ?></p>
        <?php } elseif (count($termsLinks) == 1) { ?>
            <p class="ts-terms"><?= Text::sprintf('COM_TICKETSTATION_AGREE_TO_TERMS_SINGLE', $termsLinks[0]); ?></p>
        <?php } ?>

        <?php if ($ordertotal == 0) { ?>
            <p class="ts-note"><?= Text::sprintf('COM_TICKETSTATION_ZERO_TOTAL', (new TicketstationFunctions)->showprice($this->config->priceformat, 0, $this->config->valuta)); ?></p>
        <?php } ?>

        <form action="index.php" method="POST" name="adminForm" id="adminForm" class="ts-actions">

            <a class="ts-btn ts-btn--secondary ts-btn--back" href="<?php echo $gotocheckout; ?>">
                <?= Text::_('COM_TICKETSTATION_BACK'); ?>
            </a>

            <?php if (($this->mollieconfig->bypass_mode == 0) && ($ordertotal > 0)) { ?>
                <button class="ts-btn ts-btn--primary ts-btn--next" id="payment_button" type="submit"><?= Text::_( 'COM_TICKETSTATION_MOLLIE_MAKE_PAYMENT' )?></button>
            <?php } else { ?>
                <button class="ts-btn ts-btn--primary ts-btn--next" id="order_button" type="submit"><?= Text::_('COM_TICKETSTATION_PLACE_ORDER'); ?></button>
            <?php } ?>

            <input type="hidden" name="option" value="com_ticketstation" />
            <input type="hidden" name="controller" value="payment" />
            <input type="hidden" name="task" value="makepayment"/>
            <input type="hidden" name="ordercode" value="<?= $ordercode; ?>"/>
            <?php echo HTMLHelper::_('form.token'); ?>

        </form>

    <?php } ?>

</div>


<?php function checkSeat($value, $seat)
{

    for ($i = 0, $n = count($seat); $i < $n; $i++)
    {

        if ($value == $seat[$i]->orderid)
        {
            if ($seat[$i]->row_name != '')
            {
                $seat_number = $seat[$i]->row_name . $seat[$i]->seatid;
            }
            else
            {
                $seat_number = $seat[$i]->seatid;
            }
        }
    }

    return $seat_number;
}

?>
