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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

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
        ## No uploaded design: the built-in layout (DefaultTicketLayout) is drawn instead,
        ## which only exists as the ticket preview, not as a file to link to.
        $design_type = Text::_( 'COM_TICKETSTATION_DEFAULT_TICKET' );
        $design = '';
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

                            <?php $remove_link_design = 'index.php?option=com_ticketstation&controller=ticket&task=removeDesign&ticketid='.$this->item->ticketid.'&'.\Joomla\CMS\Session\Session::getFormToken().'=1'; ?>

                            <div class="control-group">
                                <div class="control-label">
                                    <label><?= Text::_( 'COM_TICKETSTATION_TICKET_CURRENT_DESIGN' ); ?></label>
                                </div>
                                <div class="controls">
                                    <?php if ($ticket_design_present) { ?>
                                        <a href="<?= $design; ?>" target="blank" class="btn btn-secondary">
                                            <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_VIEW_DESIGN' ); ?></a>
                                    <?php } else { ?>
                                        <div class="mb-2"><strong><?= $design_type; ?></strong></div>
                                        <button type="button" class="btn btn-secondary" onclick="openTicketPreview()">
                                            <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_TICKET_PREVIEW' ); ?></button>
                                    <?php } ?>

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

                            <?php $remove_link_bg = 'index.php?option=com_ticketstation&controller=ticket&task=removeBackground&ticketid='.$this->item->ticketid.'&'.\Joomla\CMS\Session\Session::getFormToken().'=1'; ?>

                            <div class="control-group">
                                <div class="control-label">
                                    <label><?= Text::_( 'COM_TICKETSTATION_TICKET_CURRENT_BACKGROUND_UPCOMING' ); ?></label>
                                </div>
                                <div class="controls">
                                    <?php if ($background_img_present || $background_img_event) { ?>
                                        <a href="<?= $background_img; ?>" target="blank" class="btn btn-secondary">
                                            <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_VIEW_BACKGROUND_UPCOMING' ); ?></a>
                                    <?php } else { ?>
                                        <div><strong><?= Text::_('COM_TICKETSTATION_NO_IMAGE_PRESENT'); ?></strong></div>
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

            <h2>
                <?= Text::_('COM_TICKETSTATION_TICKET_DESIGN_SETTINGS'); ?>
            </h2>
            <div class="form-text mb-3">
                <?= Text::_('COM_TICKETSTATION_TLE_INTRO'); ?>
            </div>

            <?php // The layout editor (assets/js/ticketlayouteditor.js); it reads and writes the fields below. ?>
            <div id="ts-tle" class="ts-tle mb-4">
                <noscript><div class="alert alert-warning"><?= Text::_('COM_TICKETSTATION_TLE_NEEDS_JS'); ?></div></noscript>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <h3>
                        <?= Text::_('COM_TICKETSTATION_COPY_VALUES_FROM_OTHER'); ?>
                    </h3>
                    <div class="form-text mb-3">
                        <?= Text::_('COM_TICKETSTATION_COPY_VALUES_FROM_OTHER_DESC'); ?>
                    </div>
                    <div>
                        <?= $this->form->renderField('fieldcopyticket'); ?>
                    </div>
                    <button type="button" onclick="loadticketvalues()" class="btn btn-secondary">
                        <i class="icon-download"></i>
                        <?= Text::_( 'COM_TICKETSTATION_COPY_VALUES_FROM_OTHER_LOAD' ); ?>
                    </button>
                </div>
            </div>

            <hr />

            <details class="ts-tle-advanced">
                <summary><?= Text::_('COM_TICKETSTATION_TLE_ADVANCED'); ?></summary>
                <div class="form-text my-3">
                    <?= Text::_('COM_TICKETSTATION_TICKET_DESIGN_SETTINGS_DESC'); ?>
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
                        <?= Text::_('COM_TICKETSTATION_VENUE'); ?>
                    </h3>
                    <hr />
                    <?= $this->form->renderFieldset('ticket_layout_venue'); ?>

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
            </details>
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
                <button type="button" class="btn-close" onclick="closeTicketPreview()" aria-label="<?= Text::_('JCLOSE'); ?>"></button>
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

<script>
    // The form's values for a raw request (preview, layout editor, copy): without the form's own
    // option/controller/task fields, which would override the task in the request URL (Joomla
    // merges GET and POST, with POST winning), and without the file inputs.
    function ticketFormBody() {
        var body = new URLSearchParams();
        var form = document.getElementById('adminForm');
        var ticketid = document.getElementById('jform_ticketid');

        new FormData(form).forEach(function (value, name) {
            if (name !== 'task' && name !== 'option' && name !== 'controller' && typeof value === 'string') {
                body.append(name, value);
            }
        });

        body.append('ticketid', ticketid ? ticketid.value : 0);

        return body;
    }

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

    function showPreviewState(state) {
        document.getElementById('ticketPreviewLoading').classList.toggle('d-none', state !== 'loading');
        document.getElementById('ticketPreviewError').classList.toggle('d-none', state !== 'error');
        document.getElementById('ticketPreviewFrame').classList.toggle('d-none', state !== 'ready');
    }

    function loadTicketPreview() {
        showPreviewState('loading');

        fetch('index.php?option=com_ticketstation&controller=ticket&task=PreviewTicket&format=raw', {
            method: 'POST',
            body: ticketFormBody(),
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

                var frame = document.getElementById('ticketPreviewFrame');

                if (frame.dataset.blobUrl) {
                    URL.revokeObjectURL(frame.dataset.blobUrl);
                }

                var url = URL.createObjectURL(blob);
                frame.src = url;
                frame.dataset.blobUrl = url;

                showPreviewState('ready');

                // If the modal never actually became visible (eg. the Bootstrap JS
                // bundle isn't available on this page), fall back to opening the
                // PDF in a new tab so the preview is never silently lost.
                var modalEl = document.getElementById('ticketPreviewModal');
                if (!modalEl.classList.contains('show')) {
                    window.open(url, '_blank');
                }
            })
            .catch(function () {
                showPreviewState('error');
            });
    }

    // Copies the layout fields of the ticket chosen under "Load fields from other Ticket". Every
    // changed field fires a change event, so the layout editor follows.
    function loadticketvalues() {
        var body = new URLSearchParams();
        body.append('ticketid', document.getElementById('jform_fieldcopyticket').value);

        fetch('index.php?option=com_ticketstation&controller=ticket&task=TicketLayout&format=raw', {
            method: 'POST',
            body: body,
            cache: 'no-store'
        })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data) {
                    return;
                }

                var fields = ['eventname', 'ticketname', 'freetext_1', 'ticketdate', 'venue', 'ticketprice', 'orderdate',
                    'client', 'orderticketindex', 'ordernumber', 'seatnumber', 'orderreference'];
                var names = [];

                fields.forEach(function (field) {
                    names.push(field + '_fontcolor', field + '_fontsize', field + '_position');
                });
                names.push('orderticketindex_prependtext', 'qrcode_position', 'qrcode_width');

                if (data.ticket_font) {
                    names.push('ticket_font');
                }

                names.forEach(function (name) {
                    var input = document.getElementById('jform_' + name);

                    if (input) {
                        input.value = data[name] === null || data[name] === undefined ? '' : data[name];
                        input.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });

                ['orderticketindex_prependtext_print', 'orderreference_centered'].forEach(function (name) {
                    var radio = document.getElementById('jform_' + name + (String(data[name]) === '1' ? '1' : '0'));

                    if (radio) {
                        radio.checked = true;
                        radio.dispatchEvent(new Event('change', {bubbles: true}));
                    }
                });
            });
    }
</script>
