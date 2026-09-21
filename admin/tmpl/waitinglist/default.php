<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_WAITINGLIST') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=waitinglist'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="Check All Items" onclick="Joomla.checkAll(this)" data-original-title="Check All">
                            </td>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_EVENT' ); ?></th>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_TICKET' ); ?></th>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_CLIENT' ); ?></th>
                            <th scope="col" class="w-10 text-center"><?php echo Text::_( 'COM_TICKETSTATION_WAITINGLIST_ORDER' ); ?></th>
                            <th scope="col" class="w-10 text-center"><?php echo Text::_( 'COM_TICKETSTATION_CONFIRMED' ); ?></th>
                            <th scope="col" class="w-10 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_COUPON_ADDED' ); ?></th>
                        </tr>
                    </thead>
                    <?php if (count($this->items) == 0) { ?>
                        <tr>
                            <td colspan="7" class="text-center"><?php echo Text::_( 'COM_TICKETSTATION_NO_ITEMS_FOUND' ); ?></td>
                        </tr>
                    <?php } ?>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        $row     = $this->items[$i];
                        $checked = HTMLHelper::_('grid.id', $i, $row->id);

                        if ($row->client_name != '' || $row->client_firstname != '') {
                            $client = trim($row->client_firstname . ' ' . $row->client_name);
                            if ($row->client_email != '') {
                                $client .= ' (' . $row->client_email . ')';
                            }
                        } else {
                            $client = Text::_( 'COM_TICKETSTATION_WAITINGLIST_NO_CLIENT_YET' ) . ' - ' . $row->ip_address;
                        }

                        ?>
                        <tr class="row<?= $i;?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td><?php echo htmlspecialchars($row->eventname, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($row->ticketname, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($client, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td class="text-center"><?php echo (int) $row->ordercode; ?></td>
                            <td class="text-center">
                                <?php if ($row->confirmed == 1) { ?>
                                    <span class="badge bg-success"><?php echo Text::_( 'JYES' ); ?></span>
                                <?php } else { ?>
                                    <span class="badge bg-warning"><?php echo Text::_( 'JNO' ); ?></span>
                                <?php } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center"><?php echo date($this->config->dateformat . ' H:i', strtotime($row->date_added)); ?></td>
                        </tr>
                    <?php }  ?>
                </table>
            </div>
        </div>
    </div>

    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="waitinglist"/>
    <input name = "task" type="hidden" value="" />
    <input name = "boxchecked" type="hidden" value="0"/>
    <input name = "limitstart" type="hidden" value="<?php echo $this->pagination->limitstart; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>

<table width="100%" border="0" align="center" cellpadding="1" cellspacing="1">
    <tr>
        <td>
            <div align="center"><?php echo $this->pagination->getPagesLinks(); ?></div>
        </td>
    </tr>
</table>
