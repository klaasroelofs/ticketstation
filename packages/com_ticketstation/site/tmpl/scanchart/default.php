<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatChart;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_SCANCHART_TITLE') . ' - ' . $app->get('sitename'));

$document->addStyleSheet( 'components/com_ticketstation/assets/css/scanner.css' );
HTMLHelper::_('jquery.framework');

## The chart: canvas, background image and shapes; it scales with the screen (SeatChart).
SeatChart::loadAssets();
$chartOwner      = (int) ($this->items[0]->chart_ticketid ?? 0);
$chartSettings   = SeatChart::settings($chartOwner);
$chartBackground = SeatChart::background($chartSettings, $chartOwner);
$chartShapes     = SeatChart::shapes($chartSettings);
$chartCanvas     = SeatChart::canvas($chartSettings, $this->items, $chartBackground, $chartShapes);

$itemid = TicketstationFunctions::getSiteItemid();
$linkback = Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : ''));

?>

<div class="row ticketstation">

    <div class="page-header">
        <h2 style="text-transform:uppercase;"><strong><?= Text::_('COM_TICKETSTATION_SCANCHART_TITLE'); ?></strong></h2>
        <h3><?= $this->items[0]->eventcode; ?> | <?= $this->items[0]->ticketname; ?></h3>
    </div>

    <div class="row">
        <div>

            <a class="btn btn-primary pull-left" onClick="location.href='<?= $linkback; ?>'">
                <span><?= Text::_('COM_TICKETSTATION_BACK'); ?></span>
            </a>

        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <?php echo SeatChart::open($chartCanvas, $chartBackground, $chartSettings, $chartShapes, $this->items); ?>

                <?php
                foreach ($this->items as $row) {

                    if ($row->blocked > 0 && $row->orderid == 0){
                        ## Blocked seat: grey
                        $style = 'color:#fff; background-color:#888888;';
                    }elseif ($row->booked > 0){
                        ## Sold seat: red, green once scanned
                        $style = 'color:#fff; background-color:#' . ($row->scanned > 0 ? '198d02' : 'ff0000') . ';';
                    }else{
                        ## Free seat
                        $style = 'color:#000; background-color:#' . SeatChart::hex($row->background_color, 'e1fdda') . ';';
                    }

                    $label = (int) $row->type === 1 ? $row->row_name . $row->seatid : $row->ticketname;

                    echo '<div id="seat-' . (int) $row->id . '" class="seat-element" style="cursor:default; border-color:#000; ' . SeatChart::seatStyle($row, $chartCanvas) . $style . '">'
                        . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</div>';
                }
                ?>

            <?php echo SeatChart::close(); ?>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div style="margin-top:25px;">

                <?php
                $countFree = 0;
                $countSold = 0;
                $countScanned = 0;
                $countNotScanned = 0;

                foreach($this->items as $item) {
                    if ($item->booked == '0') {
                        $countFree++;
                    }
                    ## A blocked seat isn't sold, so it doesn't count as not scanned either.
                    if ($item->booked == '1' && ($item->blocked != '1' || $item->orderid > 0)) {
                        $countSold++;
                    }
                    if ($item->scanned == '1') {
                        $countScanned++;
                    }
                }
                $countNotScanned = $countSold - $countScanned;
                ?>

                <div class="ticketmaster_upcoming_event">
                    <div class="ticketmaster_upcoming_event_heading" style="padding:7px 25px;">
                        <h3>
                            <strong><?= Text::_('COM_TICKETSTATION_TOTALS'); ?></strong>
                        </h3>
                    </div>
                    <div class="ticketmaster_upcoming_event_content">
                        <div id="totals" style="margin-bottom:10px;">
                            <table width="100%">
                                <tr>
                                    <td style="font-size:11px; text-align:center; border:1px solid #000 !important; background-color:#FFF; color:#000; width: 25px; height:25px;padding: 5px 0px;"><?php echo $countFree ?></td>
                                    <td>&nbsp;&nbsp;&nbsp;= <?= Text::_('COM_TICKETSTATION_SEATS_FREE'); ?></td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr><tr>
                                    <td style="font-size:11px; text-align:center; border:1px solid #000 !important; background-color:#FF0000; color:#fff; width: 25px; height:25px;padding: 5px 0px;"><?php echo $countSold ?></td>
                                    <td>&nbsp;&nbsp;&nbsp;= <?= Text::_('COM_TICKETSTATION_SEATS_SOLD'); ?></td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr>
                                <tr>
                                    <td style="font-size:11px; text-align:center; border:1px solid #000 !important; background-color:#198d02 ; color:#fff; width: 25px; height:25px;padding: 5px 0px;"><?php echo $countScanned ?></td>
                                    <td>&nbsp;&nbsp;&nbsp;= <?= Text::_('COM_TICKETSTATION_SEATS_SCANNED'); ?></td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr>
                                <tr>
                                    <td colspan="2"><span class="label-notscanned"><?= Text::_('COM_TICKETSTATION_SEATS_NOT_SCANNED'); ?>:  <?php echo $countNotScanned ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <div style="margin-bottom: 25px;text-align: center;" class="refreshdiv">
                <h4 style="margin-bottom:8px;font-weight:bold;"><?= Text::_('COM_TICKETSTATION_AUTO_REFRESH'); ?>:</h4>
                <div>
                    <div class="btn btn-primary startrefresh"><?= Text::_('COM_TICKETSTATION_START'); ?></div>
                    <div class="btn btn-primary stoprefresh"><?= Text::_('COM_TICKETSTATION_STOP'); ?></div>
                </div>
                <div style="padding:15px 0 15px 0;"><span class="refreshmsg" style="opacity:0.25;font-weight:bold;padding:5px;border:1px solid #555;border-radius: 3px;background-color:#fff;color:#555;"><?= Text::_('COM_TICKETSTATION_ACTIVE'); ?></span></div>
            </div>
        </div>
    </div>

</div>

<script>

    jQuery(function ($) {

        // Auto refresh scan statistics
        var glassbox = false;
        var totals = false;

        function changeShadow(){
            $('.refreshmsg').css({'box-shadow': 'rgb(85, 85, 85) 2px 2px'});
        }
        function changeGreen(){
            $('.refreshmsg').css({'border-color': '#198d02 ', 'color': '#198d02', 'box-shadow': 'rgb(85, 85, 85) 2px 2px'});
        }
        function changeGrey(){
            $('.refreshmsg').css({'border-color': '#555', 'color': '#555', 'box-shadow': 'rgb(85, 85, 85) 0px 0px'});
        }

        $('.startrefresh').click(function(){
            setTimeout(changeShadow, 500);
            $('.refreshmsg').fadeTo(500, 1);
            setTimeout(changeGreen, 2000);
            if (glassbox === false) {
                glassbox = setInterval(function(){
                    $('#glassbox').load(document.URL + ' #glassbox > *')
                }, 3000);
            }
            if (totals === false) {
                totals = setInterval(function(){
                    $('#totals').load(document.URL + ' #totals *')
                }, 3000)
            }
        });
        $('.stoprefresh').click(function(){
            clearInterval(glassbox);
            glassbox = false;
            clearInterval(totals);
            totals = false;
            $('.refreshmsg').css({'border-color': '#ff0000', 'color': '#ff0000'}).delay(1000).fadeTo(500, 0.25);
            setTimeout(changeGrey, 1500);
        });

    });


</script>