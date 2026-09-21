<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Boxoffice;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\ticketcreator;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Boxoffice Admin View
 */
class HtmlView extends BaseHtmlView
{

	/**
     * Display the Ticketstation Events view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {

        if($this->getLayout() == 'form')
        {
            $this->_displayForm($tpl);
            return;
        }
		
        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_BOXOFFICE_TITLE'), 'fa fa-money-bill-alt');

        $toolbar = Toolbar::getInstance('toolbar');
        $dropdown = $toolbar->dropdownButton('status-group')
            ->text('JTOOLBAR_CHANGE_STATUS')
            ->toggleSplit(false)
            ->icon('icon-ellipsis-h')
            ->buttonClass('btn btn-action')
            ->listCheck(true);

        /** @var Toolbar $childBar */
        $childBar = $dropdown->getChildToolbar();

        $childBar->standardButton('resendpayment', 'COM_TICKETSTATION_RESEND_PAYMENT', 'boxoffice.resendpayment')
            ->icon('fa fa-share')
            ->listCheck(true);

        $childBar->standardButton('refund', 'COM_TICKETSTATION_REFUNDED', 'boxoffice.refund')
            ->icon('fa fa-reply')
            ->listCheck(true);

        $childBar->standardButton('full_process', 'COM_TICKETSTATION_TOOLBAR_FULL_PROCESS', 'boxoffice.full_process')
            ->icon('fa fa-cube')
            ->listCheck(true);

        $childBar->delete('boxoffice.remove')
            ->message('JGLOBAL_CONFIRM_DELETE')
            ->listCheck(true);

        ToolbarHelper::custom('reservation', 'fa-solid fa-calendar-plus', '', 'COM_TICKETSTATION_VIEW_RESERVATION_TITLE', false,false);

        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        $app = Factory::getApplication();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $config = $this->get('config');

        $filter_order     = $app->getUserStateFromRequest( 'filter_ordering_e', 'filter_ordering_e','0','cmd' );
        $filter_sent      = $app->getUserStateFromRequest( 'filter_ordering_sent', 'filter_ordering_sent','0','int' );
        $filter_pdf       = $app->getUserStateFromRequest( 'filter_ordering_pdf', 'filter_ordering_pdf','0','cmd' );
        $filter_paid      = $app->getUserStateFromRequest( 'filter_ordering_paid', 'filter_ordering_paid','0','cmd' );
        $filter_event     = $app->getUserStateFromRequest( 'filter_ordering_event', 'filter_ordering_event','0','cmd' );

        $search			= $app->getUserStateFromRequest( 'searchbox', 'searchbox', '', 'string' );
        $search			= strtolower( $search );
        $search_name	= $app->getUserStateFromRequest( 'search_name', 'search_name', '', 'cmd');
        $search_name	= strtolower( $search_name );

        $lists['search']= $search;
        $lists['search_name']= $search_name;

        $query = $db->getQuery(true);

        $query->select('*');
        $query->from($db->quoteName('#__ticketstation_tickets'));
        $query->where($db->quoteName('published') . ' = '. $db->quote('1'));

        $db->setQuery($query);

