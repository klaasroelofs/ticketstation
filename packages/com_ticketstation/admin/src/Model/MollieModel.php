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
 * @since 0.4.0
 */
class MollieModel extends BaseDatabaseModel
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
                    ->from($db->quoteName('#__ticketstation_mollie'))
                    ->where($db->quoteName('configid') . ' = ' . $db->quote(1));

        $db->setQuery($query);
        $this->data = $db->loadObject();

        return $this->data;
    }

    function store($data) //TODO: Replace deprecated setError / getError
    {
        $table = $this->getTable();

        // Keep the existing API keys when the submitted value is empty, so a stray
        // browser autofill/generated password on the field can't wipe out the stored key.
        $existing = $this->getData();

        foreach (['api_key', 'api_key_test'] as $field) {
            if (empty($data[$field]) && isset($existing->$field)) {
                $data[$field] = $existing->$field;
            }
        }

        // Bind the data.
        /*try
        {
            $table->bind($data);
        }
        catch (\InvalidArgumentException $exception)
        {
            $exception->getMessage();
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'error');
            return false;
        }*/

        if (!$table->bind($data)) {
            $this->setError($table->getError());
            return false;
        }

        // Check the data.
        /*try
        {
            $table->check($data);
        }
        catch (\InvalidArgumentException $exception)
        {
            $exception->getMessage();
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'error');
            return false;
        }*/

        if (!$table->check()) {
            $this->setError($table->getError());
            return false;
        }

        // Store the data.
        /*try
        {
            $table->store($data);
        }
        catch (\InvalidArgumentException $exception)
        {
            $exception->getMessage();
            Factory::getApplication()->enqueueMessage($exception->getMessage(), 'error');
            return false;
        }*/

        if (!$table->store()) {
            $this->setError($table->getError());
            return false;
        }


        return true;
    }
}