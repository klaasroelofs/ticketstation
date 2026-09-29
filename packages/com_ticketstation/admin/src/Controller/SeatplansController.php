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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanLayout;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;

/**
 * The list of seat charts and the seat plan editor. The editor loads a chart as one layout
 * (see SeatplanLayout) and talks to the JSON tasks below; each answers with the fresh layout,
 * or with an error message.
 */
class SeatplansController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Seatplans';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('unpublish','publish');
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'seatplans');
        parent::display();
    }

    function displaychart($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'chart');
        $jinput->set('view', 'seatplans');
        parent::display();
    }

    ## The settings are part of the editor since 2.6.0; old links open the editor.
    function editsettings($cachable = false, $urlparams = array())
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid=' . $this->ownerId());
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=seatplans');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

    public function tickets($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=tickets');
    }

    ## "Edit Ticket" in the editor: the edit screen of the ticket that owns this chart. The editor
    ## itself warns about unsaved changes before the page is left.
    public function ticket($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=tickets&task=edit&cid=' . $this->ownerId());
    }

    ## Saves the editor's layout: settings, shapes, section colours, seats and deleted seats.
    public function saveLayout()
    {
        $ownerId = $this->ownerId();
        $data    = json_decode($this->input->post->get('layout', '', 'raw'), true);

        if (!SeatplanLayout::owner($ownerId) || !is_array($data)) {
            $this->respond(['error' => Text::_('COM_TICKETSTATION_SEATEDITOR_SAVE_FAILED')]);
        }

        try {
            $warnings = SeatplanLayout::save($ownerId, $data);
            $layout   = SeatplanLayout::forEditor($ownerId);
        } catch (\Throwable $e) {
            $this->respond(['error' => Text::_('COM_TICKETSTATION_SEATEDITOR_SAVE_FAILED') . ' ' . $e->getMessage()]);
        }

        $this->respond(['warnings' => $warnings, 'layout' => $layout]);
    }

    ## Stores an uploaded background image; the chart uses it once the layout is saved.
    public function uploadBackground()
    {
        $ownerId = $this->ownerId();

        if (!SeatplanLayout::owner($ownerId)) {
            $this->respond(['error' => Text::_('COM_TICKETSTATION_SEATEDITOR_UPLOAD_FAILED')]);
        }

        try {
            $this->respond(['image' => SeatplanLayout::storeImage($ownerId, (array) $this->input->files->get('image', [], 'raw'))]);
        } catch (\RuntimeException $e) {
            $this->respond(['error' => $e->getMessage()]);
        }
    }

    ## Stores the saved chart as a (new or overwritten) template of the ticket's venue.
    public function saveTemplate()
    {
        $ownerId = $this->ownerId();

        try {
            SeatplanLayout::saveTemplate($ownerId, $this->input->post->getString('name', ''), $this->input->post->getInt('template', 0));
        } catch (\RuntimeException $e) {
            $this->respond(['error' => $e->getMessage()]);
        }

        $this->respond(['message' => Text::_('COM_TICKETSTATION_SEATEDITOR_TEMPLATE_SAVED'), 'templates' => SeatplanLayout::templates((int) SeatplanLayout::owner($ownerId)->venue)]);
    }

    ## Deletes a template of the ticket's venue.
    public function deleteTemplate()
    {
        $ownerId = $this->ownerId();
        $owner   = SeatplanLayout::owner($ownerId);
        $id      = $this->input->post->getInt('template', 0);

        if ($owner && SeatplanLayout::template($id, (int) $owner->venue)) {
            SeatplanLayout::deleteTemplate($id);
        }

        $this->respond(['message' => Text::_('COM_TICKETSTATION_SEATEDITOR_TEMPLATE_DELETED'), 'templates' => SeatplanLayout::templates($owner ? (int) $owner->venue : 0)]);
    }

    ## Downloads a template of the ticket's venue as a file that another site can import.
    public function exportTemplate()
    {
        $owner = SeatplanLayout::owner($this->ownerId());
        $id    = $this->input->getInt('template', 0);

        if (!$owner || !SeatplanLayout::template($id, (int) $owner->venue) || !SeatplanLayout::download($id)) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=seatplans&task=displaychart&cid=' . $this->ownerId(), Text::_('COM_TICKETSTATION_SEATEDITOR_TEMPLATE_NOT_FOUND'), 'error');
        }
    }

    ## Adds a template from an exported file to the ticket's venue.
    public function importTemplate()
    {
        $ownerId = $this->ownerId();
        $owner   = SeatplanLayout::owner($ownerId);
        $file    = (array) $this->input->files->get('file', [], 'raw');

        try {
            if (!$owner || empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || (int) $file['size'] > 25 * 1024 * 1024) {
                throw new \RuntimeException(Text::_('COM_TICKETSTATION_SEATEDITOR_IMPORT_INVALID'));
            }

            SeatplanLayout::importTemplate((int) $owner->venue, (string) file_get_contents($file['tmp_name']));
        } catch (\RuntimeException $e) {
            $this->respond(['error' => $e->getMessage()]);
        }

        $this->respond(['message' => Text::_('COM_TICKETSTATION_SEATEDITOR_TEMPLATE_IMPORTED'), 'templates' => SeatplanLayout::templates((int) $owner->venue)]);
    }

    ## Replaces the chart with a template of the ticket's venue.
    public function loadTemplate()
    {
        $ownerId = $this->ownerId();

        try {
            SeatplanLayout::applyTemplate($ownerId, $this->input->post->getInt('template', 0));
        } catch (\RuntimeException $e) {
            $this->respond(['error' => $e->getMessage()]);
        }

        $this->respond(['message' => Text::_('COM_TICKETSTATION_SEATEDITOR_TEMPLATE_LOADED'), 'layout' => SeatplanLayout::forEditor($ownerId)]);
    }

    ## Replaces the chart with the chart of another ticket.
    public function copyFromTicket()
    {
        $ownerId = $this->ownerId();

        try {
            SeatplanLayout::copyFrom($ownerId, $this->input->post->getInt('source', 0));
        } catch (\RuntimeException $e) {
            $this->respond(['error' => $e->getMessage()]);
        }

        $this->respond(['message' => Text::_('COM_TICKETSTATION_SEATEDITOR_COPIED'), 'layout' => SeatplanLayout::forEditor($ownerId)]);
    }

    ## The form token of the current session. The editor asks for it when a request was refused,
    ## e.g. after logging in again in another tab (a new session has a new token). Another site
    ## can't read this answer, so handing it out doesn't weaken the CSRF check.
    public function token()
    {
        $this->respond(['token' => \Joomla\CMS\Session\Session::getFormToken()]);
    }

    ## The chart owner (parent ticket) of the request.
    private function ownerId(): int
    {
        $cid = $this->input->get('cid', [0], 'array');

        return (int) (is_array($cid) ? reset($cid) : $cid);
    }

    ## Ends the request with a JSON answer.
    private function respond(array $data): void
    {
        $app = Factory::getApplication();

        ## Drop anything printed before the answer (a PHP notice, say), so it stays valid JSON.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->sendHeaders();
        echo json_encode($data);
        $app->close();
    }

}
