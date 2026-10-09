<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Input\Input;

/**
 * Central backend permission check, run once per request by the administrator Dispatcher,
 * in the same way CsrfGate handles the form token. Every controller task is mapped to the
 * Joomla ACL action (see admin/access.xml) the user needs for it. A task that is not listed
 * here is refused, so a new controller method stays unreachable until it is given a
 * permission.
 *
 * Views use can() to hide buttons the user cannot use.
 */
class AclGate
{
    /** Shown to anyone who can open the component. */
    private const MANAGE = 'core.manage';

    /** Opening a create/edit form: allowed with either right, the save itself is checked. */
    private const FORM = ['core.create', 'core.edit'];

    /** Saving a record: core.create for a new record, core.edit for an existing one. */
    private const SAVE = 'save';

    private const BOXOFFICE    = 'ticketstation.boxoffice';
    private const RESERVE      = 'ticketstation.reserve';
    private const PAYMENT      = 'ticketstation.payment';
    private const ORDER_DELETE = 'ticketstation.order.delete';
    private const FINANCE      = 'ticketstation.finance';
    private const SCANNERS     = 'ticketstation.scanners';

    /**
     * controller => [task => action]. The '' entry is the controller's default task, used
     * when a page is opened without a task (the Dispatcher then asks for 'main', which
     * controllers without a main() answer with their default task).
     */
    private const ADMIN = [
        'display' => [
            ''        => self::MANAGE,
            'display' => self::MANAGE,
        ],
        'controlpanel' => [
            ''     => self::MANAGE,
        ],
        'docs' => [
            ''             => self::MANAGE,
            'controlpanel' => self::MANAGE,
        ],
        'configuration' => [
            ''          => 'core.options',
            'apply'     => 'core.options',
            'save'      => 'core.options',
            'cancel'    => self::MANAGE,
            'walletcsr' => 'core.options',
        ],
        'payments' => [
            ''       => 'core.options',
            'apply'  => 'core.options',
            'save'   => 'core.options',
            'publish'   => 'core.options',
            'unpublish' => 'core.options',
            'testmode'  => 'core.options',
            'cancel' => self::MANAGE,
        ],
        'templates' => [
            ''             => 'core.options',
            'edit'         => 'core.options',
            'apply'        => 'core.options',
            'save'         => 'core.options',
            'cancel'       => 'core.options',
            'preview'      => 'core.options',
            'testmail'     => 'core.options',
            'resetdefault' => 'core.options',
            'controlpanel' => self::MANAGE,
        ],

        // Catalogue: events, tickets, venues, coupons and seat plans.
        'events' => [
            ''             => self::MANAGE,
            'add'          => 'core.create',
            'edit'         => self::FORM,
            'publish'      => 'core.edit.state',
            'unpublish'    => 'core.edit.state',
            'duplicate'    => 'core.create',
            'mailbuyers'   => 'core.edit',
            'remove'       => 'core.delete',
            'tickets'      => self::MANAGE,
            'controlpanel' => self::MANAGE,
        ],
        'event' => [
            ''                 => self::FORM,
            'apply'            => self::SAVE,
            'save'             => self::SAVE,
            'cancel'           => self::MANAGE,
            'removebackground' => 'core.edit',
        ],
        'eventmail' => [
            ''         => 'core.edit',
            'display'  => 'core.edit',
            'send'     => 'core.edit',
            'sendtest' => 'core.edit',
            'updatewallets' => 'core.edit',
            'cancel'   => self::MANAGE,
        ],
        'tickets' => [
            ''               => self::MANAGE,
            'add'            => 'core.create',
            'edit'           => self::FORM,
            'publish'        => 'core.edit.state',
            'unpublish'      => 'core.edit.state',
            'duplicate'      => 'core.create',
            'remove'         => 'core.delete',
            'resetscanstate' => self::BOXOFFICE,
            'seatplans'      => self::MANAGE,
            'events'         => self::MANAGE,
            'controlpanel'   => self::MANAGE,
        ],
        'ticket' => [
            ''                   => self::FORM,
            'apply'              => self::SAVE,
            'save'               => self::SAVE,
            'seatplan'           => self::SAVE,
            'cancel'             => self::MANAGE,
            'removedesign'       => 'core.edit',
            'removebackground'   => 'core.edit',
            'ticketlayout'       => self::FORM,
            'previewticket'      => self::FORM,
            'ticketlayouteditor' => self::FORM,
        ],
        'venues' => [
            ''               => self::MANAGE,
            'add'            => 'core.create',
            'edit'           => self::FORM,
            'apply'          => self::SAVE,
            'save'           => self::SAVE,
            'cancel'         => self::MANAGE,
            'publish'        => 'core.edit.state',
            'unpublish'      => 'core.edit.state',
            'remove'         => 'core.delete',
            'exporttemplate' => self::MANAGE,
            'controlpanel'   => self::MANAGE,
        ],
        'coupons' => [
            ''             => self::MANAGE,
            'add'          => 'core.create',
            'edit'         => self::FORM,
            'publish'      => 'core.edit.state',
            'unpublish'    => 'core.edit.state',
            'remove'       => 'core.delete',
            'controlpanel' => self::MANAGE,
        ],
        'coupon' => [
            ''             => self::FORM,
            'apply'        => self::SAVE,
            'save'         => self::SAVE,
            'cancel'       => self::MANAGE,
            'controlpanel' => self::MANAGE,
        ],
        'seatplans' => [
            ''                 => self::MANAGE,
            'displaychart'     => 'core.edit',
            'editsettings'     => 'core.edit',
            'savelayout'       => 'core.edit',
            'uploadbackground' => 'core.edit',
            'copyfromticket'   => 'core.edit',
            'loadtemplate'     => 'core.edit',
            'savetemplate'     => 'core.edit',
            'deletetemplate'   => 'core.edit',
            'importtemplate'   => 'core.edit',
            'exporttemplate'   => self::MANAGE,
            'token'            => self::MANAGE,
            'cancel'           => self::MANAGE,
            'tickets'          => self::MANAGE,
            'ticket'           => self::MANAGE,
            'controlpanel'     => self::MANAGE,
        ],

        // Orders and customers.
        'boxoffice' => [
            ''                   => self::BOXOFFICE,
            'add'                => self::BOXOFFICE,
            'edit'               => self::BOXOFFICE,
            'cancel'             => self::MANAGE,
            'controlpanel'       => self::MANAGE,
            'reservation'        => self::RESERVE,
            'export'             => self::BOXOFFICE,
            'exportxlsx'         => self::BOXOFFICE,
            'downloadtickets'    => self::BOXOFFICE,
            'processticket'      => self::BOXOFFICE,
            'sendingticket'      => self::BOXOFFICE,
            'sendticketcopy'     => self::BOXOFFICE,
            'sendinvoice'        => self::BOXOFFICE,
            'resendpayment'      => self::BOXOFFICE,
            'sendpaymentrequest' => self::BOXOFFICE,
            'renewcodes'         => self::BOXOFFICE,
            'markasscanned'      => self::BOXOFFICE,
            'resetscanstate'     => self::BOXOFFICE,
            'blocked'            => self::BOXOFFICE,
            'unlock'             => self::BOXOFFICE,
            'updateinsertremark' => self::BOXOFFICE,
            'deleteremark'       => self::BOXOFFICE,
            'publish'            => self::BOXOFFICE,
            'unpublish'          => self::BOXOFFICE,
            // Marking an order paid sends its tickets without money coming in.
            'payment'            => self::PAYMENT,
            'allpayments'        => self::PAYMENT,
            'full_process'       => self::PAYMENT,
            'completeorder'      => self::PAYMENT,
            'nopayment'          => self::PAYMENT,
            // Refunds send money back and can invalidate or release tickets.
            'refundform'         => self::PAYMENT,
            'saverefund'         => self::PAYMENT,
            'syncrefunds'        => self::PAYMENT,
            'acknowledgerefund'  => self::PAYMENT,
            'remove'             => self::ORDER_DELETE,
            'removesingleorder'  => self::ORDER_DELETE,
        ],
        'reservation' => [
            ''                => self::RESERVE,
            'start'           => self::RESERVE,
            'cancel'          => self::RESERVE,
            'selectticket'    => self::RESERVE,
            'addquantity'     => self::RESERVE,
            'makereservation' => self::RESERVE,
            'removeseat'      => self::RESERVE,
            'updateseat'      => self::RESERVE,
            'removerow'       => self::RESERVE,
            'lines'           => self::RESERVE,
            'finishseats'     => self::RESERVE,
            'touch'           => self::RESERVE,
            'savecustomer'    => self::RESERVE,
            // Saving the reservation as paid additionally needs ticketstation.payment,
            // checked in ReservationController::complete().
            'complete'        => self::RESERVE,
        ],
        'clients' => [
            ''             => self::BOXOFFICE,
            'edit'         => self::BOXOFFICE,
            'apply'        => self::BOXOFFICE,
            'save'         => self::BOXOFFICE,
            'cancel'       => self::MANAGE,
            'publish'      => self::BOXOFFICE,
            'unpublish'    => self::BOXOFFICE,
            'remove'       => self::ORDER_DELETE,
            'controlpanel' => self::MANAGE,
        ],
        'waitinglist' => [
            ''             => self::BOXOFFICE,
            'confirm'      => self::BOXOFFICE,
            'remove'       => self::BOXOFFICE,
            'controlpanel' => self::MANAGE,
        ],
        'transactions' => [
            ''             => self::FINANCE,
            'add'          => self::FINANCE,
            'edit'         => self::FINANCE,
            'remove'       => self::FINANCE,
            'cancel'       => self::MANAGE,
            'controlpanel' => self::MANAGE,
        ],
        'invoices' => [
            ''             => self::FINANCE,
            'controlpanel' => self::MANAGE,
        ],
        'scanners' => [
            ''             => self::SCANNERS,
            'add'          => self::SCANNERS,
            'edit'         => self::SCANNERS,
            'apply'        => self::SCANNERS,
            'save'         => self::SCANNERS,
            'remove'       => self::SCANNERS,
            'cancel'       => self::MANAGE,
            'controlpanel' => self::MANAGE,
        ],
    ];

