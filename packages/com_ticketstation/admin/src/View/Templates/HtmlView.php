<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Templates;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TemplateDefaults;

/**
 * Ticketstation Mollie Admin View
 */
class HtmlView extends BaseHtmlView {

    /**
     * Display the Ticketstation Templates view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    public $config = [];

    /** @var bool Whether the template being edited has a default text to go back to. */
    public $hasDefault = false;

    function display($tpl = null) {

        if ($this->getLayout() == 'form') {
            $this->_displayForm($tpl);
            return;
        }
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE'), 'fa fa-envelope');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);
        Docs::toolbarButton('templates');

        $model = $this->getModel('Templates');

        $this->items = $model->getList();

        parent::display($tpl);
    }

    function _displayForm($tpl = null)
    {
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE'), 'fa fa-envelope');
        ToolBarHelper::apply();
        ToolBarHelper::save();
        ToolBarHelper::cancel();

        $model = $this->getModel('Templates');

        $this->data = $model->getData();

        // Preview, test mail and "put the default text back" work on what is typed in the form
        // (assets/js/templates.js); nothing is saved by them.
        $this->hasDefault = (bool) TemplateDefaults::get((int) ($this->data->mailid ?? 0));
        $toolbar          = Toolbar::getInstance('toolbar');
        $button           = function (string $id, string $icon, string $label) {
            return '<joomla-toolbar-button><button type="button" id="' . $id . '" class="btn btn-outline-primary">'
                . '<span class="' . $icon . '" aria-hidden="true"></span> ' . Text::_($label) . '</button></joomla-toolbar-button>';
        };

        $toolbar->customButton('ts-template-preview')->html($button('ts-template-preview', 'icon-eye', 'COM_TICKETSTATION_TEMPLATE_PREVIEW'));
        $toolbar->customButton('ts-template-testmail')->html($button('ts-template-testmail', 'icon-mail', 'COM_TICKETSTATION_TEMPLATE_TESTMAIL'));

        if ($this->hasDefault) {
            $toolbar->customButton('ts-template-reset')->html($button('ts-template-reset', 'icon-undo', 'COM_TICKETSTATION_TEMPLATE_RESET'));
        }

        Docs::toolbarButton('templates-edit');

        $document = $this->getDocument();
        $document->addScriptOptions('com_ticketstation.templates', [
            'url'          => Route::_('index.php?option=com_ticketstation', false),
            'confirmReset' => Text::_('COM_TICKETSTATION_TEMPLATE_RESET_CONFIRM'),
            'failed'       => Text::_('COM_TICKETSTATION_TEMPLATE_TESTMAIL_FAILED'),
        ]);
        $document->getWebAssetManager()->registerAndUseScript(
            'com_ticketstation.templates',
            Uri::base() . 'components/com_ticketstation/assets/js/templates.js',
            [],
            ['defer' => true],
            ['core']
        );

        // After a refused save (a required placeholder missing) show what was typed, not the
        // stored text (see TemplatesController::apply()).
        $app      = Factory::getApplication();
        $refused  = $app->getUserState('com_ticketstation.edit.template.data');
        $app->setUserState('com_ticketstation.edit.template.data', null);

        if ($this->data && is_array($refused) && (int) $refused['mailid'] === (int) $this->data->mailid)
        {
            $this->data->mailsubject = $refused['mailsubject'];
            $this->data->mailbody    = $refused['mailbody'];
        }

        ToolBarHelper::title(Text::sprintf('COM_TICKETSTATION_VIEW_EDIT_TEMPLATES_TITLE', $this->data->alias), 'fa fa-envelope');

        parent::display($tpl);
    }

}