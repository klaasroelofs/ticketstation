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
     * Length of a temporary ordercode, see getTemporaryOrdercode().
     *
     * @since 2.13.0
     */
    public const TEMPORARY_DIGITS = 9;

    /**
     * Longest sequential ordercode that can be told apart from the legacy 7-digit codes, and so
     * the highest value the "Next order number" setting accepts (999999).
     *
     * @since 2.13.0
     */
    public const SEQUENTIAL_MAX_DIGITS = 6;

    /**
     * Whether the given code is a temporary ordercode, still to be replaced by a final one.
     *
     * @param mixed $ordercode
     *
     * @return bool
     *
     * @since 2.13.0
     */
    public static function isTemporaryOrdercode($ordercode): bool
    {
        $ordercode = (string) $ordercode;

        return ctype_digit($ordercode) && strlen($ordercode) === self::TEMPORARY_DIGITS;
    }

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
        return $this->generateOrdercode(self::TEMPORARY_DIGITS);
    }

    /**
     * A temporary ordercode (9 digits, see getTemporaryOrdercode()) is replaced by the final,
     * sequential ordercode (eg. 26001: a 2-digit season prefix plus a 3-digit sequence, growing
     * to 6 digits and beyond once the counter passes 99999). Anything else is already a final
     * code, the legacy 7-digit ones included, and is left as-is.
     *
     * @param $ordercode
     *
     * @return int
     *
     * @since 1.0.0
     */
    public function getFinalOrdercode($ordercode)
    {
        if (self::isTemporaryOrdercode($ordercode))
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
        $next_ordercode = max($this->getHighestNumericOrdercode(self::SEQUENTIAL_MAX_DIGITS) + 1, $this->getConfiguredNextOrdercode());

        // Past 999999 the sequence runs into the 7-digit range of the legacy codes, which the
        // query above leaves out. From there on count every final code, legacy ones included, so
        // the new code is always higher than any existing one and never collides.
        if (strlen((string) $next_ordercode) > self::SEQUENTIAL_MAX_DIGITS)
        {
            $next_ordercode = max($this->getHighestNumericOrdercode(self::TEMPORARY_DIGITS - 1) + 1, $next_ordercode);
        }

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
     * Returns the highest numeric ordercode of at most $maxDigits digits already used, so the
     * next one can be assigned sequentially without colliding with an existing order. With
     * SEQUENTIAL_MAX_DIGITS, the older 7-digit ordercodes from before the switch to the shorter
     * format are ignored rather than pushing new codes up to 7 digits. Temporary 9-digit codes
     * are never counted.
     *
     * @param int $maxDigits
     *
     * @return int
     *
     * @since 1.7.0
     */
    private function getHighestNumericOrdercode(int $maxDigits): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('MAX(CAST(' . $db->quoteName('ordercode') . ' AS UNSIGNED))')
            ->from($db->quoteName('#__ticketstation_orders'))
            ->where($db->quoteName('ordercode') . ' REGEXP ' . $db->quote('^[0-9]{1,' . $maxDigits . '}$'));

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