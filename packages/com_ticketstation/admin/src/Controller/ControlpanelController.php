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

use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\DashboardData;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

/**
 * Default Controller of Ticketstation component
 *
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 */
class ControlpanelController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Controlpanel';

    public function __construct($config = array(), ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerControllerTasks('main');
    }

    public function main($cachable = false, $urlparams = array()) {


        return parent::display($cachable, $urlparams);
    }

    /**
     * The figures of the door widget of the dashboard module (mod_ticketstation_dashboard), asked
     * for every half minute while the dashboard is open. Read only.
     */
    public function door()
    {
        $config = (new Config())->get();
        $events = [];

        foreach (DashboardData::doorToday() as $row)
        {
            $events[] = [
                'id'         => $row->eventid,
                'sold'       => $row->sold,
                'scanned'    => $row->scanned,
                'left'       => $row->left,
                'percentage' => $row->percentage,
                'lastscan'   => $row->lastscan ? Date::display($row->lastscan, $config->time_format) : '',
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo json_encode(['success' => true, 'message' => null, 'messages' => null, 'data' => ['events' => $events]]);
        exit();
    }

    /**
     * The counter on the Box Office quick link of the dashboard module (mod_ticketstation_dashboard),
     * in the form Joomla's own quick icons use: the amount, a text for screen readers and the name.
     * Read only.
     */
    public function quickicon()
    {
        $amount = $this->input->getCmd('key', '') === 'boxoffice' ? DashboardData::attentionCount('boxoffice') : 0;

        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, max-age=0, must-revalidate');
        echo json_encode([
            'success'  => true,
            'message'  => null,
            'messages' => null,
            'data'     => [
                'amount' => $amount,
                'sronly' => Text::plural('COM_TICKETSTATION_QUICKICON_BOXOFFICE_SRONLY', $amount),
                'name'   => Text::_('COM_TICKETSTATION_BOXOFFICE'),
            ],
        ]);
        exit();
    }
}