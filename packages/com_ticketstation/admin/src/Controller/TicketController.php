<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Filesystem\File;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\DefaultTicketLayout;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Pdf;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketDesign;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketPreviewCreator;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class TicketController extends FormController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Ticket';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        //$this->registerTask( 'add' , 'edit' );
        //$this->registerTask('unpublish','publish');
        $this->registerTask('apply','apply' );
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'edit');
        $jinput->set('view', 'ticket');
        $jinput->set('hidemainmenu', 1);
        parent::display();
    }

    function apply()
    {

        $app 	    = Factory::getApplication();
        $model	    = $this->getModel('ticket');
        $data       = $this->input->post->get('jform', array(), 'array');

        $data['startdate'] = date('Y-m-d H:i:s', strtotime($data['startdate']));
        $data['enddate'] = date('Y-m-d H:i:s', strtotime($data['enddate']));
        $data['doors_open'] = Date::normalizeTime($data['doors_open'] ?? '');
        $data['publish_date_time'] = date('Y-m-d H:i:s', strtotime($data['publish_date_time']));
        $data['sale_stop'] = date('Y-m-d H:i:s', strtotime($data['sale_stop']));
        $data['parent'] = intval($data['parent']);
        $data['ticketprice'] = floatval($data['ticketprice']);
        $data['vat_percentage'] = floatval($data['vat_percentage']);

        if ((empty($data['ticketname'])) || (empty($data['ticketcode'])) || (empty($data['eventid'])) || (empty($data['venue']))) {

            if (empty($data['ticketname'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETNAME_MISSING'), 'error');
            } elseif (empty($data['ticketcode'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETCODE_MISSING'), 'error');
            } elseif (empty($data['eventid'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_EVENT_MISSING'), 'error');
            } elseif (empty($data['venue'])) {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_VENUE_MISSING'), 'error');
            }

            // Save the data in the session.
            $session = $app->getSession();
            $session->set('postdata', $data);

            // Redirect back to the edit screen.
            $this->display();
            if ($this->getTask() == 'save') {
                return false;
            }

        } else {

            if ($model->store($data)) {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid=' . $model->getTicketID(), Text::_('COM_TICKETSTATION_TICKET_SAVED'));
                return true;

            } else {
                $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets', Text::_('COM_TICKETSTATION_TICKET_SAVED_FAILED'));
                return false;
            }

        }

        return false;

    }

    public function save($cachable = false, $urlparams = [])
    {
        if ($this->apply()) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets', Text::_('COM_TICKETSTATION_TICKET_SAVED'));
        }
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Tickets');
    }

    ## "Save & Seat Plan": saves the ticket like Apply, then opens its seat plan editor, so
    ## unsaved changes in the form are never lost. A ticket that is (no longer) a seated parent
    ## after saving stays on the edit screen with the normal saved message.
    public function seatplan()
    {
        if (!$this->apply()) {
            return;
        }

        ## The button only exists for a saved ticket, so the form carries its ID.
        $ticketid = (int) ($this->input->post->get('jform', [], 'array')['ticketid'] ?? 0);

        $db   = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['parent', 'show_seatplans']))
            ->from($db->quoteName('#__ticketstation_tickets'))
            ->where($db->quoteName('ticketid') . ' = ' . $ticketid);

        $ticket = $db->setQuery($query)->loadObject();

        if ($ticket && (int) $ticket->parent === 0 && (int) $ticket->show_seatplans === 1) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid=' . $ticketid, Text::_('COM_TICKETSTATION_TICKET_SAVED'));
        }
    }

    function removeDesign()
    {
        // Reached via a plain GET link in admin/tmpl/ticket/edit.php - check the
        // token as a query param, not just POST.

        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $ticketid = $jinput->get('ticketid', '0', 'INT');

        if($ticketid == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit');
        }


        $image_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $ticketid . '.jpg';
        $pdf_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/etickets/eTicket-' . $ticketid . '.pdf';

        if (file_exists($image_background))
        {
            File::delete($image_background);
        }

        if(file_exists($pdf_background))
        {
            File::delete($pdf_background);
        }

        $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_DESIGN_REMOVED'), 'info');
        $app->redirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid='.$ticketid);
    }

    function removeBackground()
    {
        // Reached via a plain GET link in admin/tmpl/ticket/edit.php - check the
        // token as a query param, not just POST.

        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $ticketid = $jinput->get('ticketid', '0', 'INT');

        if($ticketid == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit');
        }


        $image_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/ticket' . $ticketid . '.jpg';

        if (file_exists($image_background))
        {
            File::delete($image_background);
        }

        $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_TICKETBG_REMOVED'), 'info');
        $app->redirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid='.$ticketid);
    }

    function TicketLayout()
    {
        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $ticketid = $jinput->get('ticketid', '0', 'INT');
        $model	    = $this->getModel('ticket');

        $ticketdata = $model->getTicketLayout($ticketid);

        echo json_encode($ticketdata);
    }

    /**
     * Renders a preview PDF of the ticket using the field formatting (colour, size,
     * position, ticket size/orientation) currently entered in the "Ticket Layout"
     * tab - including any not-yet-saved changes - combined with dummy content, and
     * streams it back inline so it can be shown in the preview modal.
     */
    function PreviewTicket()
    {
        $app 	  = Factory::getApplication();
        $jinput   = $app->getInput();
        $ticketid = $jinput->get('ticketid', 0, 'INT');
        $data     = $jinput->post->get('jform', array(), 'array');

        $preview = new TicketPreviewCreator();
        $pdf     = $preview->generate($ticketid, $data);

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="ticket-preview.pdf"');
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $pdf;
        exit();
    }

    /**
     * Everything the layout editor on the "Ticket Layout" tab needs for the size, orientation
     * and venue currently entered in the form (saved or not), as JSON:
     *
     * - background: a PDF (base64) with only the ticket's background: its uploaded design or the
     *   built-in layout, drawn by the same code as the real tickets
     * - width/height: the page size in mm
     * - defaults: the built-in field set DefaultTicketLayout prints when the ticket has no design
     *   and no positions of its own (null when it has a design)
     * - texts: the texts of the preview: the ticket's own where filled in, sample text for the
     *   order data (TicketPreviewCreator::sampleTexts())
     */
    function TicketLayoutEditor()
    {
        $app      = Factory::getApplication();
        $jinput   = $app->getInput();
        $ticketid = $jinput->get('ticketid', 0, 'INT');
        $data     = $jinput->post->get('jform', [], 'array');

        ## Ticket size, same rules as ticketcreator::doPDF(). An override that is not two
        ## positive numbers would break the PDF, so the editor falls back to A5 and says so.
        $size    = ($data['ticket_size'] ?? '') == 'A4' ? [210, 297] : [148, 210];
        $invalid = false;

        if (trim((string) ($data['override_ticketsize'] ?? '')) !== '') {
            $parts = array_map('trim', explode(',', $data['override_ticketsize']));

            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1]) && $parts[0] > 0 && $parts[1] > 0) {
                $size = [(float) $parts[0], (float) $parts[1]];
            } else {
                $invalid = true;
            }
        }

        $orientation = ($data['ticket_orientation'] ?? '') === 'L' ? 'L' : 'P';

        $pdf = new Pdf();
        $pdf->AddPage($orientation, $size);

        $defaults = null;

        if (DefaultTicketLayout::applies($ticketid)) {
            DefaultTicketLayout::drawBackground($pdf);
            $defaults = DefaultTicketLayout::defaultFields($pdf);
        } else {
            TicketDesign::draw($pdf, $ticketid);
        }

        $response = [
            'background'  => base64_encode($pdf->Output('S')),
            'width'       => round($pdf->GetPageWidth(), 2),
            'height'      => round($pdf->GetPageHeight(), 2),
            'defaults'    => $defaults,
            'texts'       => (new TicketPreviewCreator())->sampleTexts($data),
            'invalidSize' => $invalid,
        ];

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo json_encode($response);
        exit();
    }

}