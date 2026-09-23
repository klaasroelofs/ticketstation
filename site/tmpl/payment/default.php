<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
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
$document->setTitle( 'Betalen - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');

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
?>

    <script language="javascript">

        jQuery(document).ready(function() {

            jQuery('head').append("<style>ul.checkout-bar li.previous:after {width:100%;} ul.checkout-bar li.complete:before {background: #BB2721;} ul.checkout-bar li.active {color: #BB2721;}</style>");

            jQuery('head').delay(1500).queue(function() {
                jQuery('head').append("<style>ul.checkout-bar li.complete:after { width:61%; }</style>");
                jQuery('head').dequeue();
            });

        });

    </script>

<div class="row ticketstation">
    <div class="col-12">

            <?php if ($count != 0) { ?>
                <div class="checkout-wrap">
                    <ul class="checkout-bar">

                        <li class="visited"><span class="progress-bar-text">Tickets kiezen</span></li>

                        <li class="visited">
                            <span class="progress-bar-text">Winkelmand</span>
                        </li>

                        <li class="visited previous">
                            <span class="progress-bar-text">Bestelgegevens</span>
                        </li>

                        <li class="active complete"><span class="progress-bar-text">Betalen</span></li>

                    </ul>
                </div>

            <?php } ?>
    </div>
</div>

