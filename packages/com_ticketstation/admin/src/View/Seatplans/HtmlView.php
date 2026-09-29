<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Seatplans;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SeatplanLayout;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;

/**
 * Ticketstation Seatplans Admin View: the list of seat charts, and the seat plan editor
 * (layout "chart").
 */
class HtmlView extends BaseHtmlView
{
    /**
     * Display the Ticketstation Seatplans view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {

        if($this->getLayout() == 'chart') {
            $this->_displayChart($tpl);
            return;
        }

        // Setup the toolbars.
        ToolBarHelper::title(Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_TITLE'), 'fa fa-chair');
        ToolbarHelper::custom('tickets', 'icon-ticket-alt', '', 'COM_TICKETSTATION_TICKETS', false,false);
        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);
        Docs::toolbarButton('seating');

        $this->items = $this->get('list');

        parent::display($tpl);

    }

    function _displayChart($tpl = null)
    {
        $app     = Factory::getApplication();
        $input   = $app->getInput()->get('cid', array(0), 'array');
        $ownerId = (int) $input[0];
        $owner   = SeatplanLayout::owner($ownerId);

        if (!$owner) {
            $app->enqueueMessage(Text::_('COM_TICKETSTATION_SEAT_NOT_FOUND'), 'error');
            $app->redirect('index.php?option=com_ticketstation&view=seatplans');
            return;
        }

        ## Keeps the session alive while the editor is open, like Joomla's own edit screens.
        HTMLHelper::_('behavior.keepalive');

        $document = $app->getDocument();
        $document->addScriptOptions('com_ticketstation.seateditor', [
            'layout' => SeatplanLayout::forEditor($ownerId),
            'url'    => Uri::base(true) . '/index.php?option=com_ticketstation&controller=seatplans&cid=' . $ownerId,
            'token'  => Session::getFormToken(),
        ]);

        ## Every string the editor script uses: the keys it passes to T() (prefix COM_TICKETSTATION_SE_).
        $script = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/js/seateditor.js';

        if (is_file($script) && preg_match_all("/\\bT\\('([A-Z0-9_]+)'/", file_get_contents($script), $keys)) {
            foreach (array_unique($keys[1]) as $key) {
                Text::script('COM_TICKETSTATION_SE_' . $key);
            }
        }

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ticketstation.seateditor', Uri::base() . 'components/com_ticketstation/assets/css/seateditor.css');
        $wa->registerAndUseScript('com_ticketstation.seateditor', Uri::base() . 'components/com_ticketstation/assets/js/seateditor.js', [], ['defer' => true], ['core']);

        $title = Text::_('COM_TICKETSTATION_VIEW_SEATPLANS_CHART') . ' ' . htmlspecialchars($owner->ticketname, ENT_QUOTES, 'UTF-8')
            . ' <small>(' . htmlspecialchars($owner->ticketcode, ENT_QUOTES, 'UTF-8') . ')</small>';
        ToolbarHelper::title($title, 'fa fa-chair');
        ToolbarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');
        ToolbarHelper::custom('ticket', 'icon-ticket-alt', '', 'COM_TICKETSTATION_EDIT_TICKET', false);
        Docs::toolbarButton('seating-editor');

        parent::display($tpl);
    }

}
