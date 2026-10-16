<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Updater\Updater;
use Ticketstation\Component\Ticketstation\Administrator\Helper\DashboardData;

/**
 * Ticketstation ControlPanel Model
 * @since 0.0.8
 */
class ControlpanelModel extends BaseDatabaseModel
{
    /**
     * @var mixed
     * @since version
     */
    public $data;

    /**
     * @var mixed
     * @since version
     */

    function getData() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
                    ->select(array('manifest_cache'))
                    ->from($db->quoteName('#__extensions'))
                    ->where($db->quoteName('name') . ' = ' . $db->quote('Ticketstation'));

        $db->setQuery($query);
        $this->data = json_decode($db->loadResult(), true);

        return $this->data;
    }

    /**
     * The newer package version that Joomla's update system offers, or null when there is none.
     *
     * Uses Joomla's own update check for pkg_ticketstation, with the cache time and minimum
     * stability from the Joomla Update settings (com_installer), so the answer matches
     * System → Update → Extensions. The update server is only contacted when the last
     * check is older than that cache time.
     *
     * @return  string|null
     */
    function getAvailableUpdate()
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName(['extension_id', 'manifest_cache']))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = ' . $db->quote('package'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('pkg_ticketstation'));

        $db->setQuery($query);
        $package = $db->loadObject();

        if (!$package)
        {
            return null;
        }

        $params = ComponentHelper::getParams('com_installer');

        try
        {
            Updater::getInstance()->findUpdates(
                (int) $package->extension_id,
                3600 * (int) $params->get('cachetimeout', 6),
                (int) $params->get('minimum_stability', Updater::STABILITY_STABLE)
            );
        }
        catch (\Throwable $e)
        {
            // Update server unreachable: fall back to what an earlier check stored.
        }

        $query = $db->getQuery(true)
            ->select($db->quoteName('version'))
            ->from($db->quoteName('#__updates'))
            ->where($db->quoteName('extension_id') . ' = ' . (int) $package->extension_id);

        $db->setQuery($query);
        $versions = $db->loadColumn();

        $installed = json_decode((string) $package->manifest_cache, true)['version'] ?? '0';
        $newest    = null;

        foreach ($versions as $version)
        {
            if (version_compare($version, $newest ?? $installed, '>'))
            {
                $newest = $version;
            }
        }

        return $newest;
    }
	
    function getConfig() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_config'))
            ->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);

        return $db->loadObject();
    }

    /**
     * Key figures: sales this week / previous week / this month and what is on sale. See DashboardData.
     *
     * @return  array
     */
    function getStats()
    {
        return DashboardData::stats();
    }

    /**
     * Paid tickets and revenue per day for the last $days days. See DashboardData.
     *
     * @return  array
     */
    function getDailySales($days = 28)
    {
        return DashboardData::dailySales($days);
    }

    /**
     * The first steps a new installation needs. See DashboardData.
     *
     * @return  array
     */
    function getSetupSteps($config)
    {
        return DashboardData::setupSteps($config);
    }

    /**
     * Availability of the upcoming published tickets. See DashboardData.
     *
     * @return  array
     */
    function getAvailability($limit = 10)
    {
        return DashboardData::availability($limit);
    }

    /**
     * Things that need the administrator's attention. See DashboardData.
     *
     * @return  array
     */
    function getAttention($config)
    {
        return DashboardData::attention($config);
    }
}