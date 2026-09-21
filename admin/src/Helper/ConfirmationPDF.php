<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;


defined('_JEXEC') or die('Restricted access');

class ConfirmationPDF extends Pdf
{
    private $config;
    private $db;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Creating a PDF confirmation fo the customer.
     *
     * @since 1.0.0
     */
    public function create($ordercode)
    {
        // Check the library.
        if ( ! $this->checkLibrary())
        {
            return false;
        }

        // Setting BIG Selcts on
        if ( ! $this->setBigSelects())
        {
            return false;
        }

        // Obtain and check userid.
        $userid = (new Order)->getUserByOrderCode($ordercode);

        if ($userid == 0)
        {
            return false;
        }

        // Getting the default variables from the order.
        $variables = (new Payment($ordercode))->getDefaultVariablesForTemplates($ordercode, $userid);

        // Getting all items from the database and add it to the variable array
        $variables['ordered_items'] = $this->getOrderlist($ordercode, $userid);

        // Instantiate message class
        $message = new eTicketsMessage;

        // Combine the orderlist and template.
        $template = $message->id(150)
            ->user($userid)
            ->variables($variables)
            ->getBody();

        $template = $this->replaceImageSources($template);
        $pdf_path = Uri::root() . '/administrator/components/com_ticketstation/tickets/confirmation/' . $ordercode . '.pdf';

        $mpdf = new \mPDF('utf-8', 'A4-P');
        $mpdf->WriteHTML($template);
        $mpdf->Output($pdf_path, 'F');

        return true;
    }
}