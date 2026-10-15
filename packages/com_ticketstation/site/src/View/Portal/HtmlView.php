<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Portal;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Portal;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SiteCaptcha;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * The customer portal "My orders": a form to get a link by email, or, once a visitor has a link,
 * their orders with the tickets to download.
 *
 * @since 2.36.0
 */
class HtmlView extends BaseHtmlView
{
    /** @var  bool  Whether the visitor has opened a link, so the orders are shown */
    public $access = false;

    /** @var  object|null  upcoming, past and hidden orders, see Portal::orders() */
    public $orders;

    /** @var  string[]  The addresses whose orders are shown (a link opened, the ones linked to the account) */
    public $emails = [];

    /** @var  bool  Whether the visitor is logged in and accounts can be linked */
    public $accounts = false;

    /** @var  object[]  The addresses linked to the account */
    public $links = [];

    /** @var  string  An address opened in this session that can be linked to the account, or '' */
    public $offer = '';

    /** @var  string  Name of the account */
    public $username = '';

    /** @var  string  The email address of the account, to start the form with */
    public $accountEmail = '';

    /** @var  bool  Whether there is something of this session to close (a link or an order link) */
    public $canLeave = false;

    /** @var  string  The site's captcha for the form, when there is one */
    public $captcha = '';

    /** @var  int */
    public $itemid = 0;

    /** @var  object  The order the order sub-template is showing */
    public $order;

    public function display($tpl = null)
    {
        if (!Portal::enabled())
        {
            throw new \Exception(Text::_('JERROR_PAGE_NOT_FOUND'), 404);
        }

        $app = Factory::getApplication();

        // Personal data on this page: never from a cache, never in a search index, and the address
        // doesn't go along to other sites in the Referer.
        $app->allowCache(false);
        $app->setHeader('Cache-Control', 'no-store, private', true);
        $app->setHeader('X-Robots-Tag', 'noindex, nofollow', true);
        $app->setHeader('Referrer-Policy', 'no-referrer', true);
        $app->getDocument()->setMetaData('robots', 'noindex, nofollow');

        // The menu item the page is reached by, else the one of the shop: the form posts to a
        // task URL, which needs a menu item to know the component.
        $this->itemid  = $app->getInput()->getInt('Itemid', 0) ?: TicketstationFunctions::getSiteItemid();
        $this->access  = Portal::hasAccess();
        $this->emails   = Portal::emails();
        $this->orders   = $this->access ? Portal::orders() : null;
        $this->canLeave = Portal::visitorEmail() !== '' || Portal::grantedOrders() !== [];

        // Linking an account: the address of a link opened here can be linked to it, and the
        // account's own address is where the form starts.
        $user = $app->getIdentity();

        $this->accounts = Portal::accountsEnabled() && $user && !$user->guest;

        if ($this->accounts)
        {
            $this->links        = Portal::links();
            $this->offer        = Portal::linkOffer();
            $this->username     = (string) $user->name;
            $this->accountEmail = (string) $user->email;
        }

        // The form is shown to visitors without a link, and to those who came in by an order
        // link only and want all their orders (the site's captcha leaves out logged-in users).
        $this->captcha = $this->emails === [] ? SiteCaptcha::display('portal') : '';

        parent::display($tpl);
    }
}
