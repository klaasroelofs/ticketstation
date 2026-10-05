<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;

/**
 * All events, latest first, with their date. Used to limit a coupon to whole events.
 */
class EventsField extends ListField
{
    protected $type = 'Events';

    protected function getOptions()
    {
        $options = parent::getOptions();
        $db      = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName(['eventid', 'eventname', 'eventdate']))
            ->from($db->quoteName('#__ticketstation_events'))
            ->order([$db->quoteName('eventdate') . ' DESC', $db->quoteName('eventid') . ' DESC']);

        $db->setQuery($query);

        foreach ($db->loadObjectList() as $event)
        {
            $options[] = HTMLHelper::_('select.option', (string) $event->eventid, $event->eventname . ' (' . date('d-m-Y', strtotime((string) $event->eventdate)) . ')');
        }

        return $options;
    }
}
