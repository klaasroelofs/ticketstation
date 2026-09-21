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
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();

$add_edit = empty($this->item->ticketid) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
$ticket_name = empty($this->item->ticketid) ? Text::_('COM_TICKETSTATION_VIEW_TICKET_TITLE') : $this->item->ticketname . ' (' . $this->item->ticketcode . ')';
$document->setTitle($add_edit . ' ' . $ticket_name . ' - ' . $app->get('sitename'));
$document->addStyleSheet(Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

// The Bootstrap modal JS is only loaded by Joomla when a page actually asks for it -
// this page doesn't use any other Bootstrap modal, so without this call bootstrap.Modal
// would not exist and the ticket preview modal could never be shown.
$document->getWebAssetManager()->useScript('bootstrap.modal');

if(isset($this->item->ticketid))
{
    ## Ticket Design
    $image_design = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $this->item->ticketid . '.jpg';
    $pdf_design = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $this->item->ticketid . '.pdf';
    $ticket_design_present = false;

    if (file_exists($image_design))
    {
        $design_type = Text::_( 'COM_TICKETSTATION_IMAGE_BACKGROUND' );
        $design = Uri::root() . 'administrator/components/com_ticketstation/assets/etickets/eTicket-'.$this->item->ticketid.'.jpg';
        $ticket_design_present = true;
    }
    else if (file_exists($pdf_design))
    {
        $design_type = Text::_( 'COM_TICKETSTATION_PDF_BACKGROUND' );
        $design = Uri::root() . 'administrator/components/com_ticketstation/assets/etickets/eTicket-'.$this->item->ticketid.'.pdf';
        $ticket_design_present = true;
    }
    else
    {
        $design_type = Text::_( 'COM_TICKETSTATION_DEFAULT_TICKET' );
        $design = Uri::root() . 'administrator/components/com_ticketstation/assets/etickets/eTicket.pdf';
    }

    ## Background Image (Upcoming Events frontend)
    $background_ticket  = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/ticket' . $this->item->ticketid . '.jpg';
    $background_event   = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/event' . $this->item->eventid . '.jpg';
    $background_img_present = false;
    $background_img_event = false;

    if (file_exists($background_ticket))
    {
        $background_img = Uri::root() . 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/ticket'.$this->item->ticketid.'.jpg';
        $background_img_present = true;
    }
    else if (file_exists($background_event))
    {
        $background_img = Uri::root() . 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/event'.$this->item->eventid.'.jpg';
        $background_img_event = true;
    }
    else
    {
        $background_img = '';
    }

}

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=ticket&layout=edit'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <div class="card-body">
            <?= HTMLHelper::_('uitab.startTabSet', 'myTab', ['active' => 'main_settings', 'recall' => true, 'breakpoint' => 768]); ?>

            <?= HTMLHelper::_('uitab.addTab', 'myTab', 'main_settings', Text::_('COM_TICKETSTATION_TICKET_MAIN_SETTINGS')); ?>
            <div class="row">
                <?= $this->form->renderFieldset('main_settings'); ?>
            </div>
            <?= HTMLHelper::_('uitab.endTab'); ?>

            <?= HTMLHelper::_('uitab.addTab', 'myTab', 'ticketdetails', Text::_('COM_TICKETSTATION_TICKET_DETAIL_SETTINGS')); ?>
            <div class="row">
                <div class="col-md-6">
                    <?= $this->form->renderFieldset('ticket_details_1'); ?>
                    <?= $this->form->renderFieldset('ticket_details_2'); ?>
                </div>
                <div class="col-md-6">
                    <?= $this->form->renderFieldset('ticket_details_3'); ?>
                </div>
            </div>
            <?= HTMLHelper::_('uitab.endTab'); ?>

           <?= HTMLHelper::_('uitab.addTab', 'myTab', 'ticketlayout', Text::_('COM_TICKETSTATION_TICKET_LAYOUT_SETTINGS')); ?>
            <div class="row">

                <div class="col-md-6">
                    <h2>
                        <?= Text::_('COM_TICKETSTATION_TICKET_SIZE_SETTINGS'); ?>
                    </h2>
                    <?= $this->form->renderFieldset('ticket_layout_1'); ?>
                </div>

                <div class="col-md-6">
                    <h2>
                        <?= Text::_('COM_TICKETSTATION_TICKET_TEMPLATE_BACKGROUND'); ?>
                    </h2>
                    <div class="row mb-3">
                        <?php if (isset($this->item->ticketid)?$this->item->ticketid:0 > 0)  { ?>

                            <?php $remove_link_design = 'index.php?option=com_ticketstation&controller=ticket&task=removeDesign&ticketid='.$this->item->ticketid.'&'.Factory::getApplication()->getSession()->getToken().'=1'; ?>

                            <div class="control-group">
                                <div class="control-label">
                                    <label><?= Text::_( 'COM_TICKETSTATION_TICKET_CURRENT_DESIGN' ); ?></label>
                                </div>
                                <div class="controls">
                                    <a href="<?= $design; ?>" target="blank" class="btn btn-secondary">
                                        <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_VIEW_DESIGN' ); ?></a>

                                    <?php if ($ticket_design_present) { ?>
                                        <a href="<?= $remove_link_design; ?>" class="btn btn-secondary">
                                            <i class="icon-trash"></i>  <?= Text::_( 'COM_TICKETSTATION_REMOVE_DESIGN' ); ?></a>
                                    <?php } ?>
                                </div>
                            </div>

                        <?php } ?>
                    </div>
                    <?= $this->form->renderFieldset('ticket_layout_2'); ?>
                    <div class="row mb-3">
                        <?php if (isset($this->item->ticketid)?$this->item->ticketid:0 > 0)  { ?>

                            <?php $remove_link_bg = 'index.php?option=com_ticketstation&controller=ticket&task=removeBackground&ticketid='.$this->item->ticketid.'&'.Factory::getApplication()->getSession()->getToken().'=1'; ?>

                            <div class="control-group">
                                <div class="control-label">
                                    <label><?= Text::_( 'COM_TICKETSTATION_TICKET_CURRENT_BACKGROUND_UPCOMING' ); ?></label>
                                </div>
                                <div class="controls">
                                    <?php if ($background_img_present || $background_img_event) { ?>
                                        <a href="<?= $background_img; ?>" target="blank" class="btn btn-secondary">
                                            <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_VIEW_BACKGROUND_UPCOMING' ); ?></a>
                                    <?php } else { ?>
                                        <div><strong>No image present</strong></div>
                                    <?php } ?>
                                    <?php if ($background_img_present) { ?>
                                        <a href="<?= $remove_link_bg; ?>" class="btn btn-secondary">
                                            <i class="icon-trash"></i>  <?= Text::_( 'COM_TICKETSTATION_REMOVE_BACKGROUND_UPCOMING' ); ?></a>
                                    <?php } ?>
                                </div>
                            </div>

                        <?php } ?>
                    </div>
                    <?= $this->form->renderFieldset('ticket_layout_3'); ?>
                </div>

            </div>

            <hr />

            <div class="row">

                <div class="col-md-6">
                    <h2>
                        <?= Text::_('COM_TICKETSTATION_TICKET_DESIGN_SETTINGS'); ?>
                    </h2>
                    <div class="form-text" style="margin-bottom: 20px;">
                        <?= Text::_('COM_TICKETSTATION_TICKET_DESIGN_SETTINGS_DESC'); ?>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="openTicketPreview()">
                        <i class="icon-search"></i> <?= Text::_('COM_TICKETSTATION_TICKET_PREVIEW'); ?>
                    </button>

                    <hr/>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_COPY_VALUES_FROM_OTHER'); ?>
                    </h3>
                    <div class="form-text" style="margin-bottom: 20px;">
                        <?= Text::_('COM_TICKETSTATION_COPY_VALUES_FROM_OTHER_DESC'); ?>
                    </div>
                    <div>
                        <?= $this->form->renderField('fieldcopyticket'); ?>
                    </div>
                    <a onclick="loadticketvalues()" class="btn btn-secondary">
                        <i class="icon-download"></i>
                        <?= Text::_( 'COM_TICKETSTATION_COPY_VALUES_FROM_OTHER_LOAD' ); ?>
                    </a>

                    <hr/>

                </div>
            </div>

            <div class="row">

                <div class="col-md-6">
                    <h3>
                        <?= Text::_('COM_TICKETSTATION_EVENTNAME'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_eventname'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_TICKETNAME'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_ticketname'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_FREETEXT_1'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_freetext_1'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_TICKETDATE'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_ticketdate'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_TICKETPRICE'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_ticketprice'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_ORDERDATE'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_orderdate'); ?>

                </div>

                <div class="col-md-6">
                    <h3>
                        <?= Text::_('COM_TICKETSTATION_CLIENT'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_client'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_ORDERTICKETINDEX'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_orderticketindex'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_ORDERNUMBER'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_ordernumber'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_SEATNUMBER'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_seatnumber'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_ORDERREFERENCE'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_orderreference'); ?>

                    <h3>
                        <?= Text::_('COM_TICKETSTATION_QRCODE'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_qrcode'); ?>

                </div>

            </div>
            <?= HTMLHelper::_('uitab.endTab'); ?>

            <?= HTMLHelper::_('uitab.endTabSet'); ?>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation"/>
    <input type="hidden" name="controller" value="ticket"/>
    <input type="hidden" name="task" value=""/>
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>

<div class="modal fade" id="ticketPreviewModal" tabindex="-1" aria-labelledby="ticketPreviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ticketPreviewModalLabel"><?= Text::_('COM_TICKETSTATION_TICKET_PREVIEW'); ?></h5>
                <button type="button" class="btn-close" onclick="closeTicketPreview()" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0" style="min-height: 60vh;">
                <div id="ticketPreviewLoading" class="text-center p-5">
                    <?= Text::_('COM_TICKETSTATION_TICKET_PREVIEW_LOADING'); ?>
                </div>
                <div id="ticketPreviewError" class="alert alert-danger m-3 d-none" role="alert">
                    <?= Text::_('COM_TICKETSTATION_TICKET_PREVIEW_ERROR'); ?>
                </div>
                <iframe id="ticketPreviewFrame" class="d-none" style="width: 100%; height: 70vh; border: 0;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="loadTicketPreview()">
                    <i class="icon-refresh"></i> <?= Text::_('COM_TICKETSTATION_TICKET_PREVIEW_UPDATE'); ?>
                </button>
                <button type="button" class="btn btn-secondary" onclick="closeTicketPreview()"><?= Text::_('JCLOSE'); ?></button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-latest.min.js"></script>
<script type="text/javascript">

    // Explicitly drive the modal through the Bootstrap JS API instead of relying on
    // data-bs-toggle/data-bs-dismiss (the automatic attribute-driven behaviour did not
    // reliably show the modal on this page), so opening/closing always works the same way.
    function getTicketPreviewModal() {
        var modalEl = document.getElementById('ticketPreviewModal');

        if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
            return null;
        }

        return bootstrap.Modal.getOrCreateInstance(modalEl);
    }

    function openTicketPreview() {
        var modal = getTicketPreviewModal();

        if (modal) {
            modal.show();
        }

        loadTicketPreview();
    }

    function closeTicketPreview() {
        var modal = getTicketPreviewModal();

        if (modal) {
            modal.hide();
        }
    }

    function loadTicketPreview() {

        var ticketid = jQuery("#jform_ticketid").length ? jQuery("#jform_ticketid").val() : 0;

        // Leave out the form's own option/controller/task hidden fields: since those
        // share their name with our target URL's query parameters, Joomla merges GET
        // and POST into $_REQUEST with POST winning, so posting the form's own
        // (empty) task field would silently override task=PreviewTicket and route
        // the request to the normal "display" task instead.
        var params = jQuery("#adminForm").serializeArray().filter(function (field) {
            return field.name !== 'task' && field.name !== 'option' && field.name !== 'controller';
        });

        var body = new URLSearchParams();
        params.forEach(function (field) {
            body.append(field.name, field.value);
        });
        body.append('ticketid', ticketid);

        jQuery("#ticketPreviewError").addClass('d-none');
        jQuery("#ticketPreviewFrame").addClass('d-none');
        jQuery("#ticketPreviewLoading").removeClass('d-none');

        // Plain fetch() instead of jQuery.ajax(): jQuery throws an uncaught
        // InvalidStateError when it tries to read xhr.responseText internally on a
        // request configured with xhrFields.responseType = 'blob', which breaks the
        // callback chain before our own success/error handling ever runs.
        fetch("index.php?option=com_ticketstation&controller=ticket&task=PreviewTicket&format=raw", {
            method: 'POST',
            body: body,
            cache: 'no-store'
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.blob();
            })
            .then(function (blob) {
                // A blob that isn't a PDF means something went wrong server-side
                // (eg. an error page or a redirect got returned instead).
                if (blob.type.indexOf('pdf') === -1) {
                    throw new Error('Unexpected response type: ' + blob.type);
                }

                var frame = document.getElementById("ticketPreviewFrame");

                if (frame.dataset.blobUrl) {
                    URL.revokeObjectURL(frame.dataset.blobUrl);
                }

                var url = URL.createObjectURL(blob);
                frame.src = url;
                frame.dataset.blobUrl = url;

                jQuery("#ticketPreviewLoading").addClass('d-none');
                jQuery("#ticketPreviewFrame").removeClass('d-none');

                // If the modal never actually became visible (eg. the Bootstrap JS
                // bundle isn't available on this page), fall back to opening the
                // PDF in a new tab so the preview is never silently lost.
                var modalEl = document.getElementById('ticketPreviewModal');
                if (!modalEl.classList.contains('show')) {
                    window.open(url, '_blank');
                }
            })
            .catch(function () {
                jQuery("#ticketPreviewLoading").addClass('d-none');
                jQuery("#ticketPreviewError").removeClass('d-none');
            });
    }

    function loadticketvalues(){

        var ticket = document.getElementById("jform_fieldcopyticket").value;
        var data = 'ticketid=' + ticket;

        jQuery.ajax({
            //this is the php file that processes the data and send mail
            url: "index.php?option=com_ticketstation&controller=ticket&task=TicketLayout&format=raw",
            //POST method is used
            type: "POST",
            //pass the data
            data: data,
            //Do not cache the page
            cache: false,
            //success
            success: function (ticketdata) {

                var dataparsed = JSON.parse(ticketdata)

                jQuery("#jform_eventname_fontcolor").val(dataparsed.eventname_fontcolor);
                jQuery("#jform_eventname_fontsize").val(dataparsed.eventname_fontsize);
                jQuery("#jform_eventname_position").val(dataparsed.eventname_position);

                jQuery("#jform_ticketname_fontcolor").val(dataparsed.ticketname_fontcolor);
                jQuery("#jform_ticketname_fontsize").val(dataparsed.ticketname_fontsize);
                jQuery("#jform_ticketname_position").val(dataparsed.ticketname_position);

                jQuery("#jform_freetext_1_fontcolor").val(dataparsed.freetext_1_fontcolor);
                jQuery("#jform_freetext_1_fontsize").val(dataparsed.freetext_1_fontsize);
                jQuery("#jform_freetext_1_position").val(dataparsed.freetext_1_position);

                jQuery("#jform_ticketdate_fontcolor").val(dataparsed.ticketdate_fontcolor);
                jQuery("#jform_ticketdate_fontsize").val(dataparsed.ticketdate_fontsize);
                jQuery("#jform_ticketdate_position").val(dataparsed.ticketdate_position);

                jQuery("#jform_ticketprice_fontcolor").val(dataparsed.ticketprice_fontcolor);
                jQuery("#jform_ticketprice_fontsize").val(dataparsed.ticketprice_fontsize);
                jQuery("#jform_ticketprice_position").val(dataparsed.ticketprice_position);

                jQuery("#jform_orderdate_fontcolor").val(dataparsed.orderdate_fontcolor);
                jQuery("#jform_orderdate_fontsize").val(dataparsed.orderdate_fontsize);
                jQuery("#jform_orderdate_position").val(dataparsed.orderdate_position);

                jQuery("#jform_client_fontcolor").val(dataparsed.client_fontcolor);
                jQuery("#jform_client_fontsize").val(dataparsed.client_fontsize);
                jQuery("#jform_client_position").val(dataparsed.client_position);

                jQuery("#jform_orderticketindex_fontcolor").val(dataparsed.orderticketindex_fontcolor);
                jQuery("#jform_orderticketindex_fontsize").val(dataparsed.orderticketindex_fontsize);
                jQuery("#jform_orderticketindex_position").val(dataparsed.orderticketindex_position);
                if (dataparsed.orderticketindex_prependtext_print === 0) {
                    jQuery("#jform_orderticketindex_prependtext_print0").prop('checked', true).toggleClass("active");
                    jQuery("#jform_orderticketindex_prependtext_print1").prop('checked', false).toggleClass("active");
                } else {
                     jQuery("#jform_orderticketindex_prependtext_print0").prop('checked', false).toggleClass("active");
                     jQuery("#jform_orderticketindex_prependtext_print1").prop('checked', true).toggleClass("active");
                }
                jQuery("#jform_orderticketindex_prependtext").val(dataparsed.orderticketindex_prependtext);

                jQuery("#jform_ordernumber_fontcolor").val(dataparsed.ordernumber_fontcolor);
                jQuery("#jform_ordernumber_fontsize").val(dataparsed.ordernumber_fontsize);
                jQuery("#jform_ordernumber_position").val(dataparsed.ordernumber_position);

                jQuery("#jform_seatnumber_fontcolor").val(dataparsed.seatnumber_fontcolor);
                jQuery("#jform_seatnumber_fontsize").val(dataparsed.seatnumber_fontsize);
                jQuery("#jform_seatnumber_position").val(dataparsed.seatnumber_position);

                jQuery("#jform_orderreference_fontcolor").val(dataparsed.orderreference_fontcolor);
                jQuery("#jform_orderreference_fontsize").val(dataparsed.orderreference_fontsize);
                jQuery("#jform_orderreference_position").val(dataparsed.orderreference_position);
                if (dataparsed.orderreference_centered === 0) {
                    jQuery("#jform_orderreference_centered0").prop('checked', true).toggleClass("active");
                    jQuery("#jform_orderreference_centered1").prop('checked', false).toggleClass("active");
                } else {
                     jQuery("#jform_orderreference_centered0").prop('checked', false).toggleClass("active");
                     jQuery("#jform_orderreference_centered1").prop('checked', true).toggleClass("active");
                }

                jQuery("#jform_qrcode_position").val(dataparsed.qrcode_position);
                jQuery("#jform_qrcode_width").val(dataparsed.qrcode_width);

            }
        });
    }

</script>
