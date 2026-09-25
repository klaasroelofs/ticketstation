<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_ticketstation_basket
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Module\TicketstationBasket\Site\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Uri\Uri;

/**
 * Dispatcher for mod_ticketstation_basket.
 *
 * @since  2.0.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Display modes selectable in the module settings.
     *
     * @since 2.0.0
     */
    private const MODES = ['mini', 'full'];

    /**
     * com_ticketstation views on which the module is never shown, whatever its menu assignment
     * says (also "on all pages"): these pages are the cart, checkout and payment flow themselves,
     * so a basket next to them is redundant.
     *
     * @since 2.1.0
     */
    private const SUPPRESSED_VIEWS = ['cart', 'checkout', 'payment', 'paymentresult'];

    /**
     * Collects the data for tmpl/default.php and registers the web assets.
     *
     * @return  array|false  The layout data, or false to render nothing.
     *
     * @since   2.0.0
     */
    protected function getLayoutData(): array|false
    {
        if ($this->isSuppressedView()) {
            return false;
        }

        $data   = parent::getLayoutData();
        $helper = $this->getHelperFactory()->getHelper('BasketHelper');

        // The module is only useful next to its component.
        if (!$helper->isComponentAvailable()) {
            return false;
        }

        $params = $data['params'];
        $mode   = (string) $params->get('display_mode', 'mini');
        $mode   = \in_array($mode, self::MODES, true) ? $mode : 'mini';
        $count  = $helper->getItemCount();

        $data['displayMode'] = $mode;
        $data['itemCount']   = $count;
        $data['cartUrl']     = $helper->getCartUrl();
        $data['hidden']      = (bool) $params->get('check_empty', 0) && $count < 1;

        $document = $this->getApplication()->getDocument();
        $wa       = $document->getWebAssetManager();

        // Joomla only auto-registers media/<name>/joomla.asset.json for the active component and
        // template; a module has to register its own registry file before using its assets.
        $wa->getRegistry()->addExtensionRegistryFile('mod_ticketstation_basket');
        $wa->useStyle('mod_ticketstation_basket.basket');

        if ($mode === 'full') {
            $data['orderCount']   = $helper->getOrderCount();
            $data['waitingCount'] = $helper->getWaitingCount();
            $data['totals']       = $helper->getTotals($data['orderCount']);

            // The component's own AJAX (add ticket on the event views) only refreshes
            // #basket-item-count; the module's script uses that as the cue to also refresh
            // the totals, through the component's existing `updatecart` task.
            //
            // The module title is rendered by the template's module chrome, outside our markup,
            // so hiding #ticketstation_basket_module alone leaves the title behind. The script
            // gets the title to find that chrome and hide it together with the module.
            $module = $data['module'];

            $document->addScriptOptions('mod_ticketstation_basket', [
                'updateUrl' => Uri::root(true) . '/index.php?option=com_ticketstation&controller=order&task=updatecart&format=raw',
                'title'     => $module->showtitle ? trim((string) $module->title) : '',
                'hideEmpty' => (bool) $params->get('check_empty', 0),
            ]);
            $wa->useScript('mod_ticketstation_basket.basket');
        }

        return $data;
    }

    /**
     * Whether the current request is one of the com_ticketstation views listed in
     * SUPPRESSED_VIEWS. The view comes from the request input, so it is resolved the same way
     * for SEF and non-SEF URLs and for menu items pointing at those views.
     *
     * @return  boolean
     *
     * @since   2.1.0
     */
    private function isSuppressedView(): bool
    {
        $input = $this->getApplication()->getInput();

        return $input->getCmd('option') === 'com_ticketstation'
            && \in_array(strtolower($input->getCmd('view')), self::SUPPRESSED_VIEWS, true);
    }
}
