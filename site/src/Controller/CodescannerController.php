<?php


namespace Ticketstation\Component\Ticketstation\Site\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Barcode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Scanner;

defined('_JEXEC') or die;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticket validation at the entrance.
 *
 * Two entry points share the same checks (validate()):
 *  - task=scan:       the browser scanner (view=ticketscanner). Authenticated by the
 *                     logged-in Joomla user's scanner assignment; CSRF-protected by the
 *                     central Dispatcher gate. Answers JSON.
 *  - task=validation: scanning hardware. Authenticated by the scanner's API key
 *                     (X-Scanner-Key header, or key= parameter). Exempt from the CSRF
 *                     gate. Answers the XML format the hardware expects.
 *
 * Either way a scanner can only validate tickets of the events/tickets assigned to it.
 */
class CodescannerController extends BaseController
{
	/**
	 * Browser scanner: validate a code for the logged-in scanner user.
	 *
	 * @return void
	 *
	 * @since 2.2.1
	 */
	public function scan()
	{
		$user    = $this->app->getIdentity();
		$scanner = $user && !$user->guest ? Scanner::getByUserId((int) $user->id) : null;

		if (!$scanner)
		{
			$this->outputJSON($this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_UNAUTHORIZED')), 403);
		}

		$this->outputJSON($this->validate($scanner, (int) $user->id));
	}

	/**
	 * Scanning hardware: validate a code for the scanner owning the API key.
	 *
	 * @since 1.0.0
	 */
	public function validation()
	{
		$key = $this->input->server->getString('HTTP_X_SCANNER_KEY', '');

		if ($key === '')
		{
			$key = $this->input->getString('key', '');
		}

		$scanner = Scanner::getByApiKey($key);

		if (!$scanner)
		{
			$this->outputXML($this->result(0, 'Unauthorized'));
		}

		$this->outputXML($this->validate($scanner, $scanner->userid));
	}

	/**
	 * Runs all checks on the submitted code and, when the ticket is valid, marks it scanned.
	 *
	 * Input: tid (QR code content, or an order id typed in by hand) and optionally
	 * eventid or ticketid (what the scanner page is scanning for).
	 *
	 * @param   object  $scanner  Scanner assignment (see Scanner helper)
	 * @param   int     $userid   User id recorded as the scanner of the ticket
	 *
	 * @return  array  See result()
	 */
	private function validate(object $scanner, int $userid): array
	{
		$code     = trim($this->input->getString('tid', ''));
		$eventid  = $this->input->getInt('eventid', 0);
		$ticketid = $this->input->getInt('ticketid', 0);

		// The page must be scanning for something assigned to this scanner.
		if (($eventid > 0 && !Scanner::mayScanEvent($scanner, $eventid))
			|| ($ticketid > 0 && !Scanner::mayScanTicket($scanner, $ticketid)))
		{
			return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_NOT_ASSIGNED'));
		}

		if ($code === '')
		{
			return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_NO_CODE'));
		}

		$orderHelper = new Order;

		if (ctype_digit($code) && strlen($code) <= 9)
		{
			// Only digits: an order id typed in by hand (QR codes are 32 hex characters).
			if (!$scanner->manual_entry)
			{
				return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_MANUAL_NOT_ALLOWED'));
			}

			$data = $orderHelper->getOrderByOrderId((int) $code);
		}
		else
		{
			if (!(new Barcode)->validate($code))
			{
				return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_INVALID_BARCODE_FORMAT'));
			}

			$data = $orderHelper->getOrderByBarcode($code);
		}

		if (!$data)
		{
			return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_INVALID_BARCODE'));
		}

		$order         = $data->ordercode . '-' . $data->orderid;
		$orderEventid  = (int) $data->eventid;
		$orderTicketid = (int) $data->ticketid;

		if ($eventid > 0 && $orderEventid !== $eventid)
		{
			return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_WRONG_EVENT'), $order);
		}

		if ($ticketid > 0 && $orderTicketid !== $ticketid)
		{
			return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_WRONG_TICKET'), $order);
		}

		// Without an eventid/ticketid (hardware) the ticket itself must be within the assignment.
		if (!Scanner::mayScanTicket($scanner, $orderTicketid, $orderEventid))
		{
			return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_NOT_ASSIGNED'), $order);
		}

		if ((int) $data->blacklisted === 1)
		{
			return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_BLACLISTED_BARCODE'), $order);
		}

		if ((int) $data->scanned === 1)
		{
			return $this->result(0, $this->scannedBeforeText($data->scandate), $order);
		}

		switch ((int) $data->paid)
		{
			case 1:
				break;
			case 0:
				return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_UNPAID_TICKET'), $order);
			case 2:
				return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_REFUNDED_TICKET'), $order);
			case 3:
				return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_PENDING_TICKET'), $order);
			default:
				return $this->result(0, Text::_('COM_TICKETSTATION_TICKETSCANNING_INVALID_STATUS'), $order);
		}

		if (!(new Barcode)->claimScan((int) $data->orderid, $userid))
		{
			// Scanned elsewhere between reading and updating it.
			$data = $orderHelper->getOrderByOrderId((int) $data->orderid);

			return $this->result(0, $this->scannedBeforeText($data->scandate ?? null), $order);
		}

		$total = $ticketid > 0
			? $orderHelper->getNumberofTicketsScanned($ticketid, 0)
			: $orderHelper->getNumberofTicketsScanned(0, $eventid > 0 ? $eventid : $orderEventid);

		return $this->result(1, Text::_('COM_TICKETSTATION_TICKETSCANNING_APPROVED'), $order, (int) $total);
	}

	private function scannedBeforeText(?string $scandate): string
	{
		if (empty($scandate))
		{
			return Text::_('COM_TICKETSTATION_TICKETSCANNING_SCANNED_BEFORE');
		}

		return Text::sprintf('COM_TICKETSTATION_TICKETSCANNING_SCANNED_BEFORE_AT', date('H:i', strtotime($scandate)));
	}

	private function result(int $status, string $text, string $order = '', int $totalscanned = 0): array
	{
		return [
			'status'       => $status,
			'text'         => $text,
			'order'        => $order,
			'totalscanned' => $totalscanned,
		];
	}

	/**
	 * Answer for the browser scanner.
	 */
	private function outputJSON(array $result, int $httpStatus = 200)
	{
		$this->app->setHeader('status', $httpStatus, true);
		$this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
		$this->app->setHeader('Cache-Control', 'no-store', true);
		$this->app->sendHeaders();

		echo json_encode($result);

		$this->app->close();
	}

	/**
	 * Answer for scanning hardware. The element layout is unchanged from earlier
	 * versions so existing devices keep working ('FOUT' marks "no order", getscoins
	 * is no longer used and always 'GEEN').
	 */
	private function outputXML(array $result)
	{
		$esc = fn ($value) => htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

		$this->app->setHeader('Content-Type', 'text/xml; charset=UTF-8', true);
		$this->app->setHeader('Cache-Control', 'no-store', true);
		$this->app->sendHeaders();

		echo '<?xml version="1.0" encoding="UTF-8"?>';
		echo '<xml>';
		echo '    <message>';
		echo '        <status>' . $esc($result['status']) . '</status>';
		echo '        <text>' . $esc($result['text']) . '</text>';
		echo '        <order>' . $esc($result['order'] !== '' ? $result['order'] : 'FOUT') . '</order>';
		echo '        <totalscanned>' . $esc($result['totalscanned']) . '</totalscanned>';
		echo '        <getscoins>GEEN</getscoins>';
		echo '    </message>';
		echo '</xml>';

		$this->app->close();
	}
}
