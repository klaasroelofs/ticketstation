<?php

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();

$document->setTitle(Text::_( 'COM_TICKETSTATION_EDIT').' '.Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_CHART') . ' - ' . $app->get('sitename'));

$document->addScript('https://code.jquery.com/jquery-3.7.1.js');
$document->addScript('https://code.jquery.com/ui/1.13.2/jquery-ui.js');
$document->addStyleSheet(Uri::base() . 'components/com_ticketstation/assets/css/seatchart.css');

## The image of the seat chart
$seatchart_png = '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->data->ticketid.'.png';
$image_png = JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_ticketstation'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'seatcharts'.DIRECTORY_SEPARATOR.'seatchart'.$this->data->ticketid.'.png';
$seatchart_jpg = '/administrator/components/com_ticketstation/assets/seatcharts/seatchart'.$this->data->ticketid.'.jpg';
$image_jpg = JPATH_ADMINISTRATOR.DIRECTORY_SEPARATOR.'components'.DIRECTORY_SEPARATOR.'com_ticketstation'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'seatcharts'.DIRECTORY_SEPARATOR.'seatchart'.$this->data->ticketid.'.jpg';

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

    #navigation{
        height:<?php echo $height+20; ?>px;
        float:left;
        width:240px;
        border-top:1px solid #CCC;
        margin:5px auto auto auto;
    }

    #glassbox {
        background:#FFF;
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

