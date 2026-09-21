<?php

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Amount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticket;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Cart Controller
 * @since  0.2.11
 */
class CartController extends BaseController
{
    private $ordercode;

    function __construct()
    {
        parent::__construct();

        $jinput = Factory::getApplication()->getInput();

        $this->ordercode = $jinput->get('ordercode', '0', 'int');
        $this->remark    = $jinput->get('content', '', 'raw');
    }

    /**
     * Loading the terms and conditions in the cart page.
     *
     * @since 1.0.0
     */
    function showTos()
    {
        // Todo Rebuild to message system
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_emails'))
            ->where($db->quoteName('emailid') . ' = 50');

        $db->setQuery($query);

        $data = $db->loadObject();

        $content = '<h3>' . $data->mailsubject . '</h3>';
        $content .= '<p>' . $data->mailbody . '</p>';

        echo $content;

        exit();
    }

    
    /**
     * Saving the remark to the database.
     *
     * @since 1.0.0
     */
    function saveRemark()
    {

        $post      = Factory::getApplication()->getInput()->post->getArray();
        $ordercode = Factory::getApplication()->getSession()->get('ordercode');

        if ($ordercode != $this->ordercode)
        {
            $msg = '<font color="#FF0000">' . Text::_('COM_TICKETSTATION_SAVING_CONTENT_FAILED') . '</font>';

            $arr = [
                'status' => '666',
                'msg'    => $msg,
            ];

            exit(json_encode($arr));
        }

        $model = $this->getModel('cart');

        $post['id']        = $model->getPreviousRemark();
        $post['remarks']   = $new_string = strip_tags($this->remark);
        $post['ordercode'] = $ordercode;

        if ( ! $model->storeRemark($post))
        {
            $msg = '<font color="#FF0000">' . Text::_('COM_TICKETSTATION_SAVING_CONTENT_FAILED') . '</font>';

            $arr = [
                'status' => '666',
                'msg'    => $msg,
            ];

            echo json_encode($arr);
        }
        else
        {
            $arr = [
                'status' => '200',
                'msg'    => Text::_('COM_TICKETSTATION_CONTENT_SAVED'),
            ];

            echo json_encode($arr);
        }
    }
}	