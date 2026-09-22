<?php

use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
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
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_TICKETS_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');
$wa->registerAndUseStyle('searchtools', Uri::root() . 'media/templates/administrator/atum/css/system/searchtools/searchtools.css');

$sold_tickets = array();
for ($i = 0; $i < count($this->sold); $i++)
{
    $row = $this->sold[$i];
    $sold_tickets[$row->ticketid] = $row->soldtickets;
}
?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=Tickets'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <div class="js-stools" role="search">
                    <div class="js-stools-container-bar">
                        <div class="btn-toolbar">

                            <div class="ordering-select">
                                <div class="js-stools-field-list">
                                    <span class="visually-hidden">
                                    </span>
                                    <?= $this->lists['filter_state']; ?>
                                </div>
                                <div class="js-stools-field-list">
                                    <span class="visually-hidden">
                                    </span>
                                    <?= $this->lists['eventid']; ?>
                                </div>
                                <div class="js-stools-field-list">
                                    <span class="visually-hidden">
                                    </span>
                                    <?= $this->lists['venue']; ?>
                                </div>
                            </div>
                            <div class="filter-search-actions btn-group">
                                <button type="button" class="filter-search-actions__button btn btn-primary js-stools-btn-clear" onclick="document.getElementById('filter_state').value='3';document.getElementById('filter_ordering_t').value='0';document.getElementById('filter_ordering_venue').value='0';this.form.submit();">
                                    Clear
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="Check All Items" onclick="Joomla.checkAll(this)" data-original-title="Check All">
                            </td>
                            <th scope="col" class="w-1 text-center"><?= Text::_( 'COM_TICKETSTATION_PUBLISHING_STATE' ); ?></th>
                            <th scope="col" class="w-15"><?= Text::_( 'COM_TICKETSTATION_TICKETNAME' ); ?></th>
                            <th scope="col" class="w-10 d-none d-md-table-cell"><?= Text::_( 'COM_TICKETSTATION_DATE' ); ?></th>
                            <th scope="col" class="w-6 d-none d-lg-table-cell"><?= Text::_( 'COM_TICKETSTATION_START_EVENT' ); ?></th>
                            <th scope="col" class="w-10 d-none d-lg-table-cell"><?= Text::_( 'COM_TICKETSTATION_VENUE' ); ?></th>
                            <th scope="col" class="w-10 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_PRICE' ); ?></th>
                            <th scope="col" class="w-1 d-none d-lg-table-cell text-center"></th>
                            <th scope="col" class="w-1 text-center"><?= Text::_( 'COM_TICKETSTATION_AVAILABLE' ); ?></th>
                            <th scope="col" class="w-1 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_SALESSTOP' ); ?></th>
                        </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                    ## Give give $row the this->item[$i]
                    $row        = $this->items[$i];
                    $published  = HTMLHelper::_('grid.published', $row, $i);
                    $checked    = HTMLHelper::_('grid.id', $i, $row->ticketid );
                    $link       = 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid='.$row->ticketid;
                    $charts     = 'index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid='.$row->ticketid;

                    if (!empty($sold_tickets[$row->ticketid])) {
                        $availabletickets = $row->starting_total_tickets - $sold_tickets[$row->ticketid];
                    } else {
                        $availabletickets = $row->starting_total_tickets;
                    }

                    $start_time = date($this->config->time_format, strtotime($row->startdate));

                    ?>
                    <tr class="row<?= $i;?>">
                        <td class="text-center"><?= $checked; ?></td>
                        <td class="text-center">
                            <?php
                            $options = [
                                'id' => 'state-' . $row->ticketid
                            ];
                            echo (new PublishedButton)->render((int) $row->published, $i, $options);
                            ?>
                        </td>
                        <td>
                            <strong><?= $row->eventname; ?></strong><br />
                            <a href="<?= $link;?>"> <?= $row->ticketname; ?></a> <small>(<?= $row->ticketcode; ?>)</small>
                        </td>
                        <td class="d-none d-md-table-cell">
                            <?= date($this->config->dateformat, strtotime($row->startdate)); ?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?= $start_time; ?>
                        </td>
                        <td class="d-none d-lg-table-cell">
                            <?= $row->venue; ?> - <?= $row->city; ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-center">
                            <?= TicketstationFunctions::showprice($this->config->priceformat ,$row->ticketprice, $this->config->valuta); ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-center">
                            <?php if ($row->show_seatplans == 1) { ?>
                                <a href="<?= $charts; ?>" title="<?= $row->ticketname; ?>" class="btn btn-primary">
                                    <?= Text::_( 'COM_TICKETSTATION_SEATCHARTS' ); ?>
                                </a>
                            <?php } ?>
                        </td>
                        <td class="text-center">
                            <?php if ($availabletickets < 25 && $availabletickets > 5) { ?>
                                <div><span class="label badge bg-warning"><?php echo $availabletickets; ?> / <?php echo $row->starting_total_tickets; ?></span></div>
                            <?php } else if ($availabletickets <= 5) { ?>
                                <div><span class="label badge bg-danger"><?php echo $availabletickets; ?> / <?php echo $row->starting_total_tickets; ?></span></div>
                            <?php }else{ ?>
                                <div><span class="label badge bg-success"><?php echo $availabletickets; ?> / <?php echo $row->starting_total_tickets; ?></span></div>
                            <?php } ?>
                        </td>
                        <td class="d-none d-lg-table-cell text-center">
                            <?php  if ($row->use_sale_stop == 1){ ?>
                                <span class="badge badge bg-success" title="<?= Text::_( 'COM_TICKETSTATION_SALESSTOP_TURNED_ON' ); ?> <?= date ($this->config->dateformat.' '.$this->config->time_format, strtotime($row->sale_stop)); ?>">
                                    <?= Text::_('COM_TICKETSTATION_YES'); ?>
                                </span>
                            <?php }else{ ?>
                                <span class="badge badge bg-danger" title="<?= Text::_( 'COM_TICKETSTATION_SALESSTOP_TURNED_OFF' ); ?>">
                                    <?= Text::_('COM_TICKETSTATION_NO'); ?>
                                </span>
                            <?php } ?>
                        </td>
                    </tr>
                        <?php

                        for ($i2 = 0, $n2 = count($this->childs); $i2 < $n2; $i2++ ){

                        ## Give give $row the this->item[$i]
                        $second     = $this->childs[$i2];
                        $publishing = HTMLHelper::_('grid.published', $second, $i2 );
                        $checking   = HTMLHelper::_('grid.id', 1000 + $i2, $second->ticketid );
                        $link       = 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid='.$second->ticketid;

                        $start_time = date ($this->config->time_format, strtotime($second->startdate));

                        if ($row->ticketid == $second->parent) { ?>
                        <tr class="row<?= $i;?>.<?= $i2;?>">
                            <td class="text-center"><?= $checking; ?></td>
                            <td class="text-center">
                                <?php
                                $options = [
                                    'id' => 'state-' . $second->ticketid
                                ];
                                echo (new PublishedButton)->render((int) $second->published, 1000 + $i2, $options);
                                ?>
                            </td>
                            <td>
                                <strong><?= $row->eventname; ?></strong><br />
                                <?= $row->ticketname; ?><br />
                                <a href="<?= $link;?>"> <?= $second->ticketname; ?></a> <small>(<?= $second->ticketcode; ?>)</small>
                            </td>
                            <td class="d-none d-md-table-cell">
                                <?= date($this->config->dateformat, strtotime($second->startdate)); ?>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <?= $start_time; ?>
                            </td>
                            <td class="d-none d-lg-table-cell">
                                <strong><?= $second->venue; ?> - <?= $second->city; ?></strong>
                            </td>
                            <td class="d-none d-lg-table-cell text-center">
                                <?= TicketstationFunctions::showprice($this->config->priceformat, $second->ticketprice, $this->config->valuta); ?>
                            </td>
                            <td class="d-none d-lg-table-cell">
                            </td>
                            <td class="text-center">
                                <?php if ($second->counter_choice == 1) { ?>
                                    <?php if ($second->totaltickets < 50 && $second->totaltickets > 25) { ?>
                                        <span class="badge badge-warning"><?= $second->totaltickets; ?> / <?= $second->starting_total_tickets; ?></span>
                                    <?php } else if ($second->totaltickets <= 25) { ?>
                                        <span class="badge badge-important"><?= $second->totaltickets; ?> / <?= $second->starting_total_tickets; ?></span>
                                    <?php }else{ ?>
                                        <span class="badge badge-success"><?= $second->totaltickets; ?> / <?= $second->starting_total_tickets; ?></span>
                                    <?php } ?>
                                <?php }else{ ?>
                                    <span title="<?= Text::_( 'COM_TICKETSTATION_USING_PARENT_COUNTER' ); ?>" class="fa fa-arrow-up" ></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                            </td>
                        </tr>
                        <?php } ?>
                        <?php } ?>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>

    <table width="100%" align="center" class="adminlist">
        <tfoot>
        <tr>
            <td colspan="7"><div align="center"><?= $this->pagination->getListFooter(); ?></div></td>
        </tr>
        </tfoot>
    </table>

    <input name="option" type="hidden" value="com_ticketstation"/>
    <input name="controller" type="hidden" value="tickets"/>
    <input name="task" type="hidden" value=""/>
    <input name="boxchecked" type="hidden" value="0"/>
    <input type="hidden" name="filter_order" value="ordering" />
    <input type="hidden" name="filter_order_Dir" value="<?= $this->lists['order_Dir']; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>
