<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\View\Transactions;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Docs;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;

/**
 * Ticketstation Transaction Admin View
 */
class HtmlView extends BaseHtmlView
{

    /**
     * Display the Ticketstation transaction view
     *
     * @param string $tpl The name of the template file to parse; automatically searches through the template paths.
     * @return  void
     */

    function display($tpl = null)
    {

        if($this->getLayout() == 'form') {
            $this->_displayForm($tpl);
            return;
        }

        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TRANSACTIONS_TITLE'), 'fa fa-credit-card');
        ToolBarHelper::deleteList();
        ToolbarHelper::custom('','spacer');
        ToolbarHelper::custom('controlpanel', 'icon-home', '', 'COM_TICKETSTATION_VIEW_CPANEL_TITLE_SHORT', false);
        Docs::toolbarButton('records-transactions');

        $lists['search'] = $this->escape($this->getModel()->getState('filter.search'));

        $items		= $this->get('list');
        $pagination	= $this->get('pagination');
        $data		= $this->get('config');

        $this->items = $items;
        $this->pagination = $pagination;
        $this->lists = $lists;
        $this->data = $data;

        parent::display($tpl);
    }

    function _displayForm($tpl = null)
    {
        // Set up the toolbar
        ToolbarHelper::title(Text::_('COM_TICKETSTATION_VIEW_TRANSACTION_DETAILS'), 'fa fa-credit-card');
        ToolBarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');
        Docs::toolbarButton('records-transactions');

        ## Getting the items into a variable
        $data	= $this->get('data');
        $config	= $this->get('config');

        $this->data = $data;
        $this->config = $config;

        // What the provider sent with the payment, as one row per value. The provider's own
        // payment id and whether it was a test payment are picked out for the top of the screen.
        parse_str((string) ($data->details ?? ''), $details);

        $this->providerData = [];
        $this->flatten($details, '', $this->providerData);
        $this->providerPaymentId = (string) ($this->providerData['id'] ?? $this->providerData['payment_intent'] ?? '');

        $mode = (string) ($this->providerData['mode'] ?? $this->providerData['livemode'] ?? '');

        $this->providerMode = in_array($mode, ['live', 'test'], true) ? $mode : '';
        $this->refunds      = !empty($data->orderid) ? Refund::forOrder((int) $data->orderid) : [];

        parent::display($tpl);
    }

    /**
     * Nested data as one row per value, named by its path ("amount / value"). The links to the
     * provider's API and empty values are left out.
     */
    private function flatten(array $data, string $prefix, array &$rows): void
    {
        foreach ($data as $key => $value) {
            if ($prefix === '' && $key === '_links') {
                continue;
            }

            $name = $prefix === '' ? (string) $key : $prefix . ' / ' . $key;

            if (is_array($value)) {
                $this->flatten($value, $name, $rows);
            } elseif ((string) $value !== '') {
                $rows[$name] = (string) $value;
            }
        }
    }

}