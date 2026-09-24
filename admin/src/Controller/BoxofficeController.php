<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use setasign\Fpdi\FPDI_EAN13;
use Ticketstation\Component\Ticketstation\Administrator\Controller\Mixin\RegisterControllerTasks;
use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Input\Input;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Order;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelLow;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelMedium;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelQuartile;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\ImageData;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeEnlarge;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeShrink;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


class BoxofficeController extends BaseController {

    use RegisterControllerTasks;

    /**
     * The default view for the display method.
     *
     * @var string
     */
    protected $default_view = 'Transactions';

    function __construct($config = array(), MVCFactoryInterface $factory = null, CMSApplication $app = null, Input $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        $this->registerTask( 'add' , 'edit' );
        $this->registerTask('unpublish','publish');
        $this->registerTask('apply','save' );
    }

    function display($cachable = false, $urlparams = array())
    {
        $jinput = Factory::getApplication()->getInput();
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
            $app->enqueueMessage('Scanstatus niet aangepast!', 'error');
        }
        else
        {
            $app->enqueueMessage('Reset scanstatus gelukt!');
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
            $app->enqueueMessage('Scanstatus niet aangepast!', 'error');
        }
        else
        {
            $app->enqueueMessage('Gemarkeerd als gescand!');
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

    function refund()
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

        if(!$model->changePaymentState($cid, 2))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENTSTATUS_REFUNDED'));
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

        if(!$model->paymentResender($cid))
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_TICKETBOX'), 'error');
            $this->setRedirect($link);
        }
        else
        {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_PAYMENT_REQUESTS_SENT'));

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

        if(!$model->sendtickets($cid))
        {
            $link = 'index.php?option=com_ticketstation&controller=boxoffice';
            $this->setRedirect($link);
        }
        else
        {
            $link = 'index.php?option=com_ticketstation&controller=boxoffice';
            $this->setRedirect($link, Text::_( 'COM_TICKETSTATION_ITEMS_HAS_BEEN_SENT'));
        }
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
        $ordercode 	= $jinput->get('cid', '0', 'int');
        $newremark	= $jinput->get('newremark', null, 'RAW');

        if ($newremark == '')
        {
            $app->enqueueMessage('Opmerking is leeg!', 'error');

        } else {

            $response = $model->updateinsertRemark($ordercode, $newremark);

            if($response == 'FAIL_NO_DIFF')
            {
                $app->enqueueMessage('Geen wijziging!', 'error');

            } elseif ($response == 'DONE_INSERT')
            {
                $app->enqueueMessage('Invoegen opmerking gelukt!');

            } elseif($response == 'DONE_UPDATE')
            {
                $app->enqueueMessage('Bijwerken opmerking gelukt!');

            } elseif($response == 'FAIL')
            {
                $app->enqueueMessage('Bijwerken opmerking mislukt!', 'error');
            }
        }

        $this->setRedirect('index.php?option=com_ticketstation&controller=boxoffice&task=edit&cid=' . $ordercode);
    }

    function deleteremark()
    {

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $model 		= $this->getModel('boxoffice');
        $ordercode 	= $jinput->get('cid', '0', 'int');

        $response = $model->deleteRemark($ordercode);

        if($response == 'NA')
        {
            $app->enqueueMessage('Opmerking niet aanwezig!', 'error');

        } elseif($response == 'DONE_DELETE')
        {
            $app->enqueueMessage('Verwijderen opmerking gelukt!');

        } elseif($response == 'FAIL')
        {
            $app->enqueueMessage('Verwijderen opmerking mislukt!', 'error');
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
            ->where($db->quoteName('ordercode') . ' = ' . (int)$ordercode);

        $db->setQuery($query);

        $orderids = $db->loadObjectList();


        $multi_ticket = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTickets-' . (int)$ordercode . '.pdf';
        $single_ticket = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTicket-' . $orderids[0]->orderid . '.pdf';
        $filepath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/';

        if (file_exists($multi_ticket)) {
            $filename = 'eTickets-' . (int)$ordercode . '.pdf';
        } else {
            if (file_exists($single_ticket)) {
                $filename = 'eTicket-' . $orderids[0]->orderid . '.pdf';
            } else {
                $app->enqueueMessage('Ticket not present on server! Please create first.', 'error');
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

    function qrcodeNew()
    {
        $app  = Factory::getApplication();
        $jinput = $app->getInput();


        $barcode = 'Dit is een test!';
        $destinationpath = JPATH_SITE . '/administrator/components/com_ticketstation/tickets/qrcodes/test.png';
        $filetype = 'PNG';

        $this->get_qr_image_with_logo($barcode, $destinationpath, $filetype);
    }

    function get_qr_image_with_logo($barcode, $destinationpath, $filetype = 'PNG')
    {

        $qr_width = 300;

        if ($filetype == 'SVG') {
            // Add logo
            $logopath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/qrlogo.svg';
            $logo = Logo::create($logopath)
                ->setResizeToWidth($qr_width / 3.3)
                ->setResizeToHeight(($qr_width / 3.3) / 0.8136) //factor 0,8136 komt voort uit aspect-ratio van Huibuuke logo
                ->setPunchoutBackground(true);
            $writer = new SvgWriter();
        } else {
            // Add logo
            $logopath = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/qrlogo.png';
            $logo = Logo::create($logopath)
                ->setResizeToWidth($qr_width / 4);
            $writer = new PngWriter();
        }

        // Create QR code
        $qrCode = QrCode::create($barcode)
            ->setEncoding(new Encoding('UTF-8'))
            ->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh())
            //->setErrorCorrectionLevel(new ErrorCorrectionLevelQuartile())
            //->setErrorCorrectionLevel(new ErrorCorrectionLevelMedium())
            //->setErrorCorrectionLevel(new ErrorCorrectionLevelLow())
            ->setSize($qr_width)
            ->setMargin(0)
            ->setRoundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->setForegroundColor(new Color(0, 0, 0))
            ->setBackgroundColor(new Color(255, 255, 255));

        //Toevoegen logo momenteel (2-2023) uitgeschakeld ivm scanbaarheid
        //unset($logo);
        $result = $writer->write($qrCode, $logo);

        // Save it to a file
        $result->saveToFile($destinationpath);

    }

}