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
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_CUSTOMER_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');
$wa->registerAndUseStyle('searchtools', Uri::root() . 'media/templates/administrator/atum/css/system/searchtools/searchtools.css');

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=clients'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">

                <div class="js-stools" role="search">
                    <div class="js-stools-container-bar">
                        <div class="btn-toolbar">
                            <div class="filter-search-bar btn-group">
                                <div class="input-group">
                                    <input type="text" name="searchbox" id="searchbox" value="<?= $this->lists['search'];?>" class="form-control" aria-describedby="filter_search-desc" placeholder="<?= Text::_('JSEARCH_FILTER'); ?>" inputmode="search">
                                    <div role="tooltip" id="filter_search-desc" class="filter-search-bar__description">
                                        <?= Text::_('COM_TICKETSTATION_CLIENTS_SEARCH_DESC'); ?>
                                    </div>
                                    <span class="filter-search-bar__label visually-hidden">
                                        <label id="filter_search-lbl" for="searchbox"><?= Text::_('JSEARCH_FILTER'); ?></label>
                                    </span>
                                    <button type="submit" class="filter-search-bar__button btn btn-primary" aria-label="<?= Text::_('JSEARCH_FILTER_SUBMIT'); ?>">
                                        <span class="filter-search-bar__button-icon icon-search" aria-hidden="true"></span>
                                    </button>
                                </div>
                            </div>
                            <div class="filter-search-actions btn-group">
                                <button type="button" class="filter-search-actions__button btn btn-primary js-stools-btn-clear" onclick="document.getElementById('searchbox').value='';this.form.submit();">
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
                            <th scope="col" class="w-1 text-center"><?php echo Text::_( 'COM_TICKETSTATION_PUBLISHING_STATE' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_CLIENTID' ); ?></th>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_CUSTOMER_NAME' ); ?></th>
                            <th scope="col" class="d-none d-lg-table-cell"><?php echo Text::_( 'COM_TICKETSTATION_PHONENUMBER' ); ?></th>
                            <th scope="col" class="d-none d-md-table-cell"><?php echo Text::_( 'COM_TICKETSTATION_EMAILADDRESS' ); ?></th>
                        </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ) {

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];
                        $checked    = HTMLHelper::_('grid.id', $i, $row->clientid );
                        $link 		= 'index.php?option=com_ticketstation&controller=clients&task=edit&cid='.$row->clientid;

                        ?>
                        <tr class="row<?php echo $i;?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td class="text-center">
                                <?php
                                $options = [
                                    'id' => 'state-' . $row->clientid
                                ];
                                echo (new PublishedButton)->render((int) $row->published, $i, $options);
                                ?>
                            </td>
                            <td class="d-none d-lg-table-cell"><?php echo $row->clientid; ?></td>
                            <td><a href="<?php echo $link; ?>"><?php echo $row->firstname; ?> <?php echo $row->name; ?></a></td>
                            <td class="small d-none d-lg-table-cell"><?php echo $row->phonenumber; ?></td>
                            <td class="small d-none d-md-table-cell"><?php echo $row->emailaddress; ?></td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>

    <table width="100%" align="center" class="adminlist">
        <tfoot>
        <tr>
            <td colspan="7"><div align="center"><?php echo $this->pagination->getListFooter(); ?></div></td>
        </tr>
        </tfoot>
    </table>

    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="clients"/>
    <input name = "task" type="hidden" value="" />
    <input name = "boxchecked" type="hidden" value="0"/>
    <input name = "filter_order" type="hidden" value="clientid"/>
    <input name = "filter_order_Dir" type="hidden" value="ASC"/>
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>