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
 * A customer has one name field (clients.name, the full name). Mails and the invoice address
 * still need "the first part of the name" for a greeting.
 */
class PersonName
{
    /**
     * The first part of a full name: "Jan van der Berg" gives "Jan".
     */
    public static function first(?string $fullName): string
    {
        return self::split($fullName)[0];
    }

    /**
     * Everything after the first part: "Jan van der Berg" gives "van der Berg".
     */
    public static function rest(?string $fullName): string
    {
        return self::split($fullName)[1];
    }

    /**
     * @return  string[]  First part, rest (both trimmed, the rest empty for a single word)
     */
    private static function split(?string $fullName): array
    {
        $parts = preg_split('/\s+/u', trim((string) $fullName), 2, PREG_SPLIT_NO_EMPTY);

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }
}
