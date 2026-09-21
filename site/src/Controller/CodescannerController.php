<?php


namespace Ticketstation\Component\Ticketstation\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Barcode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;

defined('_JEXEC') or die;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

class CodescannerController extends BaseController
{
	private $barcode;
	private $ticketid;
	private $eventid;

	function __construct()
	{
		parent::__construct();

		$jinput = Factory::getApplication()->getInput();

		$this->barcode   = $jinput->get('tid', '', 'string');
		$this->ticketid  = $jinput->get('ticketid', '0', 'int');
		$this->eventid   = $jinput->get('eventid', '0', 'int');
	}

	/**
	 * Outputting the XML to the server.
	 *
	 * @param $status
	 * @param $msg
	 *
	 * @since 1.0.0
	 */
	public function outputXML($status, $text, $order = 0, $totalscanned = 0, $getscoins = 'GEEN')
	{
        header("Access-Control-Allow-Origin: *");
        header("Content-Type: text/xml; charset=UTF-8");

		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<xml>';
		echo '    <message>';
		echo '        <status>' . $status . '</status>';
		echo '        <text>' . $text . '</text>';
		echo '        <order>' . $order . '</order>';
		echo '        <totalscanned>' . $totalscanned . '</totalscanned>';
		echo '        <getscoins>' . $getscoins . '</getscoins>';
		echo '    </message>';
		echo '</xml>';

		exit();
	}
	
	/**
	 * Validates a barcode sent.
	 *
	 * @since version
	 */
	function validation()
	{
		// Instantiate the class.
		$barcode = new Barcode;

		if (empty($this->barcode))
		{
			$this->outputXML('0', 'Geen QR-code ontvangen!', 'FOUT');
			//$this->outputXML('0', 'Barcode was not sent', $this->barcode);
			
		}
		
		if (strlen($this->barcode) == 5) { 
			
			// An orderid is submitted via manual entry by the scanner.
			// Obtaining the order by orderid.
			$data = (new Order)->getOrderByOrderId($this->barcode);
			$order = $data->ordercode . '-' . $data->orderid;
			$this->barcode = $data->barcode;
			
		} else {
		
			// Validating the barcode.
			if (!$barcode->validate($this->barcode))
			{
				$this->outputXML('0', Text::_('COM_TICKETSTATION_TICKETSCANNING_INVALID_BARCODE_FORMAT'), 'FOUT');
				
			}

			// Obtaining the order by barcode.
			$data = (new Order)->getOrderByBarcode($this->barcode);
			$order = $data->ordercode . '-' . $data->orderid;
			
		}
		
		if (!$data)
		{
			$this->outputXML('0', Text::_('COM_TICKETSTATION_TICKETSCANNING_INVALID_BARCODE'), 'FOUT');
			
		}

		// Checking the event which has been returned.
		if ($this->eventid != 0 && $data->eventid != $this->eventid)
		{
			$this->outputXML('0', Text::_('COM_TICKETSTATION_TICKETSCANNING_WRONG_EVENT'), $order);
			
		}

		// Checking the ticket id against the order object.
		if ($this->ticketid != 0 && $data->ticketid != $this->ticketid)
		{
			$this->outputXML('0', Text::_('COM_TICKETSTATION_TICKETSCANNING_WRONG_TICKET'), $order);
			
		}

		if ($data->blacklisted == 1)
		{
			$this->outputXML('0', Text::_('COM_TICKETSTATION_TICKETSCANNING_BLACLISTED_BARCODE'), $order);

		}

		if ($data->scanned == 1)
		{
			$this->outputXML('0', Text::_('COM_TICKETSTATION_TICKETSCANNING_SCANNED_BEFORE') . ' (om ' . date('H:i', strtotime($data->scandate)) . ')', $order);

		}

		if ($data->paid == 0)
		{
			$this->outputXML('0', Text::_('COM_TICKETSTATION_TICKETSCANNING_UNPAID_TICKET'), $order);
			
		}
		
		if ($data->paid == 1)
		{
			// Updating the scanning state.
			$barcode->updateScanningState($this->barcode, 1);

			if ($this->ticketid != 0) {
				$total_scanned = (new Order)->getNumberofTicketsScanned($data->ticketid, 0);
			} elseif ($this->eventid != 0) {
				$total_scanned = (new Order)->getNumberofTicketsScanned(0, $data->eventid);
			} else {
				$total_scanned = 0;
			}
			
			$msg = 'Ticket goedgekeurd';
			
			if ($data->ticketid == 18) {
				$getscoins = '4';
			} else {
				$getscoins = 'GEEN';
			}
			
			$this->outputXML(1, $msg, $order, $total_scanned, $getscoins);
			
		}
		
		
		
	}
}