<?php

use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Button\PublishedButton;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_BOXOFFICE_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');
$wa->registerAndUseStyle('searchtools', Uri::root() . 'media/templates/administrator/atum/css/system/searchtools/searchtools.css')

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=boxoffice'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">

                <div class="js-stools" role="search">
                    <div class="js-stools-container-bar">
                        <div class="btn-toolbar">

                            <div class="filter-search-bar btn-group">
                                <div class="input-group">
                                    <input type="text" name="searchbox" id="searchbox" value="<?= $this->lists['search'];?>" class="form-control" aria-describedby="filter_search-desc" placeholder="<?= Text::_( 'COM_TICKETSTATION_SEARCH' ); ?>" inputmode="search">
                                    <div role="tooltip" id="filter_search-desc" class="filter-search-bar__description">
                                        <?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_SEARCH_DESC' ); ?>
                                    </div>
                                    <span class="filter-search-bar__label visually-hidden">
                                        <label id="filter_search-lbl" for="searchbox"><?= Text::_('JSEARCH_FILTER'); ?></label>
                                    </span>
                                    <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="<?= Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                                        <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>

                            <div class="ordering-select">
                                <div class="js-stools-field-list">
                                    <span class="visually-hidden">
                                    </span>
                                    <?= $this->lists['events']; ?>
                                </div>
                            </div>

                            <div class="ordering-select">
                                <div class="js-stools-field-list">
                                    <span class="visually-hidden">
                                    </span>
                                    <?= $this->lists['paid']; ?>
                                </div>
                            </div>

                            <div class="filter-search-actions btn-group">
                                <button type="button" class="filter-search-actions__button btn btn-primary js-stools-btn-clear" onclick="document.getElementById('filter_ordering_paid').value='0';
                                                                                                                                         document.getElementById('filter_ordering_event').value='0'
                                                                                                                                         document.getElementById('searchbox').value='';
                                                                                                                                         this.form.submit();">
                                    <?= Text::_('JSEARCH_FILTER_CLEAR'); ?>
                                </button>
                            </div>

                        </div>
                    </div>
                </div>
				
                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="<?= Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)">
                            </td>
                            <th scope="col" class="w-10"><?= Text::_( 'COM_TICKETSTATION_ORDER' ); ?></th>
                            <th scope="col" class="w-15"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_EVENT_TICKET_NAME' ); ?></th>
                            <th scope="col" class="w-6 d-none d-md-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_TOTAL_TICKETS_2' ); ?></th>
                            <th scope="col" class="w-3 d-none d-md-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_TOTAL_REGULAR_PRICE' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS' ); ?></th>
                            <th scope="col" class="w-1 d-none d-xl-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_BLACKLIST' ); ?></th>
                            <th scope="col" class="w-6 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_SCANNED' ); ?></th>
                            <th scope="col" class="w-3 d-none d-xl-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_DOWNLOADED' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_TOTAL_TICKET_SENT' ); ?></th>
                            <th scope="col" class="w-3 d-none d-xl-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_PDF_CREATED' ); ?></th>
                            <th scope="col" class="w-3 d-none d-xl-table-cell text-center"><?= Text::_( 'COM_TICKETSTATION_BOXOFFICE_MANUAL_CONFIRM' ); ?></th>
                        </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++)
                    {

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];
                        $published  = HTMLHelper::_('grid.published', $row, $i);
                        $checked    = HTMLHelper::_('grid.id', $i, $row->ordercode);
                        $link       = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $row->ordercode;

                        if ($row->transaction_amount != 0)
                        {
                            $orderprice = $row->transaction_amount;
                        }
                        else
                        {
                            $orderprice = $row->orderprice;
                        }

                        //Bekijken of alle tickets in de order gescand zijn
                        $scanned_in_order = 0;

                        $db = Factory::getContainer()->get('DatabaseDriver');
                        $sql = 'SELECT scanned 					
						FROM #__ticketstation_orders
						WHERE (ordercode = ' . $row->ordercode . ')';

                        $db->setQuery($sql);
                        $results = $db->loadObjectList();

                        foreach ($results as $item) {
                            if ($item->scanned == '1') {
                                $scanned_in_order++;
                            }
                        }
                        ?>

                        <tr class="row<?= $i;?>">
                            <td class="text-center"><?= $checked; ?></td>
                            <td>
                                <div align="left">
                                    <strong><a href="<?= $link;?>"><?= $row->ordercode; ?></a></strong>
                                    <?php if (!empty($row->removed_auto)) { ?>
                                        <span class="badge bg-secondary" title="<?= Text::_('COM_TICKETSTATION_ORDER_REMOVED_AUTO_NOTICE'); ?>"><?= Text::_('COM_TICKETSTATION_ORDER_REMOVED_AUTO_BADGE'); ?></span>
                                    <?php } ?>
                                    <br />
                                    <small>
                                        <?= $row->firstname; ?> <?= $row->name; ?><br/>
                                        <em>
                                            <?= Date::_($row->orderdate, $this->config->dateformat . ' ' . $this->config->time_format); ?>
                                        </em>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div align="left">
                                    <strong><?= $row->eventname; ?></strong><br/>
                                    <?= $row->ticketname; ?>
                                    <?php if ($row->remarks != '') { ?>
                                        <br /><span class="badge bg-info"><?= $row->remarks; ?></span>
                                    <?php } ?>
                                    <?php if (!empty($row->coupon)) { ?>
                                        <br/><span title="<?= $row->coupon; ?>" class="badge bg-warning"><?= Text::_( 'COM_TICKETSTATION_DISCOUNT_CAPS' ); ?></span>
                                    <?php } ?>
                                </div>
                            </td>
                            <td class="d-none d-md-table-cell text-center">
                                <?= $row->o_tickets; ?>
                            </td>
                            <td class="d-none d-md-table-cell text-center">
                                <?= ($orderprice == 0) ? '-' : $this->config->valuta . ' ' . number_format($orderprice, 2, ',', ''); ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?php if ($row->paid == 1) { ?>
                                    <span class="label badge bg-success"><?= Text::_( 'COM_TICKETSTATION_PAID' ); ?></span>
                                <?php } elseif ($row->paid == 2) { ?>
                                    <span class="label badge bg-info"><?= Text::_( 'COM_TICKETSTATION_REFUNDED' ); ?></span>
                                <?php } elseif($row->paid == 3) { ?>
                                    <span class="label badge bg-warning"><?= Text::_( 'COM_TICKETSTATION_PENDING' ); ?></span>
                                <?php } else { ?>
                                    <span class="label badge bg-danger"><?= Text::_( 'COM_TICKETSTATION_UNPAID_OVERVIEW' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-xl-table-cell text-center">
                                <?php if ($row->blacklisted == 1) { ?>
                                    <span class="label badge bg-danger"><?= Text::_( 'COM_TICKETSTATION_YES' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?php if (($scanned_in_order < $row->o_tickets) && ($scanned_in_order != 0)) { ?>
                                    <span class="label badge bg-warning"><?= Text::_( 'COM_TICKETSTATION_PARTIAL' ); ?></span>
                                <?php } elseif ($scanned_in_order == $row->o_tickets) {?>
                                    <span class="label badge bg-danger"><?= Text::_( 'COM_TICKETSTATION_YES' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-xl-table-cell text-center">
                                <?php if ($row->downloaded == 1) { ?>
                                    <span class="label badge bg-success"><?= Text::_( 'COM_TICKETSTATION_YES' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?php if ($row->pdfsent == 1) { ?>
                                    <span class="label badge bg-success"><?= Text::_( 'COM_TICKETSTATION_YES' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-xl-table-cell text-center">
                                <?php if ($row->pdfcreated == 1) { ?>
                                    <span class="label badge bg-success"><?= Text::_( 'COM_TICKETSTATION_YES' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-xl-table-cell text-center">
                                <?php if ($row->published == 1) { ?>
                                    <span class="label badge bg-success"><?= Text::_( 'COM_TICKETSTATION_YES' ); ?></span>
                                <?php } else { ?>
                                    <span class="label badge bg-danger"><?= Text::_( 'COM_TICKETSTATION_NO' ); ?></span>
                                <?php } ?>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
				
				<?php // load the pagination. ?>
				<?= $this->pagination->getListFooter(); ?>
				
				
            </div>
        </div>
    </div>


    <input name="option" type="hidden" value="com_ticketstation"/>
    <input name="controller" type="hidden" value="boxoffice"/>
    <input name="task" type="hidden" value=""/>
    <input name="boxchecked" type="hidden" value="0"/>
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>
