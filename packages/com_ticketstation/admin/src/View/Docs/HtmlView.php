<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Docs;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Ticketstation centralised Documentation view. Purely static content, no model:
 * every topic is a separate sub-template (default_<topic>.php) loaded from default.php
 * so new topics can be added without touching this class.
 */
class HtmlView extends BaseHtmlView
{
    function display($tpl = null)
    {
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_DOCS_TITLE'), 'fa fa-book');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);

        parent::display($tpl);
    }
}
