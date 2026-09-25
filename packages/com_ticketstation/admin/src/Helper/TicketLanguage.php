<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Language;
use Joomla\CMS\Language\LanguageFactoryInterface;

defined('_JEXEC') or die('Restricted access');

/**
 * The language ticket PDFs are printed in.
 *
 * Tickets are created from the site side (a Mollie payment) as well as from the backend (box
 * office, resending), where the admin's own language is active. Like the invoice, a ticket should
 * always read in the site's configured frontend language (Global Configuration > Site > Language),
 * so the PDF texts come from the site language file. A separate Language object is used for that,
 * so the language of the page that triggered the ticket is left untouched.
 *
 * @since 2.2.13
 */
class TicketLanguage
{
    /**
     * @var Language|null
     */
    private static $language;

    /**
     * @return  Language
     */
    public static function get()
    {
        if (self::$language === null)
        {
            $tag      = ComponentHelper::getParams('com_languages')->get('site', 'en-GB');
            $language = Factory::getContainer()->get(LanguageFactoryInterface::class)->createLanguage($tag);

            $language->load('com_ticketstation', JPATH_SITE, $tag, true)
                || $language->load('com_ticketstation', JPATH_SITE . '/components/com_ticketstation', $tag, true);

            self::$language = $language;
        }

        return self::$language;
    }

    /**
     * Translates a key into the site language.
     *
     * @param   string  $key
     *
     * @return  string
     */
    public static function _($key)
    {
        return self::get()->_($key);
    }

    /**
     * Translates a key into the site language and fills in its placeholders.
     *
     * @param   string  $key
     * @param   mixed   ...$args
     *
     * @return  string
     */
    public static function sprintf($key, ...$args)
    {
        return vsprintf(self::get()->_($key), $args);
    }
}
