<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Event\Event;
use Ticketstation\Component\Ticketstation\Administrator\Helper\User;

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
$document->setTitle( 'Bestelgegevens - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');

## Redirection link in JRoute:
$gotocart = Route::_('index.php?view=cart');

## Getting the userinfo
$user = new User();
$info = $user->getClientByOrdercode($ordercode);

##Captcha -invisible
//PluginHelper::importPlugin('captcha', 'recaptcha_invisible');
//$dispatcher = JDispatcher::getInstance();
//$dispatcher->trigger('onInit','jform_captcha');

//$plugin = JPluginHelper::getPlugin('captcha', 'recaptcha_invisible');
//$params = new JRegistry($plugin->params);

//$dispatcher = Factory::getApplication()->getDispatcher();
//$event = new Event('onInit', 'jform_captcha');
//$res = $dispatcher->dispatch('onInit', $event);

?>

<script language="javascript">

    jQuery(document).ready(function() {

        jQuery('head').append("<style>ul.checkout-bar li.previous:after {width:100%;} ul.checkout-bar li.active:before {background: #BB2721;} ul.checkout-bar li.active {color: #BB2721;}</style>");

    });

</script>

<div class="row ticketstation">
    <div class="col-12">
        <div class="checkout-wrap">
            <ul class="checkout-bar">

                <li class="visited"><span class="progress-bar-text">Tickets kiezen</span></li>

                <li class="visited previous ">
                    <span class="progress-bar-text">Winkelmand</span>
                </li>

                <li class="active"><span class="progress-bar-text">Bestelgegevens</span></li>

                <li class="next"><span class="progress-bar-text">Betalen</span></li>

            </ul>
        </div>
    </div>
</div>

<div class="row ticketstation">
    <div class="col-xl-9">
        <h2 class="ticketmaster-header"><strong>Bestelgegevens</strong></h2>

        <div id="tm-cart-text" style="margin-bottom:15px;">
            <p><?php echo Text::_('COM_TICKETSTATION_CREATEACCOUNT_NOW2'); ?></p>
        </div>

        <div>
            <form id="general" action="index.php?option=com_ticketstation&controller=checkout" method="post" name="general">
                <div class="col-lg-6">
                    <?php if($this->config->show_salutation != 0 ): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_GENDER' ); ?>*</div>
                            <div class="ticketmaster-checkout-input"><?php echo $this->lists['gender']; ?></div>
                        </div>
                    <?php endif; ?>

                    <div class="row-fluid">
                        <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_FIRSTNAME' ); ?>*</div>
                        <div class="ticketmaster-checkout-input">
                            <input style="min-width: 50%;" name="firstname" type="text" id="firstname" class="input" value="<?php echo isset($info->firstname)?$info->firstname:null; ?>"  />
                        </div>
                    </div>

                    <div class="row-fluid">
                        <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_LASTNAME' ); ?>*</div>
                        <div class="ticketmaster-checkout-input">
                            <input style="min-width: 50%;" name="lastname" type="text" id="name" class="input" value="<?php echo isset($info->name)?$info->name:null; ?>"  />
                        </div>
                    </div>

                    <?php if($this->config->show_address != 0 ): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_ADDRESS' ); ?>*</div>
                            <div class="ticketmaster-checkout-input">
                                <input name="address" type="text" id="address" class="input" value="<?php echo isset($info->address)?$info->address:null; ?>"  />
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($this->config->show_secondaddress != 0 ): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_ADDRESS' ); ?>*</div>
                            <div class="ticketmaster-checkout-input">
                                <input name="address2" type="text" id="address2" class="input" value="<?php echo isset($info->address2)?$info->address2:null; ?>"  />
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($this->config->show_thirdaddress != 0 ): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_ADDRESS' ); ?>*</div>
                            <div class="ticketmaster-checkout-input">
                                <input name="address3" type="text" id="address3" class="input" value="<?php echo isset($info->address3)?$info->address3:null; ?>"  />
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($this->config->show_zipcode != 0 ): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_ZIPCODE' ); ?>*</div>
                            <div class="ticketmaster-checkout-input">
                                <input name="zipcode" type="text" id="zipcode" class="input" value="<?php echo isset($info->zipcode)?$info->zipcode:null; ?>"  />
                            </div>
                        </div>
                    <?php endif ?>

                    <?php if($this->config->show_city != 0): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_CITY' ); ?>*</div>
                            <div class="ticketmaster-checkout-input">
                                <input name="city" type="text" id="city" class="input" value="<?php echo isset($info->city)?$info->city:null; ?>"  />
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if($this->config->show_country != 0 ): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_COUNTRY' ); ?>*</div>
                            <div class="ticketmaster-checkout-input"><?php echo $this->lists['country']; ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if($this->config->show_phone != 0 ): ?>
                        <div class="row-fluid">
                            <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_PHONE' ); ?>*</div>
                            <div class="ticketmaster-checkout-input">
                                <input style="min-width: 50%;" name="phonenumber" type="text" class="input" value="<?php echo isset($info->phonenumber)?$info->phonenumber:null; ?>"/>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row-fluid">
                        <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_EMAIL' ); ?>*</div>
                        <div class="ticketmaster-checkout-input">
                            <input style="min-width: 50%;" name="emailaddress" type="text" class="input" value="<?php echo isset($info->emailaddress)?$info->emailaddress:null; ?>"  />
                        </div>
                    </div>

                    <div class="row-fluid">
                        <div class="ticketmaster-checkout-text"><?php echo Text::_( 'COM_TICKETSTATION_RETYPE_EMAIL' ); ?>*</div>
                        <div class="ticketmaster-checkout-input">
                            <input style="min-width: 50%;" name="email2" type="text" id="email2" class="input"  value="<?php echo isset($info->emailaddress)?$info->emailaddress:null; ?>"/>
                        </div>
                    </div>

                </div>

                <div style="margin-top: 20px;">

                    <input type="submit" value="Verder" class="btn btn-primary pull-right">

                    <a class="btn btn-primary pull-left" onclick="document.location.href='<?php echo $gotocart; ?>'">
                        <span>Terug</span>
                    </a>

                </div>

                <div style="clear:both;">&nbsp;</div>

                <input type="hidden" name="option" value="com_ticketstation" />
                <input type="hidden" name="controller" value="checkout" />
                <input type="hidden" name="task" value="save" />
                <?php echo HTMLHelper::_( 'form.token' ); ?>

            </form>

        </div>
    </div>
</div>