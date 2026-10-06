<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

use Joomla\CMS\Log\Log;

defined('_JEXEC') or die;

/**
 * The payment log, com_ticketstation_mollie.php in Joomla's log folder (administrator/logs), which
 * can't be read from the web. The file keeps its name from when Mollie was the only provider.
 */
final class PaymentLog
{
    public static function add(string $text): void
    {
        static $registered = false;

        if (!$registered) {
            Log::addLogger(['text_file' => 'com_ticketstation_mollie.php'], Log::ALL, ['com_ticketstation.mollie']);
            $registered = true;
        }

        Log::add($text, Log::INFO, 'com_ticketstation.mollie');
    }
}
