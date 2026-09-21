<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */


namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

## no direct access
defined('_JEXEC') or die('Restricted access');


class TicketstationFunctions
{
    /**
     * Returns the price formatted as chosen in configuration of Ticketstation
     *
     * @param $holder
     * @param $price
     * @param $currency
     *
     * @return string
     *
     * @since 1.0.0
     *
     *
     */
    public static function showprice($holder, $price, $currency)
    {

        if ($holder == 1) {
            $price = $currency . ' ' . number_format($price, 2, ',', '.');
        }
        if ($holder == 2) {
            $price = number_format($price, 2, ',', '.') . ' ' . $currency;
        }
        if ($holder == 3) {
            $price = $currency . ' ' . number_format($price, 2, ',', '');
        }
        if ($holder == 4) {
            $price = number_format($price, 2, ',', '') . ' ' . $currency;
        }
        if ($holder == 5) {
            $price = $currency . ' ' . number_format($price, 2, '.', '');
        }
        if ($holder == 6) {
            $price = number_format($price, 2, '.', '') . ' ' . $currency;
        }
        if ($holder == 7) {
            $price = number_format($price, '2', '.', ',') . ' ' . $currency;
        }
        if ($holder == 8) {
            $price = $currency . ' ' . number_format($price, '2', '.', ',');
        }
        if ($holder == 9) {
            $price = number_format($price, '0', '', ',') . ' ' . $currency;
        }
        if ($holder == 10) {
            $price = $currency . ' ' . number_format($price, '0', '', ',');
        }
        if ($holder == 11) {
            $price = number_format($price, '0', '', '.') . ' ' . $currency;
        }
        if ($holder == 12) {
            $price = $currency . ' ' . number_format($price, '0', '', '.');
        }

        return $price;
    }

    public static function showmonth($holder)
    {

        if ($holder == 1) {
            $month = Text::_('COM_TICKETSTATION_JANUARY');
        }
        if ($holder == 2) {
            $month = Text::_('COM_TICKETSTATION_FEBRUARY');
        }
        if ($holder == 3) {
            $month = Text::_('COM_TICKETSTATION_MARCH');
        }
        if ($holder == 4) {
            $month = Text::_('COM_TICKETSTATION_APRIL');
        }
        if ($holder == 5) {
            $month = Text::_('COM_TICKETSTATION_MAY');
        }
        if ($holder == 6) {
            $month = Text::_('COM_TICKETSTATION_JUNE');
        }
        if ($holder == 7) {
            $month = Text::_('COM_TICKETSTATION_JULY');
        }
        if ($holder == 8) {
            $month = Text::_('COM_TICKETSTATION_AUGUST');
        }
        if ($holder == 9) {
            $month = Text::_('COM_TICKETSTATION_SEPTEMBER');
        }
        if ($holder == 10) {
            $month = Text::_('COM_TICKETSTATION_OCTOBER');
        }
        if ($holder == 11) {
            $month = Text::_('COM_TICKETSTATION_NOVEMBER');
        }
        if ($holder == 12) {
            $month = Text::_('COM_TICKETSTATION_DECEMBER');
        }

        return $month;
    }

    public static function scanResult($result)
    {

        if ($result == 100) {
            $scanResult = '<span class="label label-success" style="margin-right:5px;">' . $result . '</span>' . Text::_('COM_TICKETSTATION_SCAN_SUCCESS');
        }
        if ($result == 101) {
            $scanResult = '<span class="label label-important" style="margin-right:5px;">' . $result . '</span>' . Text::_('COM_TICKETSTATION_SCAN_BLACKLISTED');
        }
        if ($result == 102) {
            $scanResult = '<span class="label label-important" style="margin-right:5px;">' . $result . '</span>' . Text::_('COM_TICKETSTATION_TICKET_WAS_UNPAID');
        }
        if ($result == 103) {
            $scanResult = '<span class="label label-important" style="margin-right:5px;">' . $result . '</span>' . Text::_('COM_TICKETSTATION_TICKET_WAS_SCANNED_BEFORE');
        }
        if ($result == 104) {
            $scanResult = '<span class="label label-important" style="margin-right:5px;">' . $result . '</span>' . Text::_('COM_TICKETSTATION_UNAUTHORIZED_SCANNER');
        }
        if ($result == 105) {
            $scanResult = '<span class="label label-important" style="margin-right:5px;">' . $result . '</span>' . Text::_('COM_TICKETSTATION_NO_BARCODE_FOUND');
        }

        return $scanResult;

    }
}