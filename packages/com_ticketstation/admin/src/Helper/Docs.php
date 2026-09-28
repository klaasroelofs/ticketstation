<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Toolbar\Toolbar;

defined('_JEXEC') or die('Restricted access');

/**
 * Links from the admin screens to the central Documentation page (view=docs).
 */
class Docs
{
    /**
     * Adds a "Help" button to the toolbar that opens the given topic or subsection of the
     * Documentation page in a new tab, so a half-filled form stays where it is.
     *
     * @param   string  $anchor  Topic slug or subsection id without the "docs-" prefix,
     *                           e.g. 'events' or 'records-venues' (see tmpl/docs).
     */
    public static function toolbarButton(string $anchor): void
    {
        Toolbar::getInstance('toolbar')
            ->linkButton('ticketstation-docs', 'COM_TICKETSTATION_TOOLBAR_HELP')
            ->url('index.php?option=com_ticketstation&view=docs#docs-' . $anchor)
            ->icon('fa fa-question-circle')
            ->target('blank');
    }
}
