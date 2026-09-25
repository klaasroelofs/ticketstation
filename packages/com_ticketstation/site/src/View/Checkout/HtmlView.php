<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Checkout;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;


class HtmlView extends BaseHtmlView {


    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null) {

        $app    = Factory::getApplication();
        $db     = Factory::getContainer()->get('DatabaseDriver');

        $info = $app->getUserState('com_ticketstation.registration');

        $model	= $this->getModel('checkout');

        $data	= $this->get('data');
        $config = $this->get('config');

        if ($config->pro_installed == 1)
        {
            $require  = $this->get('datacheck');

            if (isset($require->total)?$require->total:0 > 0)
            {
                $itemid = TicketstationFunctions::getSiteItemid();
                $link = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));
                $app->enqueueMessage($require->total.' '.Text::_( 'COM_TICKETSTATION_TICKETS_REQUIRES_SEAT' ), 'warning');
                $app->redirect($link);
            }

        }

        ## Filling the Array() for doors and make a select list for it.
        $gender = array(
            1 => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_MR' )),
            2 => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_MRS' )),
            //3 => array('value' => '3', 'text' => JText::_( 'COM_TICKETSTATION_MISS' )),
            //4 => array('value' => '4', 'text' => JText::_( 'COM_TICKETSTATION_FAMILY' )),
        );

        $lists['gender'] = HTMLHelper::_('select.genericList', $gender, 'gender', 'class="ts-select"', 'value', 'text', 1 );

        if($config->show_birthday != 0 )
        {
            ## Creating the drop down menu for days
            for ($i = 1, $n = 31; $i <= $n; $i++ )
            {
                $days[] = HTMLHelper::_('select.option', $i, $i);
            }

            ## Create <select name="year_from" class="inputbox"></select> ##
            $lists['day'] = HTMLHelper::_('select.genericlist', $days, 'day', 'class=" input-mini"', 'value', 'text', $info['day']);

            ## Filling the Array() for doors and make a select list for it.
            $month = array(
                1 => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_JANUARY' )),
                2 => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_FEBRUARY' )),
                3 => array('value' => '3', 'text' => Text::_( 'COM_TICKETSTATION_MARCH' )),
                4 => array('value' => '4', 'text' => Text::_( 'COM_TICKETSTATION_APRIL' )),
                5 => array('value' => '5', 'text' => Text::_( 'COM_TICKETSTATION_MAY' )),
                6 => array('value' => '6', 'text' => Text::_( 'COM_TICKETSTATION_JUNE' )),
                7 => array('value' => '7', 'text' => Text::_( 'COM_TICKETSTATION_JULY' )),
                8 => array('value' => '8', 'text' => Text::_( 'COM_TICKETSTATION_AUGUST' )),
                9 => array('value' => '9', 'text' => Text::_( 'COM_TICKETSTATION_SEPTEMBER' )),
                10 => array('value' => '10', 'text' => Text::_( 'COM_TICKETSTATION_OCTOBER' )),
                11 => array('value' => '11', 'text' => Text::_( 'COM_TICKETSTATION_NOVEMBER' )),
                12 => array('value' => '12', 'text' => Text::_( 'COM_TICKETSTATION_DECEMBER' )),

            );

            $lists['month'] = HTMLHelper::_('select.genericList', $month, 'month', ' class="input input-small" ' , 'value', 'text', $info['month'] );

            ## Get current year for dropdown menu:
            $current_year = date('Y');

            ## Creating the drop down menu for years,
            for ($i = 1930, $n = $current_year; $i <= $n; $i++ )
            {
                $years[] = HTMLHelper::_('select.option', $i, $i);
            }

            ## Create <select name="year_from" class="inputbox"></select> ##
            $lists['year'] = HTMLHelper::_('select.genericlist', $years, 'year', 'class="input  input-mini"', 'value', 'text', $info['year']);
        }

        $query = $db->getQuery(true);
        $query->select(array('country_id AS id', 'country AS name '));
        $query->from($db->quoteName('#__ticketstation_country'));
        $query->where($db->quoteName('published')." = ".$db->quote(1));
        $query->where($db->quoteName('country_id')." != ".$db->quote(1));
        $query->order('country ASC');

        $db->setQuery($query);

        $countrylist[]	  = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_PLS_SELECT' ), 'id', 'name' );
        $countrylist	      = array_merge( $countrylist, $db->loadObjectList() );
        $lists['country'] = HTMLHelper::_('select.genericlist',  $countrylist, 'country_id', 'class="ts-select"', 'id',
            'name', '' );

        $this->lists    = $lists;
        $this->data     = $data;
        $this->config   = $config;
        
        // Call the parent display to display the layout file
        parent::display($tpl);
    }

}