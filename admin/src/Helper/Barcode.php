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
     * Order has been validated by the customer. Update it now.
     *
     * @param null $ordercode
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function updateScanningState($barcode = null, $state = 1)
    {
        if (empty($barcode))
        {
            return false;
        }

        $user 	= Factory::getApplication()->getIdentity();

        $tz       = new \DateTimeZone($user->getParam('timezone', Factory::getApplication()->get('offset', 'UTC')));
        $scandate = (new \DateTime('now', $tz))->format('Y-m-d H:i:s');

        $db   = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true);

        $fields = [
            $db->quoteName('scanned') . ' = ' . $state,
            $db->quoteName('scandate') . ' = ' . $db->quote($scandate),
            $db->quoteName('scanner') . ' = ' . $db->quote($user->id),
        ];

        $conditions = [$db->quoteName('barcode') . ' = ' . $db->quote($barcode)];

        $query->update($db->quoteName('#__ticketstation_orders'))
            ->set($fields)
            ->where($conditions);

        $db->setQuery($query);

        if ( ! $db->execute())
        {
            return false;
        }

        return true;
    }
}