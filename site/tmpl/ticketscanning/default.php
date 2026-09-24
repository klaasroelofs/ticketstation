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
$document->setTitle( Text::_('COM_TICKETSTATION_TICKETSCANNING_TITLE') . ' - ' . $app->get('sitename'));

$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
HTMLHelper::_('jquery.framework');
$document->addScript('components/com_ticketstation/assets/javascripts/showLogout.js');

// After logging out, come back here (which then asks to log in again).
$itemid = TicketstationFunctions::getSiteItemid();
$return = base64_encode(Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : ''), false));

## Instruction depends on what is assigned: events, tickets or both
if (count($this->events) > 0 && count($this->tickets) > 0) {
    $selectText = Text::_('COM_TICKETSTATION_TICKETSCANNING_SELECT_EVENT_OR_TICKET');
} elseif (count($this->events) > 0) {
    $selectText = Text::_('COM_TICKETSTATION_TICKETSCANNING_SELECT_EVENT');
} else {
    $selectText = Text::_('COM_TICKETSTATION_TICKETSCANNING_SELECT_TICKET');
}

?>

<div class="row ticketstation">

    <div class="page-header">
        <h1><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNING_TITLE'); ?></h1>
    </div>

    <div>
        <div style="max-width:750px;margin-left:auto;margin-right:auto;display: block;">
            <p><?php echo $selectText; ?></p>
        </div>
        <div style="margin-bottom:25px;">
            <div class="userinfo" style="display: block;margin: 0 auto;">
                <i class="bi bi-shield-lock"></i> <?php echo Text::sprintf('COM_TICKETSTATION_TICKETSCANNING_LOGGED_IN_AS', '<strong>' . $this->escape($this->user->name) . '</strong>'); ?>
                <span class="userinfo-icon"><i id="arrow" class="bi bi-chevron-down"></i></span>
            </div>
            <div class="userinfo-logout" style="display: none;margin: 0 auto;">
                <form action="<?php echo Route::_('index.php', true); ?>" method="post" id="login-form">

                    <div>
                        <input type="submit" name="Submit" class="btn btn-danger" value="<?php echo Text::_('JLOGOUT'); ?>" />
                        <input type="hidden" name="option" value="com_users" />
                        <input type="hidden" name="task" value="user.logout" />
                        <input type="hidden" name="return" value="<?php echo $this->escape($return); ?>" />
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
                    <h3><strong><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNING_NO_EVENTS'); ?></strong></h3>
                </div>
                <div class="ticketmaster_upcoming_event_content" style="padding: 7px 25px;">
                    <p><strong><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNING_NO_EVENTS_DESC'); ?></strong></p>
                </div>
            </div>
        </div>

	<?php } else { ?>

		<?php if (count($this->events) > 0) { ?>
            <div class="col-12">
                <div class="ticketmaster_upcoming_event">
                    <div class="ticketmaster_upcoming_event_heading" style="padding: 7px 25px;">
                        <h3><strong><?php echo Text::_('COM_TICKETSTATION_EVENTS'); ?></strong></h3>
                    </div>
                    <div class="ticketmaster_upcoming_event_content">
                        <?php foreach ($this->events as $event) { ?>

                            <?php $itemid = TicketstationFunctions::getSiteItemid(); ?>
                            <?php $link = Route::_('index.php?option=com_ticketstation&view=ticketscanner&tmpl=component&eventid='.$event->eventid . ($itemid ? '&Itemid=' . $itemid : '')); ?>

                            <div class="row">
                                <div class="col-xs-12">
                                    <div class="ticketmaster_upcoming_ticket">
                                        <a href="<?php echo $link; ?>">
                                            <span class="ticketmaster_upcoming_ticketlink"></span>
                                        </a>
                                        <div class="ticketmaster_upcoming_ticket_heading" style="border-radius: 10px;">
                                            <h4 style="margin-left: 10px;"><strong><?php echo $this->escape($event->eventname); ?> (<?php echo $this->escape($event->eventcode); ?>)</strong></h4>
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
                        <h3><strong><?php echo Text::_('COM_TICKETSTATION_PAGE_HEADING_TICKETS'); ?></strong></h3>
                    </div>
                    <div class="ticketmaster_upcoming_event_content">
                        <?php foreach ($this->tickets as $ticket) { ?>

                            <?php $itemid = TicketstationFunctions::getSiteItemid(); ?>
                            <?php $scanlink = Route::_('index.php?option=com_ticketstation&view=ticketscanner&tmpl=component&ticketid='.$ticket->ticketid . ($itemid ? '&Itemid=' . $itemid : '')); ?>
                            <?php $chartlink = Route::_('index.php?option=com_ticketstation&view=scanchart&id='.$ticket->ticketid . ($itemid ? '&Itemid=' . $itemid : '')); ?>

                            <?php if ($ticket->show_seatplans == 1) { ?>

                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="ticketmaster_upcoming_ticket">
                                            <a href="<?php echo $scanlink; ?>">
                                                <span class="ticketmaster_upcoming_ticketlink"></span>
                                            </a>
                                            <div class="ticketmaster_upcoming_ticket_heading" style="border-radius: 10px;">
                                                <h4 style="margin-left: 10px;"><strong><?php echo $this->escape($ticket->eventcode); ?> | <?php echo $this->escape($ticket->ticketname); ?></strong></h4>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="d-none d-md-block col-md-4">
                                        <div class="ticketmaster_upcoming_ticket">
                                            <a href="<?php echo $chartlink; ?>">
                                                <span class="ticketmaster_upcoming_ticketlink"></span>
                                            </a>
                                            <div class="ticketmaster_upcoming_ticket_heading" style="border-radius: 10px;">
                                                <h4 style="text-align:center;"><strong><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNING_SEAT_MAP'); ?></strong></h4>
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
                                                <h4 style="margin-left: 10px;"><strong><?php echo $this->escape($ticket->eventcode); ?> | <?php echo $this->escape($ticket->ticketname); ?></strong></h4>
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