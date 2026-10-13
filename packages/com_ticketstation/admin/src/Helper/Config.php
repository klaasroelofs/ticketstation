<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

## no direct access

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die('Restricted access');

class Config
{
    /**
     * The configuration row, read once per request. Everything that changes the row has to call
     * reset() afterwards (saving the Configuration, the Payments screen, the invoice counter).
     *
     * @var  \stdClass|null
     */
    private static $row = null;

    public function get($request = '*')
    {
        if (is_array($request))
        {
            return $this->getPartialConfig($request);
        }

        return $this->getFullConfig();
    }

    /**
     * Forgets the cached row, after something changed it.
     */
    public static function reset(): void
    {
        self::$row = null;
    }

    private function getFullConfig()
    {
        if (self::$row === null)
        {
            $db = Factory::getContainer()->get('DatabaseDriver');

            $query = $db->getQuery(true)
                ->select('*')
                ->from($db->quoteName('#__ticketstation_config'))
                ->where($db->quoteName('configid') . " = 1");

            $db->setQuery($query);

            self::$row = $db->loadObject();
        }

        // A copy, so a caller that adds or changes a property doesn't change it for the others
        return self::$row === null ? null : clone self::$row;
    }

    /**
     * Only the requested columns, as an object. A column that doesn't exist is an error, as it
     * was when this was a query of its own.
     *
     * @param   string[]  $request  Column names
     *
     * @return  \stdClass|null  Null when there is no configuration row
     */
    public function getPartialConfig($request)
    {
        $row = $this->getFullConfig();

        if ($row === null)
        {
            return null;
        }

        $partial = new \stdClass;

        foreach ((array) $request as $column)
        {
            if (!property_exists($row, $column))
            {
                throw new \RuntimeException('Unknown configuration column: ' . $column);
            }

            $partial->$column = $row->$column;
        }

        return $partial;
    }

    /**
     * Whether customers order on the combined page (Configuration > Checkout layout) instead of
     * the separate cart, details and payment steps.
     *
     * @return  bool
     *
     * @since   2.26.0
     */
    public static function combinedCheckout(): bool
    {
        return (int) ((new self)->getPartialConfig(['checkout_layout'])->checkout_layout ?? 0) === 1;
    }

    /**
     * The file of the company logo (Configuration > Company), or an empty string when none has
     * been chosen or the file isn't there.
     *
     * @return  string  Absolute path
     */
    public static function companyLogoFile(): string
    {
        $stored = (string) ((new self)->get()->company_logo ?? '');

        // Joomla's media field stores "path#joomlaImage://adapter/path?width=..&height=.." -
        // everything from the "#" on is picker metadata, not part of the file path.
        $stored = trim((string) strtok($stored, '#'));

        if ($stored === '')
        {
            return '';
        }

        $file = realpath(JPATH_ROOT . '/' . ltrim($stored, '/'));
        $root = realpath(JPATH_ROOT);

        // A file of this site only
        if ($file === false || $root === false || strpos($file, $root) !== 0 || !is_file($file))
        {
            return '';
        }

        return $file;
    }

    /**
     * The address customers are pointed to when something needs checking: the company email
     * (Configuration > Company), else the sender address, else Joomla's global sender address.
     *
     * @return  string
     */
    public function getContactEmail()
    {
        $config = $this->getPartialConfig(['email', 'from_email']);

        foreach ([$config->email ?? '', $config->from_email ?? ''] as $email)
        {
            if (trim($email) !== '')
            {
                return trim($email);
            }
        }

        return (string) Factory::getApplication()->get('mailfrom');
    }

    /**
     * Makes a link from the configuration usable on every page: a full URL (https://...) or a
     * root-relative path (/...) is kept as is, anything else is taken relative to the site root.
     *
     * @param   string  $link
     *
     * @return  string  Empty when no link has been configured
     */
    public static function toAbsoluteLink($link)
    {
        $link = trim((string) $link);

        if ($link === '' || preg_match('#^(https?:)?//#i', $link) || $link[0] === '/')
        {
            return $link;
        }

        return Uri::root() . $link;
    }
}