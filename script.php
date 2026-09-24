<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installer script for com_ticketstation.
 *
 * An update only adds and overwrites files; files that were dropped from the component stay
 * on the site (and keep working) unless they are deleted here. Paths are relative to JPATH_ROOT.
 */
class com_ticketstationInstallerScript extends InstallerScript
{
    protected $deleteFiles = [
        // Frontend statistics view, replaced by the statistics in the backend.
        '/components/com_ticketstation/src/Model/StatisticsModel.php',
        '/components/com_ticketstation/assets/css/statistics.css',
    ];

    protected $deleteFolders = [
        '/components/com_ticketstation/src/View/Statistics',
        '/components/com_ticketstation/tmpl/statistics',
    ];

    public function postflight($type, $parent)
    {
        if ($type === 'update')
        {
            $this->removeFiles();
        }

        return true;
    }
}
