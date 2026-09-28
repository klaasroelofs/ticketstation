<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Model\Mixin;

defined('_JEXEC') || die;

use Joomla\CMS\Factory;

/**
 * The page, search and filters of a backend list, kept in the user state under a prefix of
 * that list only ("com_ticketstation.<list>."), so a search or page in one list never carries
 * over to another.
 *
 * The request parameters stay the ones the list templates post (searchbox, limitstart,
 * filter_*). A changed filter or search starts again at the first page: the page the list was
 * on could hold nothing of the new selection. The number of rows per page is Joomla's own
 * setting for all lists (global.list.limit).
 */
trait ListState
{
    /**
     * Puts the list's page and filters in the model state: 'limit', 'limitstart' and
     * 'filter.<name>' for each filter.
     *
     * @param   string  $list     Name of the list, e.g. 'clients'.
     * @param   array   $filters  name => [request parameter, default, filter type]. A filter
     *                            named 'search' is trimmed.
     *
     * @return  void
     */
    protected function populateListState(string $list, array $filters = [])
    {
        $app     = Factory::getApplication();
        $context = 'com_ticketstation.' . $list . '.';
        $changed = false;

        foreach ($filters as $name => [$request, $default, $type])
        {
            $previous = $app->getUserState($context . $name, $default);
            $value    = $app->getUserStateFromRequest($context . $name, $request, $default, $type);

            if ($name === 'search')
            {
                $value = trim((string) $value);
            }

            $changed = $changed || (string) $previous !== (string) $value;

            $this->setState('filter.' . $name, $value);
        }

        $limit      = (int) $app->getUserStateFromRequest('global.list.limit', 'limit', $app->get('list_limit'), 'uint');
        $limitstart = (int) $app->getUserStateFromRequest($context . 'limitstart', 'limitstart', 0, 'uint');

        if ($changed)
        {
            $limitstart = 0;
            $app->setUserState($context . 'limitstart', 0);
        }

        // In case limit has been changed, adjust limitstart accordingly
        $limitstart = ($limit != 0 ? (int) (floor($limitstart / $limit) * $limit) : 0);

        $this->setState('limit', $limit);
        $this->setState('limitstart', $limitstart);

        // The state is complete: ListModel::populateState() must not run over it.
        $this->__state_set = true;
    }
}
