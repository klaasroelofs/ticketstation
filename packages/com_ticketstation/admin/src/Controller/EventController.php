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
use Ticketstation\Component\Ticketstation\Administrator\Helper\EventMail;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class EventController extends FormController
{

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Event';

    function __construct($config = array(), ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
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
        $jinput->set('view', 'event');
        //$jinput->set('hidemainmenu', 1);
        parent::display();
    }

    function apply()
    {

        $model	    = $this->getModel('event');
        $data       = $this->input->post->get('jform', array(), 'array');

        if (!empty($data['closingdate'])) {
            $data['closingdate'] = date('Y-m-d H:i:s', strtotime($data['closingdate']));
        } else {
            $data['closingdate'] = null;
        }
        if (!empty($data['startdate'])) {
            $data['startdate'] = date('Y-m-d H:i:s', strtotime($data['startdate']));
        } else {
            $data['startdate'] = null;
        }
        if (!empty($data['eventdate'])) {
            $data['eventdate'] = date('Y-m-d', strtotime($data['eventdate']));
        } else {
            $data['eventdate'] = null;
        }

        $eventid = (int) ($data['eventid'] ?? 0);
        $before  = EventMail::watch('event', $eventid);

        // The question "move the tickets along?" is only asked, and only answered with yes, for
        // an event that already exists and whose date really changed.
        $shiftTickets = !empty($data['shift_tickets']);
        unset($data['shift_tickets']);
        $days = 0;
        $movedEventDates = false;

        if ($shiftTickets && $eventid > 0 && !empty($data['eventdate'])) {
            $stored = $model->getStoredDates($eventid);
            $days   = $this->daysBetween($stored['eventdate'] ?? null, $data['eventdate']);

            // The event's own publish and unpublish dates move along, unless they were changed on this very save
            if ($days !== 0) {
                foreach (['startdate', 'closingdate'] as $field) {
                    if (!empty($data[$field]) && !empty($stored[$field])
                        && substr((string) $data[$field], 0, 16) === substr((string) $stored[$field], 0, 16)) {
                        $data[$field] = date('Y-m-d H:i:s', strtotime($data[$field] . ' ' . sprintf('%+d', $days) . ' days'));
                        $movedEventDates = true;
                    }
                }
            }
        }

        if ($model->store($data)) {
            if ($days !== 0) {
                $moved = $model->shiftTicketDates($eventid, $days);

                if ($moved > 0) {
                    $when = Text::plural('COM_TICKETSTATION_EVENT_SHIFT_DAYS_' . ($days > 0 ? 'LATER' : 'EARLIER'), abs($days));
                    Factory::getApplication()->enqueueMessage(Text::plural('COM_TICKETSTATION_EVENT_TICKETS_SHIFTED', $moved, $when), 'info');
                }

                if (!empty($movedEventDates)) {
                    Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_EVENT_PUBLISHDATES_SHIFTED'), 'info');
                }
            }

            // Date or name changed and the event has buyers: point to the Communication screen
            EventMail::announceChange($before, $data);

            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=event&layout=edit&cid=' . $model->getEventID(), Text::_('COM_TICKETSTATION_EVENT_SAVED'));
        } else {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events', Text::_('COM_TICKETSTATION_EVENT_SAVED_FAILED'));
        }

    }

    /**
     * Whole days from one date to another (Y-m-d), negative when the second is earlier; 0 when
     * either is not a date.
     */
    private function daysBetween($from, $to): int
    {
        if (!Date::isDate($from) || !Date::isDate($to)) {
            return 0;
        }

        $a = new \DateTime(substr($from, 0, 10));
        $b = new \DateTime(substr($to, 0, 10));

        return (int) $a->diff($b)->format('%r%a');
    }

    public function save($cachable = false, $urlparams = [])
    {
        $this->apply();
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events', Text::_('COM_TICKETSTATION_EVENT_SAVED'));
    }

    public function cancel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events');
    }

    function removeBackground()
    {
        // Reached via a plain GET link in admin/tmpl/event/edit.php - check the
        // token as a query param, not just POST.

        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $eventid = $jinput->get('eventid', '0', 'INT');

        if($eventid == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=events');
        }


        $image_background = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/event' . $eventid . '.jpg';

        if (file_exists($image_background))
        {
            File::delete($image_background);
        }

        $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_TICKETBG_EVENT_REMOVED'), 'info');
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=event&layout=edit&cid='.$eventid);
    }

}