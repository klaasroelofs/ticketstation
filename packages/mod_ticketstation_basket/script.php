<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_basket
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\Database\ParameterType;
use Joomla\Filesystem\Folder;
use Joomla\Registry\Registry;

/**
 * Upgrades from the 1.x module (a single mod_ticketstation_basket.php plus css/ and language/
 * in the module folder) to the namespaced 2.x structure.
 *
 * @since  2.0.0
 */
class Mod_ticketstation_basketInstallerScript
{
    /**
     * Runs after every install and update.
     *
     * @param   string            $type     'install', 'update' or 'discover_install'
     * @param   InstallerAdapter  $adapter  The installer adapter
     *
     * @return  void
     *
     * @since   2.0.0
     */
    public function postflight(string $type, InstallerAdapter $adapter): void
    {
        if ($type !== 'update') {
            return;
        }

        $this->removeLegacyFiles();
        $this->disableCachingOnExistingInstances();
    }

    /**
     * Removes what 1.x installed and 2.x no longer ships. method="upgrade" only overwrites.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function removeLegacyFiles(): void
    {
        $base = JPATH_SITE . '/modules/mod_ticketstation_basket';

        if (is_file($base . '/mod_ticketstation_basket.php')) {
            unlink($base . '/mod_ticketstation_basket.php');
        }

        // css/ moved to media/mod_ticketstation_basket/css/, language/ to Joomla's language folders.
        foreach (['css', 'language'] as $folder) {
            if (is_dir($base . '/' . $folder)) {
                Folder::delete($base . '/' . $folder);
            }
        }
    }

    /**
     * 1.x offered module caching and defaulted to it. The cart is per visitor, so cached
     * output would show one guest's cart to other guests; 2.x has no cache option. Existing
     * module instances keep their saved parameters, so switch caching off there as well.
     *
     * @return  void
     *
     * @since   2.0.0
     */
    private function disableCachingOnExistingInstances(): void
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->createQuery()
            ->select($db->quoteName(['id', 'params']))
            ->from($db->quoteName('#__modules'))
            ->where($db->quoteName('module') . ' = ' . $db->quote('mod_ticketstation_basket'));

        foreach ($db->setQuery($query)->loadObjectList() as $instance) {
            $params = new Registry($instance->params);
            $params->set('cache', 0);

            $paramsJson = $params->toString();
            $instanceId = (int) $instance->id;

            $update = $db->createQuery()
                ->update($db->quoteName('#__modules'))
                ->set($db->quoteName('params') . ' = :params')
                ->where($db->quoteName('id') . ' = :id')
                ->bind(':params', $paramsJson)
                ->bind(':id', $instanceId, ParameterType::INTEGER);

            $db->setQuery($update)->execute();
        }
    }
}
