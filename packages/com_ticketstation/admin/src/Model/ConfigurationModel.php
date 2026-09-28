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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Ticketstation Configuration Model
 * @since 0.0.8
 */
class ConfigurationModel extends BaseDatabaseModel
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
                    ->select('*')
                    ->from($db->quoteName('#__ticketstation_config'))
                    ->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $this->data = $db->loadObject();

        return $this->data;
    }

    function store($data)
    {
        $table = $this->getTable();

        // Bind, check and store. Table methods return false or throw on a failure.
        try
        {
            return $table->bind($data) && $table->check() && $table->store();
        }
        catch (\Exception $e)
        {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

            return false;
        }
    }
}