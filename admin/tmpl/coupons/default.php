<?php

use Joomla\CMS\Component\ComponentHelper;
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
$document->setTitle(Text::_('COM_TICKETSTATION_COUPONS') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');
?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=coupons'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <table class="table itemList">
                    <thead>
                        <tr>
                            <td class="w-1 text-center">
                                <input class="form-check-input" type="checkbox" name="checkall-toggle" value="" title="Check All Items" onclick="Joomla.checkAll(this)" data-original-title="Check All">
                            </td>
                            <th scope="col" class="w-1 text-center"><?php echo Text::_( 'COM_TICKETSTATION_PUBLISHING_STATE' ); ?></th>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_COUPON_NAME' ); ?></th>
                            <th scope="col"><?php echo Text::_( 'COM_TICKETSTATION_COUPON_CODE' ); ?></th>
                            <th scope="col" class="w-10 d-none d-md-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_DISCOUNT' ); ?></th>
                            <th scope="col" class="w-10 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_COUPON_ADDED' ); ?></th>
                            <th scope="col" class="w-10 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_COUPON_EXPIRATION' ); ?></th>
                            <th scope="col" class="w-3 d-none d-lg-table-cell text-center"><?php echo Text::_( 'COM_TICKETSTATION_COUPON_LIMITATION' ); ?></th>
                        </tr>
                    </thead>
                    <?php

                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = $this->items[$i];
                        $published 	= HTMLHelper::_('grid.published', $row, $i );
                        $checked    = HTMLHelper::_('grid.id', $i, $row->coupon_id );
                        $link		= 'index.php?option=com_ticketstation&view=coupon&layout=edit&cid=' . $row->coupon_id;

                        ?>
                        <tr class="row<?= $i;?>">
                            <td class="text-center"><?php echo $checked; ?></td>
                            <td class="text-center">
                                <?php
                                $options = [
                                    'id' => 'state-' . $row->coupon_id
                                ];
                                echo (new PublishedButton)->render((int) $row->published, $i, $options);
                                ?>
                            </td>
                            <td><a href="<?php echo $link; ?>"><?php echo $row->coupon_name; ?></a></td>
                            <td><?php echo $row->coupon_code; ?></td>
                            <td class="small d-none d-md-table-cell text-center">
                                <?php if ($row->coupon_type == 1){
                                    echo $row->coupon_discount.'%';
                                }else{
                                    echo $this->config->valuta; ?> <?php echo number_format($row->coupon_discount, 2, ',', '');
                                } ?>
                            </td>
                            <td class="small d-none d-lg-table-cell text-center"><?php echo date ($this->config->dateformat, strtotime($row->coupon_added)); ?></td>
                            <td class="small d-none d-lg-table-cell text-center"><?php echo date ($this->config->dateformat, strtotime($row->coupon_valid_to)); ?></td>
                            <td class="small d-none d-lg-table-cell text-center">
                                <?php if ($row->coupon_limit == 0) { echo Text::_( 'COM_TICKETSTATION_UNLIMITED' ); }else{ echo $row->coupon_used.' / '.$row->coupon_limit; } ?>
                            </td>
                        </tr>
                    <?php }  ?>
                </table>
            </div>
        </div>
    </div>

    <input name = "option" type="hidden" value="com_ticketstation" />
    <input name = "controller" type="hidden" value="coupons"/>
    <input name = "task" type="hidden" value="" />
    <input name = "boxchecked" type="hidden" value="0"/>
    <input name = "limitstart" type="hidden" value="<?php echo $this->pagination->limitstart; ?>" />
</form>

<table width="100%" border="0" align="center" cellpadding="1" cellspacing="1">
    <tr>
        <td>
            <div align="center"><?php echo $this->pagination->getPagesLinks(); ?></div>
        </td>
    </tr>
</table>           
