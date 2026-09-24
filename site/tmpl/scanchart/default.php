<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
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
$document->setTitle( 'Scan Chart' . ' - ' . $app->get('sitename'));

$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );
$document->addScript('https://code.jquery.com/jquery-3.7.1.js');
$document->addScript('https://code.jquery.com/ui/1.13.2/jquery-ui.js');

## The image of the seat chart
$seatchart_png = '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->items[0]->chart_ticketid.'.png';
$image_png = JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_ticketstation'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'seatcharts'.DIRECTORY_SEPARATOR.'seatchart'.$this->items[0]->chart_ticketid.'.png';
$seatchart_jpg = '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->items[0]->chart_ticketid.'.jpg';
$image_jpg = JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_ticketstation'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'seatcharts'.DIRECTORY_SEPARATOR.'seatchart'.$this->items[0]->chart_ticketid.'.jpg';

if (file_exists($image_png)) {
    $seatchart = $seatchart_png;
    $image = $image_png;
} else {
    $seatchart = $seatchart_jpg;
    $image = $image_jpg;
}

if (file_exists($image)) {
    ## Get the image size
    list($width, $height, $type, $attr) = getimagesize($image);

    $container_width = $width+250;
} else {
    $container_width = 750;
    $height = 750;
}

$itemid = TicketstationFunctions::getSiteItemid();
$linkback = Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : ''));

?>

<style>

    #container_seatchart {
        height:<?php echo $height+20; ?>px;
        background-repeat:no-repeat;
        background-position: 0px 30px;
        margin:5px auto auto auto;
        position:relative;
        /*width:<?php echo $container_width; ?>px;*/
        width:100%;
        -moz-border-radius: 5px;
        -webkit-border-radius: 5px;
    }

    #glassbox {
        float:right;
        height:<?php echo $height+20; ?>px;
        background-repeat:no-repeat;
        background-position: 0px 30px;
    <?php if (file_exists($image)) { ?>
        background-image: url(<?= $seatchart;?>);
    <?php } ?>
        margin:5px auto auto auto;
        position:relative;
        /*width:<?php echo $width; ?>px;*/
        width:100%;
        /*border-left:1px solid #CCC;
        border-top:1px solid #CCC;*/
    }

</style>

<div class="row ticketstation">

    <div class="page-header">
        <h2><strong>SCANOVERZICHT</strong></h2>
        <h3><?= $this->items[0]->eventcode; ?> | <?= $this->items[0]->ticketname; ?></h3>
    </div>

    <div class="row">
        <div>

            <a class="btn btn-primary pull-left" onClick="location.href='<?= $linkback; ?>'">
                <span>Terug</span>
            </a>

        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div id="container_seatchart">
                <div class="plattegrond" id="glassbox">

                    <?php

                    $k = 0;
                    for ($i = 0, $n = count($this->items); $i < $n; $i++ ){

                        ## Give give $row the this->item[$i]
                        $row        = &$this->items[$i];

                        $x = $row->x_pos;
                        $y = $row->y_pos;

                        if ($row->booked > 0){
                            ## stoel verkocht, maak ROOD
                            $style = 'color:#FFF; border-color:#000;';
                            $background = 'ff0000';
                            if ($row->scanned > 0){
                                ## stoel verkocht en gescand, maak GROEN
                                $style = 'color:#FFF; border-color:#000;';
                                $background = '198d02';
                            }
                        }else{
                            ## stoel vrij
                            $style = 'color:#000; border-color:#000;';
                            if ($row->background_color != ''){
                                $background = $row->background_color;
                            }else{
                                $background = 'e1fdda';
                            }
                        }

                        ## This is a seat --> Load seat data.
                        if ($row->type == 1){

                            echo '<div id="'.$row->id.'" class="seat-element"
                                    style="cursor: default; border: 1px black solid !important;left:'.$x.'px; top:'.$y.'px; background-color:#'.$background.';
                                           width:'.$row->width.'px; height:'.$row->height.'px; line-height:'.$row->height.'px;
                                           position:absolute; '.$style.'")">'.$row->row_name.$row->seatid.'</div>';

                        }else{
                            echo '<div id="'.$row->id.'" class="seat-element" 
                                style="cursor: default; border: 1px black solid !important;left:'.$x.'px; top:'.$y.'px;  background-color:#'.$background.'; 
                                       width:'.$row->width.'px; height:'.$row->height.'px; line-height:'.$row->height.'px; 
                                       position:absolute; '.$style.'")">
                                            <div style = "line-height:'.$row->height.'px;"><strong>'.$row->ticketname.'</strong></div>
                                       </div>';
                        }

                        $k=1 - $k;
                    }
                    ?>

                </div>
            </div>
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
                    if ($item->booked == '1') {
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
                            <strong>Totalen</strong>
                        </h3>
                    </div>
                    <div class="ticketmaster_upcoming_event_content">
                        <div id="totals" style="margin-bottom:10px;">
                            <table width="100%">
                                <tr>
                                    <td style="font-size:11px; text-align:center; border:1px solid #000 !important; background-color:#FFF; color:#000; width: 25px; height:25px;padding: 5px 0px;"><?php echo $countFree ?></td>
                                    <td>&nbsp;&nbsp;&nbsp;= Vrij</td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr><tr>
                                    <td style="font-size:11px; text-align:center; border:1px solid #000 !important; background-color:#FF0000; color:#fff; width: 25px; height:25px;padding: 5px 0px;"><?php echo $countSold ?></td>
                                    <td>&nbsp;&nbsp;&nbsp;= Verkocht</td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr>
                                <tr>
                                    <td style="font-size:11px; text-align:center; border:1px solid #000 !important; background-color:#198d02 ; color:#fff; width: 25px; height:25px;padding: 5px 0px;"><?php echo $countScanned ?></td>
                                    <td>&nbsp;&nbsp;&nbsp;= Gescand</td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr>
                                <tr>
                                    <td colspan="2"><span class="label-notscanned">Niet gescand:  <?php echo $countNotScanned ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <div style="margin-bottom: 25px;text-align: center;" class="refreshdiv">
                <h4 style="margin-bottom:8px;font-weight:bold;">Auto refresh:</h4>
                <div>
                    <div class="btn btn-primary startrefresh">Start</div>
                    <div class="btn btn-primary stoprefresh">Stop</div>
                </div>
                <div style="padding:15px 0 15px 0;"><span class="refreshmsg" style="opacity:0.25;font-weight:bold;padding:5px;border:1px solid #555;border-radius: 3px;background-color:#fff;color:#555;">actief</span></div>
            </div>
        </div>
    </div>

</div>

<script>

    $(document).ready(function () {

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
                    $('#glassbox').load(document.URL + ' #glassbox *')
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