<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Table;

defined('_JEXEC') or die;

use Ticketstation\Component\Ticketstation\Administrator\Helper\TestData;

/**
 * For the table classes of #__ticketstation_orders: a new order is stamped with the mode the shop
 * is in (see TestData). An order that already has a mode, and every update of an existing order,
 * keeps what it has.
 */
trait StampsTestMode
{
    public function store($updateNulls = false)
    {
        $key = $this->getKeyName();

        if (empty($this->$key) && property_exists($this, 'test') && $this->test === null) {
            $this->test = TestData::mode();
        }

        return parent::store($updateNulls);
    }
}
