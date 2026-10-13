<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

/**
 * What the position of a text on a ticket means (the "Text alignment" setting on the Ticket Layout
 * tab): L, the text starts at the position and runs to the right, as it always did; or R, the text
 * ends at the position and runs to the left, for right-to-left scripts such as Arabic and Persian.
 *
 * @since  2.35.0
 */
class TicketTextAlign
{
    public const LEFT = 'L';

    public const RIGHT = 'R';

    /**
     * The alignment for a ticket's stored setting, left when empty or unknown.
     *
     * @param   string|null  $align  The value of the ticket's ticket_textalign column.
     *
     * @return  string
     */
    public static function of(?string $align): string
    {
        return strtoupper(trim((string) $align)) === self::RIGHT ? self::RIGHT : self::LEFT;
    }
}
