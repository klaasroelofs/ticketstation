<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Ticket;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketLayoutFields;

/**
 * Ticketstation Ticket Admin View
 */
class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;

    function display($tpl = null)
    {

        $model       = $this->getModel();
        $this->form  = $model->getForm();
        $this->item  = $model->getItem();

        // Set up the toolbar
        $add_edit = empty($this->item->ticketid) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
        $ticket_name = empty($this->item->ticketid) ? Text::_('COM_TICKETSTATION_VIEW_TICKET_TITLE') : $this->item->ticketname . ' <small>(' . $this->item->ticketcode . ')</small>';
        ToolBarHelper::title($add_edit . ' ' . $ticket_name, 'fa fa-ticket-alt');
        ToolBarHelper::apply();
        ToolBarHelper::save();
        if (empty($this->item->ticketid)) {
            ToolbarHelper::cancel();
        }
        else {
            ToolbarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');

            ## A seated parent ticket: save the form, then open its seat plan editor.
            if ((int) $this->item->parent === 0 && (int) $this->item->show_seatplans === 1) {
                ToolbarHelper::custom('seatplan', 'icon-chair', '', 'COM_TICKETSTATION_SAVE_AND_EDIT_SEATPLAN', false);
            }
        }

        $app = Factory::getApplication();
        $app->getInput()->set('hidemainmenu', 1);

        Docs::toolbarButton('events');
        $this->loadLayoutEditor();
        parent::display($tpl);
    }

    /**
     * The layout editor on the "Ticket Layout" tab (assets/js/ticketlayouteditor.js): its
     * assets, and what it needs to place the fields exactly like the PDF (TicketLayoutFields).
     */
    private function loadLayoutEditor(): void
    {
        $assets = Uri::base() . 'components/com_ticketstation/assets/';
        $fields = [];

        foreach (TicketLayoutFields::FIELDS as $key => $field) {
            $fields[] = [
                'key'       => $key,
                'label'     => Text::_($field['label']),
                'bold'      => $field['bold'],
                'kind'      => $field['kind'],
                'condition' => isset($field['condition']) ? Text::_($field['condition']) : '',
            ];
        }

        $document = Factory::getApplication()->getDocument();
        $document->addScriptOptions('com_ticketstation.ticketlayouteditor', [
            'url'     => Uri::base(true) . '/index.php?option=com_ticketstation&controller=ticket&task=TicketLayoutEditor&format=raw',
            'pdfjs'   => $assets . 'pdfjs/pdf.min.js',
            'worker'  => $assets . 'pdfjs/pdf.worker.min.js',
            'fields'  => $fields,
            'metrics' => TicketLayoutFields::metrics(),
        ]);

        ## Every string the editor script uses: the keys it passes to T() (prefix COM_TICKETSTATION_TLE_).
        $script = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/js/ticketlayouteditor.js';

        if (is_file($script) && preg_match_all("/\\bT\\('([A-Z0-9_]+)'/", file_get_contents($script), $keys)) {
            foreach (array_unique($keys[1]) as $key) {
                Text::script('COM_TICKETSTATION_TLE_' . $key);
            }
        }

        $wa = $document->getWebAssetManager();
        $wa->registerAndUseStyle('com_ticketstation.ticketlayouteditor', $assets . 'css/ticketlayouteditor.css');
        $wa->registerAndUseScript('com_ticketstation.ticketlayouteditor', $assets . 'js/ticketlayouteditor.js', [], ['defer' => true], ['core']);
    }
}
