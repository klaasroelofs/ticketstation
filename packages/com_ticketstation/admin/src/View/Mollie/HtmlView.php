<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Mollie;

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * Ticketstation Mollie Admin View
 */
class HtmlView extends BaseHtmlView {

    /**
     * Display the Ticketstation configuration view
     *
     * @param   string  $tpl  The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    public $config = [];

    function display($tpl = null) {

        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_MOLLIE_TITLE'), 'fa-regular fa-money-bill-wave');

        ToolbarHelper::apply();
        ToolbarHelper::save();
        ToolbarHelper::cancel();

        $model = $this->getModel('Mollie');
        $config = $model->getData();

        $yesno = [
            '0' => ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_NO')],
            '1' => ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_YES')],
        ];

        $lists = [];

        $lists['test_mode'] = HTMLHelper::_('select.genericList', $yesno, 'test_mode', ' class="form-select" ' . '',
            'value', 'text', $config->test_mode);

        $lists['bypass_mode'] = HTMLHelper::_('select.genericList', $yesno, 'bypass_mode', ' class="form-select" ' . '',
            'value', 'text', $config->bypass_mode);

        $language = [
            'nl_NL' => ['value' => 'nl_NL', 'text' => 'Nederlands'],
            'en_GB' => ['value' => 'en_GB', 'text' => 'English'],
            'fr_FR' => ['value' => 'fr_FR', 'text' => 'Francais'],
            'de_DE' => ['value' => 'de_DE', 'text' => 'Deutsch'],
        ];

        $lists['mollie_language'] = HTMLHelper::_('select.genericList', $language, 'mollie_language', ' class="form-select" ' . '',
            'value', 'text', $config->mollie_language);

        $lists['send_tickets_directly'] = HTMLHelper::_('select.genericList', $yesno, 'send_tickets_directly', ' class="form-select" ' . '',
            'value', 'text', $config->send_tickets_directly);


        $this->config = $config;
        $this->lists = $lists;

        parent::display($tpl);
    }


}