<div class="row ticketstation">
    <div class="col-xl-9">

        <?php if ($count == 0 && $waiting > 0) { ?>

            <div style="min-height:250px;">
                <h2 class="ticketmaster-header"><strong><?= Text::_('COM_TICKETSTATION_WAITINGLIST_REGISTERED'); ?></strong></h2>

                <p style="margin:30px 0px;"><?= Text::_('COM_TICKETSTATION_WAITINGLIST_CHECK_MAIL'); ?></p>

                <a class="btn btn-forward-back pull-left" onClick="location.href='<?php echo $shop_on; ?>'">
                    <span><?= Text::_('COM_TICKETSTATION_AVAILABLE_EVENTS'); ?></span>
                </a>
            </div>

        <?php } elseif ($count == 0) { ?>

            <div style="min-height:250px;">
                <h2 class="ticketmaster-header"><strong><?= Text::_('COM_TICKETSTATION_YOUR_CART_EMPTY'); ?></strong></h2>

                <p style="margin:30px 0px;"><?= Text::_('COM_TICKETSTATION_GO_TO_UPCOMING'); ?></p>

                <a class="btn btn-forward-back pull-left" onClick="location.href='<?php echo $shop_on; ?>'">
                    <span><?= Text::_('COM_TICKETSTATION_AVAILABLE_EVENTS'); ?></span>
                </a>
            </div>

        <?php } else { ?>

            <?php if ($waiting > 0) { ?>

                <div class="alert">
                    <p><?= Text::_('COM_TICKETSTATION_PLEASE_CONFIRM_WAITINGLIST_TIKETS'); ?></p>
                    <p style="margin-top: 8px;"><?= Text::_('COM_TICKETSTATION_PLEASE_CONFIRM_WAITINGLIST_TIKETS_DESC'); ?></p>
                </div>

            <?php } ?>

            <?php if ($count > 0) {?>

                <h2 class="ticketmaster-header"><strong>Controleren & Betalen</strong></h2>

                <div id = "tm-cart-text">
                    <p><?= Text::_( 'COM_TICKETSTATION_YOUR_PAYMENT_TEXT' ); ?></p>
                </div>

                <div>
                    <div class="ticketmaster_event_info">
                        <div class="row-fluid">
                            <div class="span8"><h3 style="color: #008C39;"><strong>Bestelnummer <?= $ordercode; ?></strong></h3></div>
                            <div class="span8"><h4><strong>Bestelgegevens:</strong></h4></div>
                            <table style="margin-bottom:30px;">
                                <?php if($this->config->show_salutation != 0 && isset($genderLabels[$this->items[0]->gender])): ?>
                                <tr>
                                    <td style="width: 130px;font-weight:bold;">Aanhef:</td>
                                    <td><?= $genderLabels[$this->items[0]->gender]; ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="width: 130px;font-weight:bold;">Naam:</td>
                                    <td><?= $this->items[0]->firstname; ?> <?= $this->items[0]->name; ?></td>
                                </tr>
                                <?php if($this->config->show_address != 0 ): ?>
                                <tr>
                                    <td style="font-weight:bold;">Adres:</td>
                                    <td><?= htmlspecialchars($this->items[0]->address, ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($this->config->show_secondaddress != 0 ): ?>
                                <tr>
                                    <td style="font-weight:bold;">Adres 2:</td>
                                    <td><?= htmlspecialchars($this->items[0]->address2, ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($this->config->show_thirdaddress != 0 ): ?>
                                <tr>
                                    <td style="font-weight:bold;">Adres 3:</td>
                                    <td><?= htmlspecialchars($this->items[0]->address3, ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($this->config->show_zipcode != 0 ): ?>
                                <tr>
                                    <td style="font-weight:bold;">Postcode:</td>
                                    <td><?= htmlspecialchars($this->items[0]->zipcode, ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($this->config->show_city != 0 ): ?>
                                <tr>
                                    <td style="font-weight:bold;">Plaats:</td>
                                    <td><?= htmlspecialchars($this->items[0]->city, ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($this->config->show_country != 0 && !empty($this->items[0]->country) ): ?>
                                <tr>
                                    <td style="font-weight:bold;">Land:</td>
                                    <td><?= htmlspecialchars($this->items[0]->country, ENT_QUOTES, 'UTF-8'); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if($this->config->show_phone != 0 ): ?>
                                <tr>
                                    <td style="padding-right:5px;font-weight:bold;">Telefoonnummer:</td>
                                    <td><?= $this->items[0]->phonenumber; ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td style="font-weight:bold;">E-mailadres:</td>
                                    <td><?= $this->items[0]->emailaddress; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="row-fluid">
                        <div class="span8">

                            <table class="table" id="cart">

                                <?php foreach ($this->items as $row) { ?>

                                    <tr id="row-<?= $row->orderid; ?>">
                                        <td style="width: 65%;">

                                            <?= $row->eventname; ?> - <?= $row->ticketname; ?>

                                            <?php if ($row->requires_seat == '1') { ?>

                                                <?php $checkrefresh = checkSeat($row->orderid, $this->coords); ?>

                                                <?php if (strpos($checkrefresh, 'Array') !== false) { ?>
                                                    <?= '<script>parent.window.location.reload(true);</script>'; ?>
                                                <?php } ?>

                                                <?= ' - '.Text::_( 'COM_TICKETSTATION_SEATNUMBER' ).': '.checkSeat($row->orderid, $this->coords);?>

                                            <?php } ?>

                                            <br/>

                                            <?= Text::_( 'COM_TICKETSTATION_DATE' ); ?>: <?= date ($this->config->dateformat, strtotime($row->startdate)); ?>

                                        </td>
                                        <td style="width: 35%;">
                                            <div style="text-align: right;"><?= (new TicketstationFunctions)->showprice($this->config->priceformat ,$row->ticketprice,$this->config->valuta); ?></div>
                                        </td>
                                    </tr>

                                <?php } ?>

                                <tr>
                                    <td>
                                        <div style="font-weight:bold;text-align: right"><?= Text::_('COM_TICKETSTATION_SUBTOTAL'); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:bold; text-align: right;"><?= (new TicketstationFunctions)->showprice($this->config->priceformat , ($ordertotal-$fees)+$discount, $this->config->valuta); ?></div>
                                    </td>
                                </tr>
                                <?php if($discount != 0) { ?>
                                    <tr>
                                        <td>
                                            <div style="text-align: right;"><?= Text::_('COM_TICKETSTATION_DISCOUNT'); ?><?php if ($this->items[0]->discount_type == 1):?> (<?= $this->items[0]->discount_amount;?>%)<?php endif; ?></div>
                                        </td>
                                        <td>
                                            <div style="text-align: right;"><sup>-</sup>/<sub>-</sub> <?= (new TicketstationFunctions)->showprice($this->config->priceformat , $discount, $this->config->valuta); ?></div>
                                        </td>

                                    </tr>
                                <?php } ?>
                                <?php if ($this->config->variable_transcosts != 2) { ?>
                                <tr>
                                    <td>
                                        <div style="text-align: right;"><?= Text::_('COM_TICKETSTATION_FEES'); ?><?php if ($this->config->variable_transcosts == '1') { ?> (<?= $this->config->transcosts ?>%) <?php } ?></div>
                                    </td>
                                    <td>
                                        <div style="text-align: right;"><?= (new TicketstationFunctions)->showprice($this->config->priceformat , $fees, $this->config->valuta); ?></div>
                                    </td>
                                </tr>
                                <?php } ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:bold;text-align: right"><?= Text::_('COM_TICKETSTATION_ORDERTOTAL'); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:bold; text-align: right;"><?= (new TicketstationFunctions)->showprice($this->config->priceformat , $ordertotal, $this->config->valuta); ?></div>
                                    </td>
                                </tr>
                            </table>
                        </div>

                        <div>
                            <div style="margin-bottom:20px;">
                                Door op 'Betalen' te klikken ga je akkoord met onze <a style="font-weight:bold;" href="downloads/Ticketshop-AlgemeneVoorwaarden.pdf" target="_blank">Algemene Voorwaarden</a> en <a style="font-weight:bold;" href="downloads/Ticketshop-Privacyverklaring.pdf" target="_blank">Privacyverklaring</a>.
                            </div>
                            <div>

                                <form action = "index.php" method="POST" name="adminForm" id="adminForm">

                                    <?php if ($ordertotal == 0) { ?>
                                        <div>Het totale bedrag is € 0,-. Klik op onderstaande knop om je bestelling af te ronden.</div>
                                    <?php } ?>


                                    <a class="btn btn-primary pull-left" onclick="document.location.href='<?php echo $gotocheckout; ?>'">
                                        <span>Terug</span>
                                    </a>

                                    <?php if (($this->mollieconfig->bypass_mode == 0) && ($ordertotal > 0)) { ?>
                                        <button class="btn btn-primary pull-right" id="payment_button" type="submit"><?= Text::_( 'COM_TICKETSTATION_MOLLIE_MAKE_PAYMENT' )?></button>
                                    <?php } else { ?>
                                        <button class="btn btn-primary pull-right" id="order_button" type="submit">Bestellen</button>
                                    <?php } ?>

                                    <input type="hidden" name="option" value="com_ticketstation" />
                                    <input type="hidden" name="controller" value="payment" />
                                    <input type="hidden" name="task" value="makepayment"/>
                                    <input type="hidden" name="ordercode" value="<?= $ordercode; ?>"/>
                                    <?php echo HTMLHelper::_('form.token'); ?>

                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        <?php } ?>
    </div>
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