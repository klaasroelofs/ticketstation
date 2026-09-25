<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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

    /**
     * Look up the Itemid of a published site menu item pointing at com_ticketstation, so
     * internal redirects/links can be built explicitly under it instead of relying on
     * Joomla's "borrow the currently active menu item" SEF fallback
     * (Joomla\CMS\Component\Router\Rules\MenuRules::preprocess(), which Joomla core itself
     * marks with "TODO: Remove this whole block in 6.0 as it is a bug"). That fallback only
     * works while rendering a page that is itself served under the menu item; it fails for
     * AJAX endpoints, controller redirects, payment gateway callbacks, and links sent in
     * emails, where the "active" menu item is the site's default/home page instead - those
     * then fall back to the unrouted /component/ticketstation/... URL form.
     *
     * Queried directly from the database rather than via the site menu object, so it works
     * the same whether called from the site app, the administrator app, or a webhook/CLI
     * context with no site menu loaded.
     *
     * @return int  The Itemid, or 0 if no site menu item exists for this component
     *
     * @since 2.1.6
     */
    public static function getSiteItemid()
    {
        static $itemid = null;

        if ($itemid !== null) {
            return $itemid;
        }

        $itemid = 0;

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('m.id'))
            ->from($db->quoteName('#__menu', 'm'))
            ->join(
                'INNER',
                $db->quoteName('#__extensions', 'e'),
                $db->quoteName('e.extension_id') . ' = ' . $db->quoteName('m.component_id')
            )
            ->where($db->quoteName('e.element') . ' = ' . $db->quote('com_ticketstation'))
            ->where($db->quoteName('m.published') . ' = 1')
            ->where($db->quoteName('m.client_id') . ' = 0')
            ->order($db->quoteName('m.id') . ' ASC')
            ->setLimit(1);

        $db->setQuery($query);

        $result = $db->loadResult();

        if ($result) {
            $itemid = (int) $result;
        }

        return $itemid;
    }
}