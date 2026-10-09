<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Ticketstation\Component\Ticketstation\Administrator\Table\StampsTestMode;

defined('_JEXEC') || die;

class OrderseatedTable extends Table
{
    use StampsTestMode;

    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__ticketstation_orders', 'orderid', $db);

    }
}