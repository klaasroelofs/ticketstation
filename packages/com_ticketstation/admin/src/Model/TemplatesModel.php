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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;

/**
 * Ticketstation Templates Model
 * @since 0.5.0
 */
class TemplatesModel extends BaseDatabaseModel
{
    private $id;

    function __construct()
    {
        parent::__construct();

        $app    = Factory::getApplication();
        $jinput = $app->getInput();

        $this->id = $jinput->get('cid', '0', 'INT');

    }

    function getData() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_templates'))
            ->where($db->quoteName('mailid') . ' = '. $db->quote((int) $this->id));

        $db->setQuery($query);

        return $db->loadObject();
    }

    function getList() {

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__ticketstation_templates'));

        $db->setQuery($query);

        return $db->loadObjectList();
    }

    public function store($data)
    {
        $table = $this->getTable();

        if (!$table->save($data)) {
            Factory::getApplication()->enqueueMessage(Text::_('COM_TICKETSTATION_ERROR_SAVE_FAILED'), 'error');
            $this->setError($table->getError());
            return false;
        }


//        // Bind the data.
//        if (!$table->bind($data)) {
//            Factory::getApplication()->enqueueMessage('Bind failed', 'error');
//            //$this->setError($table->getError());
//            return false;
//        }
//
//        // Check the data.
//        if (!$table->check()) {
//            Factory::getApplication()->enqueueMessage('Check failed', 'error');
//            //$this->setError($table->getError());
//            return false;
//        }
//
//        // Store the data.
//        try {
//            $table->store($data);
//        }
//        catch (\Exception $e) {
//            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
//            return false;
//        }


//        if (!$table->store()) {
//            Factory::getApplication()->enqueueMessage('Store failed', 'error');
//            $this->setError($table->getError());
//            return false;
//        }

        $this->mailid = $data['mailid'];

        return true;

    }

    function getMailID()
    {
        return $this->mailid;
    }
}