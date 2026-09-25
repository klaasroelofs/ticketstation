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
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;


class SeatplansettingsController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Seatplansettings';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask('unpublish','publish');
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'default');
        $jinput->set('view', 'seatplansettings');
        parent::display();
    }

    public function cancel($cachable = false, $urlparams = []) {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Seatplans');
    }

    function apply()
    {

        $model	    = $this->getModel('seatplansettings');
        $data       = $this->input->post->get('jform', array(), 'array');

        if ($model->store($data)) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=Seatplans&task=editsettings&cid=' . $data['ticketid'], Text::_('COM_TICKETSTATION_SEATPLANSETTINGS_SAVED'));
            return true;
        } else {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Seatplans', Text::_('COM_TICKETSTATION_SEATPLANSETTINGS_SAVED_FAILED'));
            return false;
        }

    }

    public function save($cachable = false, $urlparams = [])
    {
        if ($this->apply()) {
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=Seatplans', Text::_('COM_TICKETSTATION_SEATPLANSETTINGS_SAVED'));
        }
    }

    function removeBackground()
    {
        // Reached via a plain GET link in admin/tmpl/seatplansettings/default.php -
        // check the token as a query param, not just POST.

        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $ticketid = $jinput->get('ticketid', '0', 'INT');

        if($ticketid == 0)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_NO_ID_GIVEN'), 'error');
            $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=seatplans&task=editsettings');
        }

        $image_background_png = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/seatcharts/seatchart' . $ticketid . '.png';
        $image_background_jpg = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/seatcharts/seatchart' . $ticketid . '.jpg';

        if (file_exists($image_background_png)) {
            File::delete($image_background_png);
        } elseif (file_exists($image_background_jpg)) {
            File::delete($image_background_jpg);
        }

        $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_SEATCHART_IMAGE_REMOVED'), 'info');
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&controller=seatplans&task=editsettings&cid='.$ticketid);
    }

}