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


defined('_JEXEC') or die('Restricted access');

class Ordercode
{
    /**
     * Setting an ordercode at request.
     *
     * @param null $ordercode
     *
     * @return bool
     *
     * @since 3.5.0
     */
    public function setOrdercode($ordercode = null)
    {
        if ( ! $ordercode)
        {
            return false;
        }

        $session = Factory::getApplication()->getSession();

        // Clearing previous ordercode
        $session->clear('ordercode');

        // setting the given ordercode
        $session->set('ordercode', $ordercode);

        return true;
    }

    /**
     * Returning a temporary ordercode.
     * When the order will be completed we will get a final ordercode.
     * The temporart ordercode is the time in unix timestamp.
     *
     * @return string
     *
     * @since 1.0.0
     */
    public function getTemporaryOrdercode()
    {
        // Generating a 10 digit ordercode.
        return $this->generateOrdercode(9);
    }

    /**
     * A temporary ordercode (9 digits, see getTemporaryOrdercode()) is replaced by the final,
     * sequential ordercode (at most 5 digits: a 2-digit season prefix plus a 3-digit sequence,
     * eg. 26001). Anything already at or under that length is a final code and is left as-is.
     *
     * @param $ordercode
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function getFinalOrdercode($ordercode)
    {
        if (strlen($ordercode) > 5)
        {
            return $this->getReformattedOrdercode();
        }

        return $ordercode;
    }

    /**
     * Getting the next ordercode: one higher than the highest ordercode already in use, or the
     * configured/default starting point when that is higher (eg. a fresh install, or an
     * admin-requested jump via the "Next order number" setting).
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function getReformattedOrdercode()
    {
        $next_ordercode = max($this->getHighestNumericOrdercode() + 1, $this->getConfiguredNextOrdercode());

        // Resetting the ordercode to move on.
        Factory::getApplication()->getSession()->set('ordercode', $next_ordercode);

        return $next_ordercode;
    }

    /**
     * Returns the order number configured on the Configuration screen, falling back to the
     * default starting point (26001: season 26, first order) when it has not been set.
     *
     * @return int
     *
     * @since 1.7.0
     */
    private function getConfiguredNextOrdercode(): int
    {
        $default = 26001;
        $config  = (new Config)->getPartialConfig(['next_ordercode']);

        if (empty($config->next_ordercode))
        {
            return $default;
        }

        return (int) $config->next_ordercode;
    }

    /**
     * Returns the highest numeric ordercode already used, so the next one can be assigned
     * sequentially without colliding with an existing order. Restricted to (at most) 5-digit
     * codes so older 7-digit ordercodes, from before the switch to the shorter format, are
     * ignored here rather than pushing new codes back up to 7 digits.
     *
     * @return int
     *
     * @since 1.7.0
     */
    private function getHighestNumericOrdercode(): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('MAX(CAST(' . $db->quoteName('ordercode') . ' AS UNSIGNED))')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' REGEXP ' . $db->quote('^[0-9]{1,5}$'));

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    /**
     * Generating an ordercode on request.
     *
     * @param int    $length
     * @param string $chars
     *
     * @return string
     *
     * @since 1.0.0
     */
    private function generateOrdercode($length = 7, $chars = '123456789')
    {
        $chars_length = (strlen($chars) - 1);
        $string       = $chars[rand(0, $chars_length)];

        for ($i = 1; $i < $length; $i = strlen($string))
        {
            $r = $chars[rand(0, $chars_length)];
            if ($r != $string[$i - 1]) $string .= $r;
        }

        return $string;
    }
}