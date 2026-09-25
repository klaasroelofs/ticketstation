<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Table;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

defined('_JEXEC') || die;

class CouponsTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__ticketstation_coupons', 'coupon_id', $db);

    }
}