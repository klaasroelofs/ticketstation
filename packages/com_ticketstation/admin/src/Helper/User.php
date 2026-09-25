<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;


use Joomla\CMS\Factory;

## no direct access
defined('_JEXEC') or die('Restricted access');

class User
{
    /**
     * Getting a user by its ID
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getClientById($userid = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $user   = Factory::getApplication()->getIdentity();
        $userid = ( ! $userid) ? $user->id : $userid;

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_clients'))
            ->where($db->quoteName('clientid') . " = " . (int) $userid);

        $db->setQuery($query);

        return $db->loadObject();
    }

    public function getClientByOrdercode($ordercode = null)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['c.*'])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('o.userid') . ' = ' . $db->quoteName('c.clientid'))
            ->where($db->quoteName('ordercode') . " = " . $ordercode);

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Getting a user by email address
     *
     * @return mixed
     *
     * @since 1.0.0
     */
    public function getJoomlaUserByEmail($mail)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['*'])
            ->from($db->quoteName('#__ticketstation_clients'))
            ->where($db->quoteName('emailaddress') . " = " . $db->quote($mail));

        $db->setQuery($query);

        return $db->loadObject();
    }



    /**
     * Checks if an email address is already in the database.
     * If so, return false.
     *
     * @param $email
     *
     * @return bool
     *
     * @since 1.0.0
     */
    public function checkIfEmailAddressExists($email)
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName(['email']))
            ->from($db->quoteName('#__users'))
            ->where($db->quoteName('email') . ' = ' . $db->quote($email));



        $db->setQuery($query);

        $user = $db->loadResult();

        if ($user['email'])
        {
            return false;
        }

        return true;
    }

}