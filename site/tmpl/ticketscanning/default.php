<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
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

$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( 'Ticket Scanning' . ' - ' . $app->get('sitename'));

$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');
$document->addScript('components/com_ticketstation/assets/javascripts/showLogout.js');

?>

<div class="row ticketstation">

    <div class="page-header">
        <h1>Ticket scanning</h1>
    </div>

    <div>
        <div style="max-width:750px;margin-left:auto;margin-right:auto;display: block;">
            <p>Selecteer het <?php echo count($this->events)>0?'<b>evenement</b>':'';?> <?php echo count($this->events)>0&&count($this->tickets)>0?'of':'';?> <?php echo count($this->tickets)>0?'<b>ticket</b>':'';?> waarvoor je wilt scannen.</p>
        </div>
        <div style="margin-bottom:25px;">
            <div class="userinfo" style="display: block;margin: 0 auto;">
                <i class="bi bi-shield-lock"></i> Je bent ingelogd als <strong><?php echo $this->user->name; ?></strong>
                <span class="userinfo-icon"><i id="arrow" class="bi bi-chevron-down"></i></span>
            </div>
            <div class="userinfo-logout" style="display: none;margin: 0 auto;">
                <form action="<?php echo Route::_('index.php', true); ?>" method="post" id="login-form">

                    <div>
                        <input type="submit" name="Submit" class="btn btn-danger" value="<?php echo Text::_('JLOGOUT'); ?>" />
                        <input type="hidden" name="option" value="com_users" />
                        <input type="hidden" name="task" value="user.logout" />
                        <input type="hidden" name="return" value="<?php echo $return; ?>" />
                        <?php echo HTMLHelper::_('form.token'); ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

	<?php if ((count($this->events) == 0) && (count($this->tickets) == 0))  {?>

        <div class="col-12">
            <div class="ticketmaster_upcoming_event">
                <div class="ticketmaster_upcoming_event_heading" style="padding: 7px 25px;">
                    <h3><strong>Geen evenementen!</strong></h3>
                </div>
                <div class="ticketmaster_upcoming_event_content" style="padding: 7px 25px;">
                    <p><strong>Er zijn op dit moment geen evenementen en/of tickets aan je toegewezen.</strong></p>
                </div>
            </div>
        </div>

	<?php } else { ?>

		<?php if (count($this->events) > 0) { ?>
            <div class="col-12">
                <div class="ticketmaster_upcoming_event">
                    <div class="ticketmaster_upcoming_event_heading" style="padding: 7px 25px;">
                        <h3><strong>Evenementen</strong></h3>
                    </div>
                    <div class="ticketmaster_upcoming_event_content">
                        <?php foreach ($this->events as $event) { ?>

                            <?php $link = Route::_('index.php?option=com_ticketstation&view=ticketscanner&tmpl=component&eventid='.$event->eventid); ?>

                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="ticketmaster_upcoming_ticket">
                                        <a href="<?php echo $link; ?>">
                                            <span class="ticketmaster_upcoming_ticketlink"></span>
                                        </a>
                                        <div class="ticketmaster_upcoming_ticket_heading" style="border-radius: 10px;">
                                            <h4 style="margin-left: 10px;"><strong><?php echo $event->eventname; ?> (<?php echo $event->eventcode; ?>)</strong></h4>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        <?php } ?>
                    </div>
                </div>
            </div>
		<?php } ?>

		<?php if (count($this->tickets) > 0) { ?>
            <div class="col-12">
                <div class="ticketmaster_upcoming_event">
                    <div class="ticketmaster_upcoming_event_heading" style="padding: 7px 25px;">
                        <h3><strong>Tickets</strong></h3>
                    </div>
                    <div class="ticketmaster_upcoming_event_content">
                        <?php foreach ($this->tickets as $ticket) { ?>

                            <?php $scanlink = Route::_('index.php?option=com_ticketstation&view=ticketscanner&tmpl=component&ticketid='.$ticket->ticketid); ?>
                            <?php $chartlink = Route::_('index.php?option=com_ticketstation&view=scanchart&id='.$ticket->ticketid); ?>

                            <?php if ($ticket->show_seatplans == 1) { ?>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="ticketmaster_upcoming_ticket">
                                            <a href="<?php echo $scanlink; ?>">
                                                <span class="ticketmaster_upcoming_ticketlink"></span>
                                            </a>
                                            <div class="ticketmaster_upcoming_ticket_heading" style="border-radius: 10px;">
                                                <h4 style="margin-left: 10px;"><strong><?php echo $ticket->eventcode; ?> | <?php echo $ticket->ticketname; ?></strong></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-none d-md-block col-md-4">
                                        <div class="ticketmaster_upcoming_ticket">
                                            <a href="<?php echo $chartlink; ?>">
                                                <span class="ticketmaster_upcoming_ticketlink"></span>
                                            </a>
                                            <div class="ticketmaster_upcoming_ticket_heading" style="border-radius: 10px;">
                                                <h4 style="text-align:center;"><strong>Kaart</strong></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php } else { ?>

                                <div class="row">
                                    <div class="col-xs-12">
                                        <div class="ticketmaster_upcoming_ticket">
                                            <a href="<?php echo $scanlink; ?>">
                                                <span class="ticketmaster_upcoming_ticketlink"></span>
                                            </a>
                                            <div class="ticketmaster_upcoming_ticket_heading" style="border-radius: 10px;">
                                                <h4 style="margin-left: 10px;"><strong><?php echo $ticket->eventcode; ?> | <?php echo $ticket->ticketname; ?></strong></h4>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            <?php } ?>

                        <?php } ?>
                    </div>
                </div>
            </div>
		<?php } ?>

	<?php } ?>

</div>