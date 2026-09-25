<?php
/**
 * @package     Joomla.Admin
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

class QueryHelper
{
    /**
     * Enables SQL_BIG_SELECTS on the given database driver, needed for some hosting
     * providers to allow the large queries this component relies on.
     *
     * @param   \Joomla\Database\DatabaseDriver  $db  The database driver.
     *
     * @return  bool
     *
     * @since   1.7.0
     */
    public static function enableBigSelects($db): bool
    {
        $db->setQuery('SET SQL_BIG_SELECTS=1');

        return $db->execute();
    }
}
