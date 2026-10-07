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
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Tickets;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Xlsx;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;


class BoxofficeController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Transactions';

    function __construct($config = array(), ?MVCFactoryInterface $factory = null, ?CMSApplication $app = null, ?Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask( 'add' , 'edit' );
        $this->registerTask('unpublish','publish');
        $this->registerTask('apply','save' );
    }

    function display($cachable = false, $urlparams = array())
    {
        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        // A search that was just submitted and points at exactly one order (its ordercode, or
        // the code of one of its tickets as a QR scanner types it) opens that order straight
        // away. The search is cleared, so the list is complete again after closing the order.
        $search = trim($jinput->post->getString('searchbox', ''));

        if ($search !== '')
        {
            $ordercode = $this->getModel('boxoffice')->findExactOrder($search);

            if ($ordercode !== null)
            {
                $app->setUserState('com_ticketstation.boxoffice.search', '');
                $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . (int) $ordercode);

                return $this;
            }
        }

        $jinput->set('layout', 'default');
        $jinput->set('view', 'boxoffice');
        parent::display();
    }

    function edit($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'form');
        $jinput->set('view', 'boxoffice');
        parent::display();
    }

    public function cancel($cachable = false, $urlparams = []) {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=boxoffice');
    }

    public function reservation($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=reservation');
    }

    public function controlpanel($cachable = false, $urlparams = [])
    {
        $this->setRedirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

    function removeSingleOrder()
    {

        $app	= Factory::getApplication();
        $jinput = $app->getInput();
        $link 	= 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');
        $ordercode 	= $jinput->get('ordercode', '0', 'int');

        if(!$model->removeOrderID($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX_DELETE_ORDERID'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ORDERID_REMOVED'));
        }

        $tickets_left = (new Order)->getNumberOfTicketsInOrder($ordercode);

        if ($tickets_left > 0) {

            $this->processticket();
            $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);

        } else {
            $this->setRedirect($link);
        }

    }

    function resetscanstate()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $link 	= 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        $ordercode 	= $jinput->get('ordercode', '0', 'int');

        if(!$model->resetScanstate($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SCANSTATUS_NOT_CHANGED'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SCANSTATUS_RESET'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function markasscanned()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $link 	= 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        $ordercode 	= $jinput->get('ordercode', '0', 'int');

        if(!$model->markasScanned($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SCANSTATUS_NOT_CHANGED'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_MARKED_AS_SCANNED'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function full_process()
    {


        $app 	= Factory::getApplication();
        $link 	= 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        if(!$model->fullprocessorder($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COMPLETED_ORDER'));
        }

        $this->setRedirect($link);
    }

    function allpayments()
    {


        $app 	= Factory::getApplication();
        $link 	= 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        if(!$model->changePaymentState($cid, 1))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENTS_PROCESSED'));
        }

        $this->setRedirect($link);
    }

    function unlock()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $link   = 'index.php?option=com_ticketstation&controller=boxoffice';

        $ordercode 	= $jinput->get('ordercode', '0', 'int');
        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        if(!$model->blacklistTicket($cid, 0))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_HAS_BEEN_UNBLOCKED'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function blocked()
    {


        $app    = Factory::getApplication();
        $jinput = $app->getInput();
        $link   = 'index.php?option=com_ticketstation&controller=boxoffice';

        $ordercode 	= $jinput->get('ordercode', '0', 'int');
        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        if(!$model->blacklistTicket($cid, 1))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_HAS_BEEN_BLOCKED'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function resendpayment()
    {

        $app  = Factory::getApplication();
        $link = 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        // A payment link can't be paid while online payments are off.
        if (!Shop::paymentsOn())
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_REQUESTS_PAYMENTS_OFF'), 'error');
        }
        elseif(!$model->paymentResender($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            if (count($model->skippedPaymentRequests) < count($cid))
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_REQUESTS_SENT'));
            }

            if ($model->skippedPaymentRequests)
            {
                $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_PAYMENT_REQUESTS_SKIPPED', implode(', ', $model->skippedPaymentRequests)), 'warning');
            }
        }

        $this->setRedirect($link);
    }

    function payment()
    {

        $app  = Factory::getApplication();
        $jinput = $app->getInput();

        $ordercode 	= $jinput->get('ordercode', '0', 'int');
        $link = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode;

        $model = $this->getModel('boxoffice');

        if(!$model->paymentprocessor($ordercode, 1))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_PROCESSED'));
        }

        $this->setRedirect($link);
    }

    function nopayment()
    {

        $app  = Factory::getApplication();
        $jinput = $app->getInput();

        $ordercode 	= $jinput->get('ordercode', '0', 'int');
        $link = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode;

        $model = $this->getModel('boxoffice');

        if(!$model->paymentprocessor($ordercode, 0))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_REMOVED'));
        }

        $this->setRedirect($link);
    }

    function processticket()
    {

        $app  = Factory::getApplication();
        $jinput = $app->getInput();

        $ordercode 	= $jinput->get('ordercode', '0', 'int');

        $model = $this->getModel('boxoffice');
        $link = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode;

        if(!$model->ticketprocessor($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETS_CREATED'));
        }

        $this->setRedirect($link);

    }

    function sendingticket()
    {

        $app  = Factory::getApplication();
        $link = 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1)
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
            $this->setRedirect($link);
        }

        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query->select(array('persending'));
        $query->from($db->quoteName('#__ticketstation_config'));
        $query->where($db->quoteName('configid') . ' = 1');

        $db->setQuery($query);
        $config = $db->loadObject();

        if (count( $cid ) > $config->persending)
        {
            $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_TO_MUCH_ITEMS').' '.$config->persending.' '.Text::_( 'COM_TICKETSTATION_TO_SEND_AT_ONCE'), 'error');
            $app->redirect($link);
        }

        $model = $this->getModel('boxoffice');

        if(!$model->sendTicketsForOrders($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
        }
        else
        {
            if (count($model->skippedTicketMails) < count($cid))
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_ITEMS_HAS_BEEN_SENT'));
            }

            if ($model->skippedTicketMails)
            {
                $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_BOXOFFICE_TICKETS_NOT_SENT_SKIPPED', implode(', ', $model->skippedTicketMails)), 'warning');
            }
        }

        $this->setRedirect($link);
    }

    /**
     * Downloads the tickets of the orders in the list (with its filters and search) as a CSV
     * file, one line per ticket: a guest list. With the event filter on, only the tickets for
     * that event. Orders removed by the automatic cleanup are left out.
     */
    function export()
    {
        $this->download('csv');
    }

    /**
     * The same guest list as an Excel file (.xlsx): phone numbers and codes stay text, so a
     * leading zero is kept, and the price is a number.
     */
    function exportxlsx()
    {
        $this->download('xlsx');
    }

    private function download(string $format)
    {
        $app    = Factory::getApplication();
        $rows   = $this->getModel('boxoffice')->getExportRows();
        $config = (new Config)->getPartialConfig(['dateformat', 'time_format']);

        // The dates are written as on screen: the notation of the Configuration, and the calendar
        // of the site language
        $dateTime = trim(($config->dateformat ?: 'd-m-Y') . ' ' . ($config->time_format ?: 'H:i'));

        $status = [
            0 => Text::_('COM_TICKETSTATION_UNPAID_OVERVIEW'),
            1 => Text::_('COM_TICKETSTATION_PAID'),
            2 => Text::_('COM_TICKETSTATION_REFUNDED'),
            3 => Text::_('COM_TICKETSTATION_PENDING'),
        ];

        $header = [
            Text::_('COM_TICKETSTATION_ORDERCODE'),
            Text::_('COM_TICKETSTATION_ORDERDATE'),
            Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT_NAME'),
            Text::_('COM_TICKETSTATION_EMAILADDRESS'),
            Text::_('COM_TICKETSTATION_PHONENUMBER'),
            Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT_EVENT'),
            Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT_TICKET'),
            Text::_('COM_TICKETSTATION_SEAT'),
            Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT_PRICE'),
            Text::_('COM_TICKETSTATION_BOXOFFICE_PAYMENT_STATUS'),
            Text::_('COM_TICKETSTATION_BOXOFFICE_SCANNED'),
            Text::_('COM_TICKETSTATION_BOXOFFICE_BLACKLIST'),
            Text::_('COM_TICKETSTATION_ORDERREFERENCE'),
        ];

        $lines = [];

        foreach ($rows as $row)
        {
            $lines[] = [
                $row->ordercode,
                Date::screen($row->orderdate, $dateTime),
                $row->name,
                $row->emailaddress,
                $row->phonenumber,
                $row->eventname,
                $row->ticketname,
                $row->seatid ? $row->row_name . $row->seatid : '',
                $format === 'xlsx' ? (float) $row->price : number_format((float) $row->price, 2, ',', ''),
                $status[(int) $row->paid] ?? '',
                $row->scanned ? Date::display($row->scandate, $dateTime) : '',
                $row->blacklisted ? Text::_('COM_TICKETSTATION_YES') : '',
                $row->remarks,
            ];
        }

        $name = 'boxoffice-' . date('Y-m-d-His');

        if ($format === 'xlsx')
        {
            $file = Xlsx::build($header, $lines, Factory::getDocument()->getDirection() === 'rtl', Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT'));

            if ($file === '')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_BOXOFFICE_EXPORT_XLSX_FAILED'), 'error');
                $this->setRedirect('index.php?option=com_ticketstation&view=boxoffice');

                return;
            }

            $app->setHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', true);
            $app->setHeader('Content-Disposition', 'attachment; filename="' . $name . '.xlsx"', true);
            $app->setHeader('Cache-Control', 'no-store', true);
            $app->sendHeaders();

            echo $file;

            $app->close();
        }

        // A value starting with one of these would be read as a formula by a spreadsheet.
        $cell = static function ($value) {
            $value = (string) $value;

            return $value !== '' && strpbrk($value[0], "=+-@\t\r") !== false ? "'" . $value : $value;
        };

        $out = fopen('php://temp', 'r+');

        // A byte order mark, so spreadsheet programs read the file as UTF-8.
        fwrite($out, "\xEF\xBB\xBF");

        // The escape character is given explicitly (PHP 8.4 asks for it); empty means none
        fputcsv($out, $header, ';', '"', '');

        foreach ($lines as $line)
        {
            fputcsv($out, array_map($cell, $line), ';', '"', '');
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        $app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $app->setHeader('Content-Disposition', 'attachment; filename="' . $name . '.csv"', true);
        $app->setHeader('Cache-Control', 'no-store', true);
        $app->sendHeaders();

        echo $csv;

        $app->close();
    }

    /**
     * Complete Order for the order that is open: marks it as paid, creates the tickets and
     * emails them, as the Complete Order action in the list does.
     */
    function completeorder()
    {
        $app       = Factory::getApplication();
        $ordercode = $app->getInput()->get('ordercode', 0, 'int');

        if (!$this->getModel('boxoffice')->fullprocessorder([$ordercode]))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COMPLETED_ORDER'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    /**
     * Gives the ticked tickets of the order that is open a new QR code (a lost or stolen
     * ticket): the copies sent earlier no longer scan.
     */
    function renewcodes()
    {
        $app       = Factory::getApplication();
        $ordercode = $app->getInput()->get('ordercode', 0, 'int');
        $cid       = $this->input->get('cid', [], 'array');

        if (!$this->getModel('boxoffice')->renewTicketCodes($ordercode, $cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PLEASE_SELECT_ORDER'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_BOXOFFICE_CODES_RENEWED'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    /**
     * Resend Payment for the order that is open.
     */
    function sendpaymentrequest()
    {
        $app       = Factory::getApplication();
        $ordercode = $app->getInput()->get('ordercode', 0, 'int');
        $model     = $this->getModel('boxoffice');

        // A payment link can't be paid while online payments are off.
        if (!Shop::paymentsOn())
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_REQUESTS_PAYMENTS_OFF'), 'error');
        }
        elseif (!$model->paymentResender([$ordercode]))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
        }
        elseif ($model->skippedPaymentRequests)
        {
            $app->enqueueMessage(Text::sprintf('COM_TICKETSTATION_PAYMENT_REQUESTS_SKIPPED', $ordercode), 'warning');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_REQUESTS_SENT'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    /**
     * The refund screen of an order: a new refund, or with &refund=<id> the decision about the
     * tickets for a refund or chargeback Mollie reported.
     */
    function refundform()
    {
        $jinput = Factory::getApplication()->getInput();
        $jinput->set('layout', 'refund');
        $jinput->set('view', 'boxoffice');
        parent::display();
    }

    /**
     * Saves the refund screen: makes the refund (at Mollie, or by hand) or records the decision,
     * and gives the tickets their treatment.
     */
    function saverefund()
    {
        $app        = Factory::getApplication();
        $jinput     = $app->getInput();
        $ordercode  = $jinput->get('ordercode', 0, 'int');
        $refundId   = $jinput->get('refund_id', 0, 'int');
        $treatments = array_map('intval', (array) $jinput->get('treatment', [], 'array'));
        $order      = 'index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode;

        try
        {
            if ($refundId)
            {
                Refund::decide($ordercode, $refundId, $treatments);
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REFUND_DECIDED'));
            }
            else
            {
                $amount   = (float) str_replace(',', '.', trim((string) $jinput->get('amount', '', 'string')));
                $refundId = Refund::create($ordercode, $amount, (string) $jinput->get('description', '', 'string'), $treatments);
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REFUND_CREATED'));
            }

            // Tickets that wait until Mollie has processed the refund.
            $refund = Refund::get($refundId);

            if ($refund && Refund::isWaiting($refund))
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REFUND_TICKETS_WAITING'), 'notice');
            }
        }
        catch (\RuntimeException $e)
        {
            $app->enqueueMessage($e->getMessage(), 'error');
            $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=refundform&cid=' . $ordercode . ($refundId ? '&refund=' . $refundId : ''));

            return;
        }

        $this->setRedirect($order);
    }

    /**
     * Fetches the refunds and chargebacks of the order's payment from Mollie, for a webhook that
     * never arrived or refunds made before 2.9.0.
     */
    function syncrefunds()
    {
        $app       = Factory::getApplication();
        $ordercode = $app->getInput()->get('ordercode', 0, 'int');

        try
        {
            $new = Refund::sync($ordercode);
            $app->enqueueMessage($new ? Text::plural('COM_TICKETSTATION_REFUND_SYNC_N_NEW', $new) : Text::_('COM_TICKETSTATION_REFUND_SYNC_NONE'));
        }
        catch (\RuntimeException $e)
        {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    /**
     * Takes a failed refund or reversed chargeback off the "Needs attention" list.
     */
    function acknowledgerefund()
    {
        $app       = Factory::getApplication();
        $jinput    = $app->getInput();
        $ordercode = $jinput->get('ordercode', 0, 'int');

        try
        {
            Refund::acknowledge($ordercode, $jinput->get('refund_id', 0, 'int'));
        }
        catch (\RuntimeException $e)
        {
            $app->enqueueMessage($e->getMessage(), 'error');
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function sendticketcopy()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $model = $this->getModel('boxoffice');
        $ordercode 	= $jinput->get('ordercode', '0', 'int');

        //if(!$model->ticketprocessor([$ordercode]))
        //{
        //	$app->enqueueMessage(Text::_( 'COM_TICKETSTATION_ERROR_SENDING_ITEMS'), 'error');
        //}
        //else
        //{
        if(!$model->reSendTickets($ordercode))
        {
            $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_ERROR_SENDING_ITEMS'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_( 'COM_TICKETSTATION_TICKET_COPIES_SENT'));
        }
        //}

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }


    /**
     * Manually generates (or re-uses the existing) invoice for an order and emails it -
     * available regardless of the "send automatically" config setting, so a single order can
     * still get an invoice on request.
     */
    function sendinvoice()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $model     = $this->getModel('boxoffice');
        $ordercode = $jinput->get('ordercode', '0', 'int');

        if ( ! $model->sendInvoiceForOrder($ordercode))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_SENDING_INVOICE'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_INVOICE_SENT'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }


    function updateinsertremark()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $model 		= $this->getModel('boxoffice');
        $ordercode 	= $jinput->get('ordercode', '0', 'int');
        $newremark	= trim((string) $jinput->get('newremark', '', 'STRING'));

        if ($newremark == '')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_EMPTY'), 'error');

        } else {

            $response = $model->updateinsertRemark($ordercode, $newremark);

            if($response == 'FAIL_NO_DIFF')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_NOT_CHANGED'), 'error');

            } elseif ($response == 'DONE_INSERT')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_ADDED'));

            } elseif($response == 'DONE_UPDATE')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_UPDATED'));

            } elseif($response == 'FAIL')
            {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_UPDATE_FAILED'), 'error');
            }
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function deleteremark()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $model 		= $this->getModel('boxoffice');
        $ordercode 	= $jinput->get('ordercode', '0', 'int');

        $response = $model->deleteRemark($ordercode);

        if($response == 'NA')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_NOT_PRESENT'), 'error');

        } elseif($response == 'DONE_DELETE')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_DELETED'));

        } elseif($response == 'FAIL')
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_REMARK_DELETE_FAILED'), 'error');
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function publish()
    {

        $app  = Factory::getApplication();
        $link = 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1) {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETS_PUBLISH_NO_ITEMS'), 'error');
            $this->setRedirect($link);
        }

        $publish = ($this->getTask() == 'publish') ? 1 : 0;

        $model = $this->getModel('boxoffice');

        if(!$model->publish($cid, $publish))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETS_PUBLISH_ERROR'), 'error');
            $this->setRedirect($link);
        }

        $this->setRedirect($link);
    }

    function remove()
    {


        $app  = Factory::getApplication();
        $link = 'index.php?option=com_ticketstation&controller=boxoffice';

        $cid = $this->input->get('cid', array(), 'array');
        ArrayHelper::toInteger($cid);

        if (count( $cid ) < 1) {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETS_REMOVE_NO_ITEMS'), 'error');
            $this->setRedirect($link);
        }

        $model = $this->getModel('boxoffice');

        if(!$model->removeTickets($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_COULD_NOT_REMOVE_ORDERS'), 'error');
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKETS_REMOVED'));
        }

        $this->setRedirect('index.php?option=com_ticketstation&view=boxoffice');

    }

    function downloadtickets()
    {
        $app = Factory::getApplication();
        $jinput = $app->getInput();
        $ordercode = $jinput->get('ordercode', '0', 'int');

        // Get orderid's from the database.
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . (int)$ordercode)
            ->where(Refund::validSql());

        $db->setQuery($query);

        $orderids = $db->loadObjectList();

        if (!$orderids) {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_PDF_NOT_PRESENT'), 'error');
            $app->redirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
        }


        $multi_ticket = Tickets::combinedPath($ordercode);
        $single_ticket = Tickets::singlePath($ordercode);
        $filepath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/';

        if (file_exists($multi_ticket)) {
            $filename = Tickets::combinedName($ordercode);
        } else {
            if (file_exists($single_ticket)) {
                $filename = Tickets::singleName($ordercode);
            } else {
                $app->enqueueMessage(Text::_('COM_TICKETSTATION_TICKET_PDF_NOT_PRESENT'), 'error');
                $app->redirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
            }
        }

        // get the file mime type using the file extension
        switch (strtolower(substr(strrchr($filepath . $filename, '.'), 1))) {
            case 'pdf':
                $mime = 'application/pdf';
                break;
            case 'zip':
                $mime = 'application/zip';
                break;
            case 'jpeg':
            case 'jpg':
                $mime = 'image/jpg';
                break;
            default:
                $mime = 'application/force-download';
        }

        header('Pragma: public');    // required
        header('Expires: 0');        // no cache
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filepath . $filename)) . ' GMT');
        header('Cache-Control: private', false);
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($filepath . $filename));    // provide file size
        header('Connection: close');
        readfile($filepath . $filename);        // push it out
        exit();
    }

}