        $ticketlist[]	    = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_SELECTTICKET' ), 'ticketid', 'ticketname' );
        $ticketlist	        = array_merge( $ticketlist, $db->loadObjectList() );
        $lists['ticket']    = HTMLHelper::_('select.genericlist',  $ticketlist, 'filter_ordering_e', 'class="form-select" 
                              onchange="this.form.submit();"', 'ticketid', 'ticketname', intval($filter_order) );

        $query = $db->getQuery(true);

        $query->select(array('eventid', 'eventname'));
        $query->from($db->quoteName('#__ticketstation_events'));

        $db->setQuery($query);

        $events[]	        = HTMLHelper::_('select.option',  '0', Text::_( 'COM_TICKETSTATION_SELECTLIST_EVENT' ), 'eventid', 'eventname' );
        $events	            = array_merge( $events, $db->loadObjectList() );
        $lists['events']    = HTMLHelper::_('select.genericlist',  $events, 'filter_ordering_event', 'class="form-select js-select-submit-on-change active"  
						      onchange="this.form.submit();"', 'eventid', 'eventname', intval($filter_event) );

        ## Getting the items into a variable
        $items		= $this->get('list');
        $pagination = $this->get('Pagination');

        ## Filling the Array() for doors and make a select list for it.
        $sent = array(
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_PLS_SELECT' )),
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_SENT_TICKETS' )),
            '2' => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_UNSENT_TICKETS' )),
        );
        $lists['sent'] = HTMLHelper::_('select.genericList', $sent, 'filter_ordering_sent', ' class="form-select" 
							onchange="this.form.submit();"', 'value', 'text', (int)$filter_sent );

        $pdf_created = array(
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_PLS_SELECT' )),
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_PROCESSED_PDF' )),
            '2' => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_UNPROCESSED_PDF' )),
        );
        $lists['pdf_created'] = HTMLHelper::_('select.genericList', $pdf_created, 'filter_ordering_pdf', ' class="form-select" 
							onchange="this.form.submit();"', 'value', 'text', (int)$filter_pdf );

        ## Filling the Array() for doors and make a select list for it.
        $paid = array(
            '0' => array('value' => '0', 'text' => Text::_( 'COM_TICKETSTATION_SELECTLIST_PAYMENT_STATUS' )),
            '1' => array('value' => '1', 'text' => Text::_( 'COM_TICKETSTATION_PAID' )),
            '2' => array('value' => '2', 'text' => Text::_( 'COM_TICKETSTATION_UNPAID_OVERVIEW' )),
            '3' => array('value' => '3', 'text' => Text::_( 'COM_TICKETSTATION_REFUNDED' )),
            '4' => array('value' => '4', 'text' => Text::_( 'COM_TICKETSTATION_PENDING' )),
        );
        $lists['paid'] = HTMLHelper::_('select.genericList', $paid, 'filter_ordering_paid', 'class="form-select js-select-submit-on-change active"  
						 onchange="this.form.submit();"', 'value', 'text', (int)$filter_paid );

        $this->items        = $items;
        $this->config       = $config;
        $this->pagination   = $pagination;
        $this->lists        = $lists;
		
		
        parent::display($tpl);

    }

    function _displayForm($tpl = null)
    {
        $model = $this->getModel();

        // The order was auto-removed by the ticketcleaner: the row itself is really gone
        // (so front-end availability/reports are unaffected), only a History snapshot
        // remains. Show a read-only summary instead of the normal, interactive order form -
        // none of its actions (resend, mark paid, scan, ...) apply to a deleted order.
        if ($model->isGhostOrder())
        {
            ToolBarHelper::title(Text::_('COM_TICKETSTATION_BOXOFFICE_VIEW_ORDER_DETAILS'), 'fa fa-money-bill-alt');
            ToolBarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');

            $this->config  = $this->get('config');
            $this->history = $this->get('history');
            $this->ghost   = $model->getGhostOverview();

            $this->setLayout('ghost');
            parent::display($tpl);

            return;
        }

        ToolBarHelper::title(Text::_('COM_TICKETSTATION_BOXOFFICE_VIEW_ORDER_DETAILS'), 'fa fa-money-bill-alt');
        ToolbarHelper::custom( 'sendticketcopy', 'mail', '', Text::_( 'COM_TICKETSTATION_TOOLBAR_RESEND_TICKETS' ), false, false);
        ToolbarHelper::custom( 'sendinvoice', 'file-alt', '', Text::_( 'COM_TICKETSTATION_TOOLBAR_SEND_INVOICE' ), false, false);
        ToolbarHelper::custom( 'processticket', 'file-2', '', Text::_( 'COM_TICKETSTATION_TOOLBAR_CREATE_TICKETS' ), false, false);
        ToolbarHelper::custom( 'downloadtickets', 'download', '', Text::_( 'COM_TICKETSTATION_TOOLBAR_DOWNLOAD_TICKETS' ), false, false);
        ToolbarHelper::custom( 'nopayment', 'thumbs-down', '', Text::_( 'COM_TICKETSTATION_TOOLBAR_UNPAID' ), false, false);
        ToolbarHelper::custom( 'payment', 'thumbs-up', '', Text::_( 'COM_TICKETSTATION_TOOLBAR_PAID' ), false, false);
        ToolBarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');

        $config = $this->get('config');
        $remark = $this->get('remark');
        $items = $this->get('client');
        $price = $this->get('price');
        $history = $this->get('history');

        if ($config->pro_installed == 1) {
            $data = $this->get('extdata');
        } else {
            $data = $this->get('data');

        }

        ## Filling the Array() for doors and make a select list for it.
        $paid = array(
            '0' => array('value' => '0', 'text' => Text::_('COM_TICKETSTATION_UNPAID')),
            '1' => array('value' => '1', 'text' => Text::_('COM_TICKETSTATION_PAID')),
        );

        $lists['paid'] = HTMLHelper::_('select.genericList', $paid, 'paid', ' class="inputbox" ' . '', 'value', 'text', $items->paid);

        ## GENERATE QR CODES IF NEEDED
        for ($i = 0, $n = count($data); $i < $n; $i++ ) {

            ## Give give $row the this->item[$i]
            $row = $data[$i];

            $qrcode = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/qrcodes/' . $row->barcode . '.png';
            require_once JPATH_ADMINISTRATOR . '/components/com_ticketstation/src/Helper/ticketcreator.php';

            //checken of QR-code al bestaat, zo niet aanmaken
            if ((!file_exists($qrcode)) && ($row->barcode != '0')) {

                $ticketcreator = new ticketcreator($row->orderid);

                $destinationpath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/qrcodes/' . $row->barcode . '.png';

                //$pdf->get_qr_image_('250', 'fff', $row->barcode, $destinationpath);
                $ticketcreator->get_qr_image_with_logo($row->barcode, '250', $destinationpath);

            }
        }

        $this->qrcode_folder = '/administrator/components/com_ticketstation/tickets/qrcodes/';

        ## Orderprice
        if ($data[0]->transaction_amount > 0)
        {
            $orderprice = $data[0]->transaction_amount;
        }
        else
        {
            $orderprice = 0;
            foreach($data as $item)
            {
                $orderprice = ($orderprice + $item->price);
            }
        }

        $this->data     = $data;
        $this->remark   = $remark;
        $this->items    = $items;
        $this->config   = $config;
        $this->lists    = $lists;
        $this->price    = $price;
        $this->orderprice = $orderprice;
        $this->history  = $history;

        parent::display($tpl);
    
    }
    
}