<form action="<?php echo Route::_('index.php?option=com_ticketstation&controller=seatplans&layout=chart'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="seatplans" />
    <input type="hidden" name="task" value="" />
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-lg-3">
                <div id="navigation" style="border:0px;">

                    <div class="editdiv" style="width: 100%; padding: 8px 0px 8px 0px; border-bottom:0px solid #CCC;">
                        <div class="btn btn-primary edit"><?= Text::_( 'COM_TICKETSTATION_SEATCHART_EDIT' ); ?></div>
                    </div>
                    <div class="stopeditdiv" style="display:none;width: 100%; padding: 8px 0px 8px 0px; border-bottom:0px solid #CCC;">
                        <div class="btn btn-primary stopedit"><?= Text::_( 'COM_TICKETSTATION_SEATCHART_STOP_EDIT' ); ?></div>
                    </div>

                    <div class="accordion" id="accordionEdit" style="display:none;margin-bottom:15px;">
                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingOne">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                    <?= Text::_( 'COM_TICKETSTATION_ADDING_SEATS' ); ?>
                                </button>
                            </h2>

                            <div id="collapseOne" class="accordion-collapse collapse" aria-labelledby="headingOne" data-bs-parent="#accordionEdit">
                                <div class="accordion-body">
                                    <div style="width: 100%; padding: 8px 0px 8px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_CHOOSE_SEAT_SECTOR' ); ?></div>
                                    <div style="width: 100%;"><?php echo $this->lists['childtickets']; ?></div>

                                    <?php if ($this->data->multi_seat == 1){ ?>
                                        <div style="width: 100%; padding: 8px 0px 8px 0px"><?php echo Text::_( 'COM_TICKETSTATION_STARTING_NEWNUMBER' ); ?></div>
                                        <div style="width: 100%;"><?php echo $this->lists['type']; ?></div>

                                        <div style="width: 100%; padding: 8px 0px 8px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_ROW_NAME' ); ?></div>
                                        <div style="width: 100%;"><input name="row_name" id="row_name" class="form-control" style="width:94%;" type="text" value="" maxlength="10" /></div>

                                        <div style="width: 100%; padding: 8px 0px 25px 0px; border-bottom:0px solid #CCC;">
                                            <a href="#" id="addMultiSeat" class="btn btn-primary"> <?php echo Text::_( 'COM_TICKETSTATION_ADD_SEAT_SECTOR' ); ?></a>
                                        </div>

                                    <?php }else{ ?>

                                        <div style="width: 100%; padding: 8px 0px 25px 0px; border-bottom:0px solid #CCC;">
                                            <a href="#" id="addSeat" class="btn btn-primary"> <?php echo Text::_( 'COM_TICKETSTATION_ADD_SEAT_SECTOR' ); ?></a>
                                        </div>

                                    <?php } ?>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingTwo">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                                    <?= Text::_( 'COM_TICKETSTATION_ADDING_SEATS_BATCH' ); ?>
                                </button>
                            </h2>
                            <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo" data-bs-parent="#accordionEdit">
                                <div class="accordion-body">
                                    <div style="width: 100%; padding: 4px 0px 4px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_START_SEAT' ); ?></div>
                                    <div style="width: 100%;"><input name="database_id" id="database_id" class="form-control" style="width:94%; text-align:center;" type="text" value="" maxlength="10" /></div>

                                    <div style="width: 100%; padding: 4px 0px 4px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_SEAT_AMOUNT' ); ?></div>
                                    <div style="width: 100%;"><input name="seat_amount" id="seat_amount" class="form-control" style="width:94%; text-align:center;" type="text" value="" maxlength="10" /></div>

                                    <div style="width: 100%; padding: 4px 0px 4px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_DIRECTION' ); ?></div>
                                    <div style="width: 100%;"><?php echo $this->lists['direction']; ?></div>

                                    <div style="width: 100%; padding: 4px 0px 4px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_SEAT_COUNTER' ); ?></div>
                                    <div style="width: 100%;"><?php echo $this->lists['seat_counter']; ?></div>

                                    <div style="width: 100%; padding: 4px 0px 4px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_WAY_OF_COUNTING' ); ?></div>
                                    <div style="width: 100%;"><?php echo $this->lists['up_down']; ?></div>

                                    <div style="width: 100%; padding: 4px 0px 4px 0px;"><?php echo Text::_( 'COM_TICKETSTATION_SEAT_MARGIN' ); ?></div>
                                    <div style="width: 100%;"><input name="seat_margin" id="seat_margin" class="form-control" style="width:94%; text-align:center;" type="text" value="" maxlength="10" /></div>

                                    <div style="width: 100%; padding: 8px 0px 25px 0px; border-bottom:0px solid #CCC;">
                                        <a href="#" id="startBatch" class="btn btn-primary"> <?= Text::_( 'COM_TICKETSTATION_START_BATCH' ); ?></a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingThree">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                                    <?= Text::_( 'COM_TICKETSTATION_REMOVING_SEATS_CHART' ); ?>
                                </button>
                            </h2>
                            <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree" data-bs-parent="#accordionEdit">
                                <div class="accordion-body">
                                    <div style="padding: 8px 0px;"><em><?= Text::_( 'COM_TICKETSTATION_ENTER_DATABASE_NUMBER' ); ?></em></div>
                                    <div style="padding: 8px 0px;"><input class="form-control" style="width:125px;" id="remove" name="remove" type="text" placeholder="<?= Text::_( 'COM_TICKETSTATION_ENTER_DATABASE_ID' ); ?>"></div>

                                    <div style="padding: 8px 0px;">
                                        <a class="btn btn-primary" id="removeSeat" type="button"><?= Text::_( 'COM_TICKETSTATION_REMOVE_SEATS' ); ?></a>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="headingFour">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFour" aria-expanded="false" aria-controls="collapseFour">
                                    <?= Text::_( 'COM_TICKETSTATION_EDIT_SEAT_NEW' ); ?>
                                </button>
                            </h2>
                            <div id="collapseFour" class="accordion-collapse collapse" aria-labelledby="headingFour" data-bs-parent="#accordionEdit">
                                <div class="accordion-body">
                                    <div id="editSeatChanger1" style="padding: 8px 0px;"><em><?= Text::_( 'COM_TICKETSTATION_ENTER_DATABASE_NUMBER' ); ?></em></div>
                                    <div style="padding: 8px 0px;"><input class="form-control" id="editSeat" style="width:125px;" name="editSeat" type="text" placeholder="<?= Text::_( 'COM_TICKETSTATION_ENTER_DATABASE_ID' ); ?>"></div>

                                    <div style="padding: 8px 0px;" id="editSeatChanger2">
                                        <a class="btn btn-primary" id="loadSeat" type="button"><?= Text::_( 'COM_TICKETSTATION_LOAD_SEAT' ); ?></a>
                                    </div>

                                    <div id="editSeatBox" style="margin-top:8px;"></div>
                                </div>
                            </div>
                        </div>

                        <?php if ($this->data->multi_seat == 1) { ?>
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingFive">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFive" aria-expanded="true" aria-controls="collapseFive">
                                        <?= Text::_( 'COM_TICKETSTATION_COPY_FROM_TICKET' ); ?>
                                    </button>
                                </h2>

                                <div id="collapseFive" class="accordion-collapse collapse" aria-labelledby="headingFive" data-bs-parent="#accordionEdit">
                                    <div class="accordion-body">
                                        <div style="width: 100%; padding: 8px 0px 8px 0px;"><small><?= Text::_( 'COM_TICKETSTATION_COPY_FROM_TICKET_DESC' ); ?></small></div>
                                        <div style="width: 100%; padding: 8px 0px 8px 0px;"><?= Text::_( 'COM_TICKETSTATION_SEATCHART_SELECT_SOURCE_TICKET' ); ?></div>
                                        <div style="width: 100%;"><?= $this->lists['sourcetickets']; ?></div>

                                        <div style="width: 100%; padding: 8px 0px 25px 0px; border-bottom:0px solid #CCC;">
                                            <a href="#" id="CopyFromSource" class="btn btn-primary"> <?php echo Text::_( 'COM_TICKETSTATION_SEATCHART_COPY_FROM_SOURCE_TICKET' ); ?></a>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        <?php } ?>

                    </div>

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
                    <div style="border:1px solid #aaa; padding: 5px 5px 5px 5px; border-radius: 3px; background-color: #ddd;">
                        <div id="totals" style="margin-bottom:10px;">
                            <div style="margin-bottom:8px;font-size:16px;font-weight:bold;width: 100%; padding:8px 0px 4px 0px;color:#3071a9;">Totalen:</div>
                            <table width="100%">
                                <tr>
                                    <td style="font-size:11px;font-weight:bold; text-align:center; border:1px solid #198d02 ; background-color:#FFF; color:#000; width: 25px; height:25px;"><?php echo $countFree ?></td>
                                    <td style="color:#3071a9; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px;">&nbsp;&nbsp;&nbsp;= Vrij</td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr><tr>
                                    <td style="font-size:11px;font-weight:bold; text-align:center; border:1px solid #000; background-color:#FF0000; color:#fff; width: 25px; height:25px;"><?php echo $countSold ?></td>
                                    <td style="color:#3071a9; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px;">&nbsp;&nbsp;&nbsp;= Verkocht</td>
                                    <td style="color:#3071a9; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px;">(<?php echo ($countSold != 0 ? round(($countSold/($countSold+$countFree))*100) : 0)?>%)</td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr>
                                <tr>
                                    <td style="font-size:11px;font-weight:bold; text-align:center; border:1px solid #000; background-color:#198d02 ; color:#fff; width: 25px; height:25px;"><?php echo $countScanned ?></td>
                                    <td style="color:#3071a9; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 13px;">&nbsp;&nbsp;&nbsp;= Gescand</td>
                                    <td style="color:#3071a9; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 10px;">(<?php echo ($countSold != 0 ? round(($countScanned/$countSold)*100) : 0)?>%)</td>
                                </tr>
                                <tr>
                                    <td style="width: 22px; height:10px; line-height:10px;"></td>
                                </tr>
                                <tr>
                                    <td colspan="2"><span class="nietgescand">Niet gescand:  <?php echo $countNotScanned ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    <div class="refreshdiv" style="margin-top:15px;border:1px solid #aaa; padding: 5px 0px 5px 0px; border-radius: 3px; background-color: #ddd;">
                        <div style="margin-bottom:8px;font-size:16px;font-weight:bold;width: 100%; padding:8px 0px 4px 5px;color:#3071a9;">Realtime weergave:</div>
                        <div style="text-align:center;">
                            <div type="button" class="btn btn-primary startrefresh">Start</div>
                            <div type="button" class="btn btn-primary stoprefresh">Stop</div>
                        </div>
                        <div style="padding:15px 0 15px 0;text-align:center;"><span class="refreshmsg" style="opacity:0.25;font-weight:bold;padding:5px;border:1px solid #555;border-radius: 3px;background-color:#fff;color:#555;">Realtime actief</span></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-9">

                <div id="respond">
                    <div id="respond_html">
                    </div>
                </div>
                <div id="seatinfo"></div>

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
                                    style="left:'.$x.'px; top:'.$y.'px; background-color:#'.$background.';
                                           width:'.$row->width.'px; height:'.$row->height.'px; line-height:'.$row->height.'px;
                                           position:absolute; '.$style.'")">'.$row->row_name.$row->seatid.'</div>';

                            }else{
                                echo '<div id="'.$row->id.'" class="seat-element" 
                                style="left:'.$x.'px; top:'.$y.'px;  background-color:#'.$background.'; 
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
    </div>
</div>

<script type="text/javascript">

    // CSRF token name/value pair for this session, appended to every write request below.
    var csrfTokenName = '<?php echo \Joomla\CMS\Session\Session::getFormToken(); ?>';
    var csrfTokenParam = csrfTokenName + '=1';

    $(document).ready(function () {

        // Show/Hide Edit section
        $('.edit').click( function() {
            $('.accordion').slideDown(300),
            $('.refreshdiv').slideUp(300),
            $('.editdiv').hide(),
            $('.stopeditdiv').show(),
            makeDraggable($(".seat-element")),
            enableDraggable($(".seat-element")),
            $(".seat-element").addClass("seat-element-cursor");
        });
        $('.stopedit').click( function() {
            $('.accordion').slideUp(300),
            $('.refreshdiv').slideDown(300),
            $('.editdiv').show(),
            $('.stopeditdiv').hide(),
            disableDraggable($(".seat-element")),
            $(".seat-element").removeClass("seat-element-cursor");
            $("#seatinfo").text( "" );
        });

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

    function disableDraggable(drag_object) {
        drag_object.draggable( 'disable' );
    }

    function enableDraggable(drag_object) {
        drag_object.draggable( 'enable' );
    }

    function makeDraggable(drag_object) {

        if ($('.stopeditdiv').is(":hidden") === true) {
            return false;
        }

        drag_object.draggable({
            containment: '#glassbox',
            scroll: false
        }).mousemove(function(){

            if ($('.stopeditdiv').is(":hidden") === true) {
                return false;
            }

            var coord = $(this).position();
            $("#seatinfo").text( "Positions: Left: " + coord.left + ", Top: " + coord.top + " - Database ID: " + $(this).attr("id") );
            //$("p:first").text( "Positions: Left: " + coord.left + ", Top: " + coord.top + " - Database ID: " + $(this).attr("id") );
            $("#database_id").val( $(this).attr("id") );

        }).mouseup(function(){

            //$(".seat-element").removeClass("seat-element-cursor");
            //$(".seat-element").addClass("seat-element-cursor-grabbed");

            var coords=[];

            var currentId = $(this).attr('id');
            var newSeat   = $("#new_seat").val();
            var rowName   = $("#row_name").val();
            var ticketId  = $("#single").val();

            var coord = $(this).position();
            var item={ coordTop:  coord.left, coordLeft: coord.top, coordId: currentId, newSeatNumber: newSeat, rowName: rowName, ticketid: ticketId};

            coords.push(item);
            var order = { coords: coords };
            const data = 'data='+JSON.stringify(order)+'&'+csrfTokenParam;

            $.ajax({
                //this is the php file that processes the data
                url: "index.php?option=com_ticketstation&controller=seatplans&task=saverecord&format=raw",
                //POST method is used
                type: "POST",
                //pass the data
                data: data,
                //Do not cache the page
                cache: false,
                //success
                success: function (response) {
                    if(response === "success"){
                        $("#respond_html").text('Seat Position has been saved to the database.').addClass('success_msg').fadeIn(1000);
                        setTimeout(function(){ $('#respond_html').fadeOut(1000); }, 2000);
                        setTimeout(function(){ $('#respond_html').removeClass('success_msg'); }, 3000);
                    }else{
                        alert(response);
                    }
                },
                error:function (xhr, ajaxOptions, thrownError){
                    alert(xhr.status);
                }
            });

            //$(".seat-element").removeClass("seat-element-cursor-grabbing");
            //$(".seat-element").addClass("seat-element-cursor");

        });
    }

    $("#loadSeat").bind("click", function(e){

        var seatValue = $("#editSeat").val();
        var data = 'seatid=' + $("#editSeat").val();

        $.ajax({
            //this is the php file that processes the data
            url: "index.php?option=com_ticketstation&controller=seatplans&task=loadSeat&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            success: function (data) {
                // We're done, show data
                $("#editSeatChanger").hide();
                $("#editSeatChanger1").hide();
                $("#editSeatChanger2").hide();
                $( '#editSeatBox').html(data);

            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    });

    $("#addSeat").bind("click", function(e){

        var singleValue = $("#single").val();

        if (singleValue === 0){
            return false;
        }

        $.getJSON("index.php?option=com_ticketstation&controller=seatplans&task=newSeat&ticketid="+ singleValue +"&format=raw&"+csrfTokenParam,

            function(data){

                if (data.id === 0){
                    return false;
                }

                // Create a new div in the glassbox div.
                var elm = $('<div id="'+data.id+'" class="seat-element" style="left:30px; top:30px; position:absolute;">'+data.seatid+'</div>').appendTo("#glassbox");

                // Pass the element to makeDraggable
                makeDraggable(elm);
                $("#"+data.id+"").animate({height: data.seat_height, width: data.seat_width})

            });
    });

    $("#addMultiSeat").bind("click", function(e){

        var singleValue  = $("#single").val();
        var NewRowName   = $("#row_name").val();
        var newSeat      = $("#new_seat").val();

        if (singleValue === 0){
            return false;
        }

        $.getJSON("index.php?option=com_ticketstation&controller=seatplans&task=getrecord&ticketid="+ singleValue +"&row_name="+ NewRowName +"&new_seat="+ newSeat +"&format=raw&"+csrfTokenParam,

            function(data){

                if (data.id === 0){
                    return false;
                }

                // Create a new div in the glassbox div.
                if(data.row_name === ''){
                    var elm = $('<div id="'+data.id+'" class="seat-element" style="left:30px; top:30px; position:absolute;">'+data.seatid+'</div>').appendTo("#glassbox");
                }else{
                    var elm = $('<div id="'+data.id+'" class="seat-element" style="left:30px; top:30px; position:absolute;">'+data.row_name+''+data.seatid+'</div>').appendTo("#glassbox");
                }
                // Pass the element to makeDraggable
                makeDraggable(elm);
                $("#"+data.id+"").animate({height: data.seat_height, width: data.seat_width});

                if(data.new_seatnr === '1'){
                    $("#new_seat").val(0);
                }

            });
    });

    $("#startBatch").bind("click", function(e){

        var database_id = $("#database_id").val();
        var direction = $("#direction").val();
        var seat_counter = $("#seat_counter").val();
        var up_down = $("#up_down").val();
        var seat_amount = $("#seat_amount").val();
        var seat_margin = $("#seat_margin").val();

        //organize the data properly
        var data = 'database_id=' + database_id + '&direction=' + direction +  '&seat_counter=' + seat_counter + '&up_down='  + up_down + '&seat_amount='
            + seat_amount +'&seat_margin='+seat_margin+'&'+csrfTokenParam;

        $.ajax({
            //this is the php file that processes the data and send mail
            url: "index.php?option=com_ticketstation&controller=seatplans&task=BatchAdds&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {

                const htmlparsed = JSON.parse(html);

                $.each(htmlparsed, function(idx, data) {

                    // Create a new div in the glassbox div.
                    if(data.row_name === ''){
                        var elm = $('<div id="'+data.added_database_id+'" class="seat-element" style="left:'+data.x_pos+'px; top:'+data.y_pos+'px; position:absolute;">'+data.seatid+'</div>').appendTo("#glassbox");
                    }else{
                        var elm = $('<div id="'+data.added_database_id+'" class="seat-element" style="left:'+data.x_pos+'px; top:'+data.y_pos+'px; position:absolute;">'+data.row_name+''+data.seatid+'</div>').appendTo("#glassbox");
                    }
                    if (window.console) console.log(data.added_database_id);
                    // Pass the element to makeDraggable
                    makeDraggable(elm);
                    $("#"+data.added_database_id+"").animate({height: data.height, width: data.width});

                });

            },
        });

    });

    $("#removeSeat").bind("click", function(e){

        var removeValue = $("#remove").val();

        if (removeValue === 0){
            return false;
        }

        var data = 'id=' + removeValue + '&' + csrfTokenParam;

        $.ajax({
            //this is the php file that processes the data
            url: "index.php?option=com_ticketstation&controller=seatplans&task=removerecord&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            success: function (data) {
                const dataparsed = JSON.parse(data);
                // We're done, hide removed seat if remove succeeded
                if (dataparsed.result === "1") {
                    $("#" + dataparsed.id + "").hide("slow");
                }
            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    });

    $("#CopyFromSource").bind("click", function(e){

        var sourceValue = $("#source").val();
        var multiseat = <?= $this->data->multi_seat; ?>;
        var countsold = <?= $countSold; ?>;

        if (sourceValue == 0) {
            return false;
        }

        if (countsold > 0) {
            $("#respond_html").text('There are booked seats present! Deletion of seats not possible.').addClass('danger_msg').hide().fadeIn(1000);
            setTimeout(function(){ $('#respond_html').fadeOut(1000); }, 4000);
            setTimeout(function(){ $('#respond_html').removeClass('danger_msg'); }, 6000);
            return false;
        }

        if (multiseat == 0) {
            return false;
        }

        //$(".seat-element").hide("slow");
        $(".seat-element").remove();

        var data = 'sourceid=' + sourceValue + '&targetid=' + <?= $this->data->ticketid; ?> + '&' + csrfTokenParam;

        $.ajax({
            //this is the php file that processes the data
            url: "index.php?option=com_ticketstation&controller=seatplans&task=copyfromsource&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            success: function (html) {

                const htmlparsed = JSON.parse(html);

                if (htmlparsed.result === 0) {
                    $("#respond_html").text('Failed to delete current seat from database.').addClass('danger_msg').hide().fadeIn(1000);
                    setTimeout(function(){ $('#respond_html').fadeOut(1000); }, 2000);
                    setTimeout(function(){ $('#respond_html').removeClass('danger_msg'); }, 4000);
                } else {
                    $.each(htmlparsed, function (idx, data) {

                        // Create a new div in the glassbox div.
                        if (data.row_name === '') {
                            var elm = $('<div id="' + data.added_database_id + '" class="seat-element seat-element-cursor" style="left:' + data.x_pos + 'px; top:' + data.y_pos + 'px; position:absolute;">' + data.seatid + '</div>').appendTo("#glassbox");
                        } else {
                            var elm = $('<div id="' + data.added_database_id + '" class="seat-element seat-element-cursor" style="left:' + data.x_pos + 'px; top:' + data.y_pos + 'px; position:absolute;">' + data.row_name + '' + data.seatid + '</div>').appendTo("#glassbox");
                        }
                        if (window.console) console.log(data.added_database_id);
                        // Pass the element to makeDraggable
                        makeDraggable(elm);
                        $("#" + data.added_database_id + "").animate({height: data.height, width: data.width});
                    });
                }

            },
            error:function (xhr, ajaxOptions, thrownError){
                alert(xhr.status);
            }
        });

    });
</script>