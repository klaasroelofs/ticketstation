<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Mollie\Api\MollieApiClient;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\eTicketsMessage;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;
use Ticketstation\Component\Ticketstation\Site\View\Paymentresult\HtmlView;

/**
 * Ticketstation Payment Controller
 * @since  0.2.11
 */
class PaymentresultController extends BaseController
{
    private $ordercode;
    private $mollieconfig;

    function __construct()
    {
        parent::__construct();

        $jinput = Factory::getApplication()->getInput();
        $db = Factory::getContainer()->get('DatabaseDriver');

        // Get Ordercode
        $this->ordercode = $jinput->get('ordercode', '0', 'int');

        // Get Mollie config from database
        $query = 'SELECT * FROM #__ticketstation_mollie WHERE configid = 1';

        $db->setQuery($query);
        $this->mollieconfig = $db->loadObject();

    }

    function downloadTicketAfterPurchase()
    {
        $jinput   = Factory::getApplication()->getInput();
        $ordercode = $jinput->get('order', '0', 'int');

        // Only the browser session that just completed this specific order may download
        // its ticket - otherwise guessing an ordercode would be enough to get someone
        // else's ticket PDF (and the QR code printed on it).
        $session = Factory::getApplication()->getSession();
        $authorized_ordercode = $session->get('ticketstation.authorized_ordercode');

        if ($authorized_ordercode === null || (int) $authorized_ordercode !== $ordercode)
        {
            exit(Text::sprintf('COM_TICKETSTATION_DOWNLOAD_NOT_AUTHORIZED', (new Config)->getContactEmail()));
        }

        // Get orderid's from the database.
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' = ' . (int) $ordercode);

        $db->setQuery($query);

        $orderids = $db->loadObjectList();

        if ($orderids[0]->downloaded != 1) {

            //Mark downloaded
            $this->MarkDownloaded($ordercode);

            $multi_ticket = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/eTickets-' . (int) $ordercode . '.pdf';
            $filepath     = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/';

            if ( ! file_exists($multi_ticket))
            {
                $filename = 'eTicket-' . $orderids[0]->orderid . '.pdf';
            }
            else
            {
                $filename = 'eTickets-' . (int) $ordercode . '.pdf';
            }

            // get the file mime type using the file extension
            switch (strtolower(substr(strrchr($filepath . $filename, '.'), 1)))
            {
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

        } else {

            $itemid = TicketstationFunctions::getSiteItemid();
            Factory::getApplication()->redirect(Route::_('index.php?option=com_ticketstation&view=paymentresult&ordercode=' . $ordercode . ($itemid ? '&Itemid=' . $itemid : '')));
            exit();

        }

    }

    function MarkDownloaded($ordercode)
    {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('downloaded') . ' = 1',
        ];

        $conditions = [$db->quoteName('ordercode') . ' = ' . $ordercode];

        $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

        $db->setQuery($query);

        $db->execute();

    }

    public function return()
    {
        $app = Factory::getApplication();
        $input = $app->getInput();

        $orderCode = $input->getString('ordercode');

        // Direct view-class instantiëren
        $view = new HtmlView();

        // Layout instellen
        $view->setLayout('wait');

        // Variabelen doorgeven
        $view->orderCode = $orderCode;

        // Renderen
        $view->display();

        $app->close();
    }

    public function poll()
    {
        $app   = Factory::getApplication();
        $input = $app->getInput();
        $db = Factory::getContainer()->get('DatabaseDriver');

        $orderCode = $input->getString('ordercode');

        $query = $db->getQuery(true)
            ->select($db->quoteName('processed'))
            ->from($db->quoteName('#__ticketstation_transactions_temp'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote($orderCode));

        $db->setQuery($query);
        $processed = (int) $db->loadResult();

        echo json_encode([
            'processed' => $processed === 1 || $processed === 5
        ]);

        $app->close();
    }


}	