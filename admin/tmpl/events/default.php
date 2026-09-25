<?php

use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_EVENTS_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');

for ($i = 0; $i < count($this->sold); $i++)
{
    $row = $this->sold[$i];
    $sold_tickets[$row->eventid] = $row->soldtickets;
}

$pending_tickets = array();

for ($i = 0; $i < count($this->pending); $i++)
{
    $row = $this->pending[$i];
    $pending_tickets[$row->eventid] = $row->pending_tickets;
}

$added_tickets = array();

for ($i = 0; $i < count($this->added); $i++)
{
    $row = $this->added[$i];
    $added_tickets[$row->eventid] = $row->total;
}

$unfinished_orders = array();

for ($i = 0; $i < count($this->unfinished); $i++)
{
    $row = $this->unfinished[$i];
    $unfinished_orders[$row->eventid] = $row->unfinished_orders;
}
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=Events'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="<?= Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)">
                            </td>
                            <th scope="col" class="w-1 text-center"><?php echo Text::_( 'COM_TICKETSTATION_PUBLISHING_STATE' ); ?></th>
                            <th scope="col" class="w-15"><?php echo Text::_( 'COM_TICKETSTATION_EVENTNAME' ); ?></th>
                            <th scope="col" class="w-10"><?php echo Text::_( 'COM_TICKETSTATION_EVENTDATE' ); ?></th>
                            <th scope="col" class="w-1 d-none d-md-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_AUTO_PUBLISH_TICKETS' ); ?></th>
                            <th scope="col" class="w-6 d-none d-md-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_AUTO_DATE_PUBLISH' ); ?></th>
                            <th scope="col" class="w-6 d-none d-md-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_AUTO_DATE_UNPUBLISH' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_ADDED_TICKETS' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_SOLD_TICKETS' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_PENDING_TICKETS' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_AVAILABLE_TICKETS' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_EVENT_UNFINISHED_TICKETS' ); ?></th>
                        </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++)
                    {

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];
                        $published  = HTMLHelper::_('grid.published', $row, $i);
                        $checked    = HTMLHelper::_('grid.id', $i, $row->eventid);
                        $link       = 'index.php?option=com_ticketstation&view=event&layout=edit&cid=' . $row->eventid;

                        $addedtickets = isset($added_tickets['' . $row->eventid . '']) ? $added_tickets['' . $row->eventid . ''] : 0;
                        $soldtickets = isset($sold_tickets['' . $row->eventid . '']) ? $sold_tickets['' . $row->eventid . ''] : 0;
                        $pendingtickets = isset($pending_tickets['' . $row->eventid . '']) ? $pending_tickets['' . $row->eventid . ''] : 0;

                        $available_tickets = $addedtickets - ($soldtickets + $pendingtickets);
                        $check_tickets = isset($unfinished_orders['' . $row->eventid . '']) ? $unfinished_orders['' . $row->eventid . ''] : 0;

                        $class_unfinished = null;

                        if($check_tickets <= 5)
                        {
                            $class_unfinished = '';
                        }
                        else if($check_tickets > 5 && $check_tickets < 25)
                        {
                            $class_unfinished = ' label badge bg-warning';
                        }
                        else
                        {
                            $class_unfinished = ' label badge bg-danger';
                        }

                        ?>
                        <tr class="row<?= $i;?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td class="text-center">
                                <?php
                                $options = [
                                    'id' => 'state-' . $row->eventid
                                ];
                                echo (new PublishedButton)->render((int) $row->published, $i, $options);
                                ?>
                            </td>
                            <td>
                                <a href="<?php echo $link; ?>"><?php echo $row->eventname; ?></a> <small>(<?= $row->eventcode; ?>)</small>
                            </td>
                            <td>
                                <?php echo date($this->config->dateformat, strtotime($row->eventdate)); ?>
                            </td>
                            <td class="small d-none d-md-table-cell text-center">
                                <?php if($row->automatic_change_state == 1)
                                { ?>
                                    <span class="label badge bg-success"><?= Text::_('COM_TICKETSTATION_AUTOMATIC'); ?></span>
                                <?php }
                                else
                                { ?>
                                    <span class="label badge bg-danger"><?= Text::_('COM_TICKETSTATION_MANUAL'); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-md-table-cell text-center">
                                <?php if($row->automatic_change_state == 1)
                                {
                                    echo date($this->config->dateformat . ' ' . $this->config->time_format, strtotime($row->startdate));
                                } ?>
                            </td>
                            <td class="small d-none d-md-table-cell text-center">
                                <?php if($row->automatic_change_state == 1)
                                {
                                    echo date($this->config->dateformat . ' ' . $this->config->time_format, strtotime($row->closingdate));
                                } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?= isset($added_tickets['' . $row->eventid . '']) ? $added_tickets['' . $row->eventid . ''] : 0; ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?= isset($sold_tickets['' . $row->eventid . '']) ? $sold_tickets['' . $row->eventid . ''] : 0; ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?= isset($pending_tickets['' . $row->eventid . '']) ? $pending_tickets['' . $row->eventid . ''] : 0; ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?= isset($available_tickets) ? $available_tickets : 0; ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center<?=$class_unfinished;?>">
                                <?= isset($unfinished_orders['' . $row->eventid . '']) ? $unfinished_orders['' . $row->eventid . ''] : 0; ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>

    <input name="option" type="hidden" value="com_ticketstation"/>
    <input name="controller" type="hidden" value="events"/>
    <input name="task" type="hidden" value=""/>
    <input name="boxchecked" type="hidden" value="0"/>
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>
