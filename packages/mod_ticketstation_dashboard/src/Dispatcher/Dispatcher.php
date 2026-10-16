<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_dashboard
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Module\TicketstationDashboard\Administrator\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\DashboardData;

/**
 * Dispatcher for mod_ticketstation_dashboard: one widget of the Ticketstation figures for Joomla's
 * dashboard (module position cpanel). The module holds no queries of its own; everything comes from
 * the component's DashboardData, the same source as the component's own Control panel.
 *
 * @since  2.37.0
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * The widgets the module can show, selectable in the module settings.
     *
     * @since 2.37.0
     */
    private const WIDGETS = ['attention', 'stats', 'upcoming', 'links'];

    /**
     * Collects the data for tmpl/default.php and registers the web assets.
     *
     * @return  array|false  The layout data, or false to render nothing.
     *
     * @since   2.37.0
     */
    protected function getLayoutData(): array|false
    {
        // The module is only useful next to its component, and only for those who may open it.
        if (!class_exists(DashboardData::class) || !AclGate::can('core.manage')) {
            return false;
        }

        $data   = parent::getLayoutData();
        $app    = $this->getApplication();
        $params = $data['params'];

        // The labels and texts of the component are used as they are, so both screens say the same.
        $app->getLanguage()->load('com_ticketstation', JPATH_ADMINISTRATOR);

        $widget = (string) $params->get('widget', 'attention');
        $widget = \in_array($widget, self::WIDGETS, true) ? $widget : 'attention';

        $data['widget'] = $widget;

        switch ($widget) {
            case 'attention':
                $data['items'] = DashboardData::attention((new Config())->get());

                if (!$data['items'] && (bool) $params->get('hide_when_empty', 0)) {
                    return false;
                }
                break;

            case 'stats':
                $data['stats']      = DashboardData::stats();
                $data['dailySales'] = DashboardData::dailySales();
                break;

            case 'upcoming':
                $data['rows']   = DashboardData::availability(max(1, min(20, (int) $params->get('count', 5))));
                $data['config'] = (new Config())->get();
                break;

            case 'links':
                $data['links'] = $this->chosenLinks($params->get('links'));

                if (!$data['links']) {
                    return false;
                }
                break;
        }

        $wa = $app->getDocument()->getWebAssetManager();

        // Joomla only auto-registers media/<name>/joomla.asset.json for the active component and
        // template; a module has to register its own registry file before using its assets.
        $wa->getRegistry()->addExtensionRegistryFile('mod_ticketstation_dashboard');
        $wa->useStyle('mod_ticketstation_dashboard.dashboard');

        return $data;
    }

    /**
     * The links shown when the module has not been saved since it was made: the usual six.
     *
     * @since 2.37.0
     */
    private const DEFAULT_LINKS = ['controlpanel', 'events', 'tickets', 'reservation', 'boxoffice', 'scanpage'];

    /**
     * The quick links this user may use, in the order of the rows in the module settings.
     *
     * @param   mixed  $chosen  The "links" parameter: the rows of the settings (each with a "link"),
     *                          a list of keys or a comma separated string; null while not saved.
     *
     * @return  array
     *
     * @since   2.37.0
     */
    private function chosenLinks($chosen): array
    {
        if ($chosen === null) {
            $chosen = self::DEFAULT_LINKS;
        }

        if (\is_string($chosen)) {
            $chosen = explode(',', $chosen);
        }

        $available = DashboardData::quickLinks();
        $links     = [];

        foreach ((array) $chosen as $row) {
            $key = \is_scalar($row) ? (string) $row : (string) (((array) $row)['link'] ?? '');

            if (isset($available[$key])) {
                $links[$key] = $available[$key];
            }
        }

        return $links;
    }
}