    /**
     * Where a SAVE task finds the id of the record it saves (0 or missing = a new record).
     * 'jform.x' is a field of the posted jform array.
     */
    private const RECORD_ID = [
        'event'  => 'jform.eventid',
        'ticket' => 'jform.ticketid',
        'coupon' => 'jform.coupon_id',
        'venues' => 'id',
    ];

    /**
     * Whether the current user may run this controller task.
     *
     * @param   string  $controller  Resolved controller name
     * @param   string  $task        Resolved task name; '' or 'main' for the default task
     * @param   Input   $input       The request input, to tell a new record from an existing one
     */
    public static function allows(string $controller, string $task, Input $input): bool
    {
        $action = self::requiredAction($controller, $task, $input);

        if ($action === null) {
            return false;
        }

        foreach ((array) $action as $name) {
            if (self::can($name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The ACL action(s) a controller task needs (any of them suffices), or null when the
     * task isn't known and must be refused.
     *
     * @return  string|string[]|null
     */
    public static function requiredAction(string $controller, string $task, Input $input)
    {
        $controller = strtolower($controller);
        $task       = strtolower($task);

        if (!isset(self::ADMIN[$controller])) {
            return null;
        }

        $map = self::ADMIN[$controller];

        // The default task: the Dispatcher asks for 'main', which a controller without a
        // main() method answers with its registered default (display).
        if ($task === 'main' || $task === 'display') {
            $task = '';
        }

        if (!isset($map[$task])) {
            return null;
        }

        if ($map[$task] !== self::SAVE) {
            return $map[$task];
        }

        return self::recordId($controller, $input) > 0 ? 'core.edit' : 'core.create';
    }

    /**
     * Whether the current user may open a backend screen (its controller's default task).
     */
    public static function canOpen(string $view): bool
    {
        return self::allows($view, '', Factory::getApplication()->getInput());
    }

    /**
     * Whether the current user has a Ticketstation ACL action.
     */
    public static function can(string $action): bool
    {
        $user = Factory::getApplication()->getIdentity();

        return $user !== null && (bool) $user->authorise($action, 'com_ticketstation');
    }

    private static function recordId(string $controller, Input $input): int
    {
        $field = self::RECORD_ID[$controller] ?? 'id';

        if (strpos($field, 'jform.') === 0) {
            $data = $input->post->get('jform', [], 'array');

            return (int) ($data[substr($field, 6)] ?? 0);
        }

        return $input->getInt($field, 0);
    }
}
