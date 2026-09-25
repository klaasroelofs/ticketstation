<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


## no direct access
use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;


defined('_JEXEC') or die('Restricted access');

class Barcode
{
    /**
     * Checking a barcode which came in.
     *
     * @param $barcode
     *
     * @return bool
     *
     * @since 1.0.0
     */
    function validate($barcode)
    {

        //Check if barcode has only valid Base64 characters
        if (!preg_match('/^[a-zA-Z0-9\/\r\n+]*={0,2}$/', $barcode)) {

            //if it doesn't, it's probably an 'old' MD5 hashed barcode of 32 characters
            if (strlen($barcode) <> 32) {
                return false;
            }
        }

        return true;

    }

    /**
     * Marks a paid, not yet scanned ticket as scanned.
     *
     * The check and the update are one atomic UPDATE, so when the same ticket is
     * scanned at two entrances at the same moment only one of them succeeds.
     *
     * @param   int  $orderid  The ticket (order row) to mark as scanned
     * @param   int  $scanner  User id of the scanner, stored for reference
     *
     * @return  bool  True when this call marked the ticket as scanned
     *
     * @since 2.2.1
     */
    public function claimScan(int $orderid, int $scanner): bool
    {
        if ($orderid <= 0)
        {
            return false;
        }

        $app  = Factory::getApplication();
        $user = $app->getIdentity();
        $tz   = new \DateTimeZone($user ? $user->getParam('timezone', $app->get('offset', 'UTC')) : $app->get('offset', 'UTC'));

        $scandate = (new \DateTime('now', $tz))->format('Y-m-d H:i:s');

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__ticketstation_orders'))
            ->set([
                $db->quoteName('scanned') . ' = 1',
                $db->quoteName('scandate') . ' = :scandate',
                $db->quoteName('scanner') . ' = :scanner',
            ])
            ->where([
                $db->quoteName('orderid') . ' = :orderid',
                $db->quoteName('scanned') . ' = 0',
                $db->quoteName('paid') . ' = 1',
            ])
            ->bind(':scandate', $scandate)
            ->bind(':scanner', $scanner, ParameterType::INTEGER)
            ->bind(':orderid', $orderid, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        return $db->getAffectedRows() === 1;
    }
}
