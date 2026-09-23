<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

/**
 * Single source of truth for which com_ticketstation controller.task combinations
 * are exempt from the centralised anti-CSRF token check performed once per request
 * by the admin and site Dispatchers, instead of the ~74 individual
 * `$this->checkToken(...) or jexit(...)` calls this replaces.
 *
 * Every task is checked by default (deny-by-default). A task only needs to be
 * listed here when it is either:
 *  - read-only (no database write / no state change), or
 *  - reached by something that structurally can never carry a Joomla session
 *    CSRF token: a payment provider's server-to-server webhook or browser
 *    redirect-back, a scanning device authenticated by its own API key, or a
 *    single-use emailed link opened outside any Joomla browser session.
 *
 * @since  __DEPLOY_VERSION__
 */
class CsrfGate
{
    /**
     * Admin (administrator app) controller => exempt task list.
     * Controller and task names are matched case-insensitively.
     *
     * @var array<string, string[]>
     */
    private const ADMIN_EXEMPT = [
        'display'          => ['display'],
        'controlpanel'     => ['main'],
        'invoices'         => ['display', 'controlpanel'],
        'coupon'           => ['display', 'cancel', 'controlpanel'],
        'mollie'           => ['main', 'cancel'],
        'scanners'         => ['display', 'edit', 'cancel', 'controlpanel'],
        'boxoffice'        => ['display', 'edit', 'cancel', 'reservation', 'controlpanel', 'downloadtickets'],
        'configuration'    => ['main', 'cancel'],
        'clients'          => ['display', 'edit', 'cancel', 'controlpanel'],
        'templates'        => ['display', 'edit', 'cancel', 'controlpanel'],
        'events'           => ['display', 'edit', 'controlpanel'],
        'tickets'          => ['display', 'seatplans', 'edit', 'controlpanel'],
        'coupons'          => ['display', 'edit', 'controlpanel'],
        'venues'           => ['display', 'edit', 'cancel', 'controlpanel'],
        'waitinglist'      => ['display', 'controlpanel'],
        'docs'             => ['main', 'controlpanel'],
        'transactions'     => ['display', 'edit', 'cancel', 'controlpanel'],
        'reservation'      => ['start', 'finishseats'],
        'ticket'           => ['display', 'cancel', 'ticketlayout', 'previewticket'],
        'event'            => ['display', 'cancel'],
        'seatplansettings' => ['display', 'cancel'],
        'seatplans'        => ['display', 'displaychart', 'editsettings', 'controlpanel', 'tickets', 'loadseat'],
    ];

    /**
     * Site app controller => exempt task list.
     *
     * @var array<string, string[]>
     */
    private const SITE_EXEMPT = [
        // The default fallback controller Joomla instantiates for every plain
        // "view=..." page request that carries no explicit controller= param.
        'display' => ['display'],

        'cart'         => ['showtos'],
        'order'        => ['updateavailable', 'updatecart', 'itemcount'],
        'orderseated'  => ['loadseat', 'loadcart'],

        // Payment-provider webhook / browser redirect-back endpoints: these are
        // never a form submission from our own UI and can never carry a Joomla
        // session CSRF token. Each has its own independent protection (Mollie's
        // signed callback, single-use bypass_token, session-side redirect state).
        'payment'       => ['mollie', 'molliebypass', 'ipnprocesspayment'],
        'paymentresult' => ['poll', 'return'],

        // Ticket scanning hardware authenticates with its own API key
        // (checked in the constructor via hash_equals), not a browser session.
        'codescanner' => ['validation'],

        // Reached only via single-use tokens embedded in emailed links
        // (waiting-list confirmation, pay-later, order validation) that are
        // opened outside any Joomla browser session and authenticate themselves.
        'validate' => ['waitinglist', 'pay', 'validate'],
    ];

    /**
     * @param   string  $app         'administrator' or 'site'
     * @param   string  $controller  Resolved controller name for this request
     * @param   string  $task        Resolved task name for this request
     *
     * @return  boolean  True when this controller.task must NOT be required to carry a CSRF token.
     *
     * @since  __DEPLOY_VERSION__
     */
    public static function isExempt(string $app, string $controller, string $task): bool
    {
        $map = $app === 'site' ? self::SITE_EXEMPT : self::ADMIN_EXEMPT;

        $controller = strtolower($controller);
        $task       = strtolower($task);

        if (!isset($map[$controller])) {
            return false;
        }

        return \in_array($task, $map[$controller], true);
    }
}
