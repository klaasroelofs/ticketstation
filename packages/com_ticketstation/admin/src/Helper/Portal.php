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
use Joomla\CMS\Mail\MailHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\IpHelper;

/**
 * The customer portal "My orders": which orders a visitor may see and what they may do with them.
 *
 * Everything the portal shows or serves goes through this class, so who may see which order is
 * decided in one place. A visitor gets access in one of two ways, both held in the PHP session:
 *
 *  - by email: the link in the mail from the portal page ({@see requestLink()}, {@see enter()}).
 *    This shows every order placed with that address.
 *  - by order: the link in the ticket mail ({@see orderLink()}, {@see grantOrder()}). This shows
 *    that one order, until its last event is over.
 *
 * Keys are HMACs of the site's own secret (like the wallet links), so nobody can make one.
 *
 * @since   2.36.0
 */
class Portal
{
    /**
     * How long the link in the portal mail works.
     */
    public const LINK_MINUTES = 60;

    /**
     * How long a visitor stays "logged in" after opening a link.
     */
    public const SESSION_MINUTES = 120;

    /**
     * The mail template (#__ticketstation_templates) with the link.
     */
    public const MAIL_TEMPLATE = 9;

    /**
     * Requests for a link per address and per visitor (IP address) within an hour. More is
     * silently ignored, so the form can't be used to fill a customer's inbox.
     */
    private const MAX_PER_EMAIL = 3;
    private const MAX_PER_IP    = 10;

    /**
     * Mails of tickets (to the customer or to someone else) per order and per visitor within an
     * hour. The visitor's limit counts the requests for a link too, so it is wider.
     */
    private const MAX_SENDS_PER_ORDER = 10;
    private const MAX_SENDS_PER_IP    = 30;

    /**
     * The most orders held by order links in one session, and listed on the page.
     */
    private const MAX_GRANTS = 25;

    /**
     * The most addresses one account can have linked.
     */
    private const MAX_LINKS = 10;
    private const MAX_ORDERS = 100;

    private const SESSION_KEY = 'ticketstation.portal';

    /**
     * Order states that make an order show up: paid (1) or refunded the old way (2).
     */
    private const PAID_STATES = [1, 2];

    /**
     * The address of the visitor, for the limits. Joomla's own: behind a load balancer or reverse
     * proxy it is the visitor's, once the Global Configuration says "Behind Load Balancer"; without
     * that, `REMOTE_ADDR` is the proxy's and all visitors share one limit.
     */
    public static function visitorIp(): string
    {
        return (string) IpHelper::getIp();
    }

    /**
     * Whether a request to open a link is a page navigation: not an image, frame, script or fetch
     * of another site. A page of another site could otherwise load the link invisibly in a
     * visitor's browser and so put that visitor into somebody else's orders. Browsers without the
     * Sec-Fetch headers, and mail programs that strip them, are let through. User activation
     * (`Sec-Fetch-User`) is deliberately not required: the Gmail app on a phone, and other mail
     * apps with their own in-app browser, open links without it.
     */
    public static function isUserNavigation(): bool
    {
        $header = static fn (string $name): string => strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_' . $name] ?? '')));

        $dest = $header('DEST');
        $mode = $header('MODE');

        return ($dest === '' || $dest === 'document') && ($mode === '' || $mode === 'navigate');
    }

    /**
     * Whether the portal is switched on (Configuration > Customer portal).
     */
    public static function enabled(): bool
    {
        return (int) ((new Config)->get()->portal_mode ?? 0) > 0;
    }

    /**
     * Whether Joomla accounts can be linked to email addresses (Configuration > Customer portal).
     */
    public static function accountsEnabled(): bool
    {
        return (int) ((new Config)->get()->portal_mode ?? 0) >= 2;
    }

    /**
     * What the menu says about the page: whether there is a published menu item of the type My
     * orders, and the paths of published menu items with the alias the page is built at
     * (my-orders, or portal as in the release candidates) that are of another kind. Without an own
     * menu item of its own the page sits behind the shop's menu item, and such an item can take
     * its address over.
     *
     * @return  array{own: bool, clashes: string[]}
     */
    public static function menuStatus(): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([$db->quoteName('alias'), $db->quoteName('path'), $db->quoteName('link')])
            ->from($db->quoteName('#__menu'))
            ->where($db->quoteName('client_id') . ' = 0')
            ->where($db->quoteName('published') . ' = 1')
            ->where('(' . $db->quoteName('link') . ' LIKE ' . $db->quote('%option=com_ticketstation&view=portal%')
                . ' OR ' . $db->quoteName('alias') . ' IN (' . $db->quote('my-orders') . ', ' . $db->quote('portal') . '))');

        $db->setQuery($query);

        $status = ['own' => false, 'clashes' => []];

        foreach ($db->loadObjectList() as $item)
        {
            if (str_contains((string) $item->link, 'option=com_ticketstation&view=portal'))
            {
                $status['own'] = true;
            }
            else
            {
                $status['clashes'][] = '/' . ltrim((string) $item->path, '/');
            }
        }

        return $status;
    }

    /**
     * How many months back past orders are shown; 0 for all of them.
     */
    public static function pastMonths(): int
    {
        return max(0, (int) ((new Config)->get()->portal_past_months ?? 0));
    }

    // ------------------------------------------------------------------ keys and links

    private static function secret(): string
    {
        return (string) Factory::getApplication()->get('secret');
    }

    /**
     * The key of a mail link: for one customer (client row) and the time the link stops working.
     */
    public static function mailKey(int $clientid, int $expires): string
    {
        return substr(hash_hmac('sha256', 'ticketstation-portal-mail:' . $clientid . ':' . $expires, self::secret()), 0, 32);
    }

    /**
     * The key of the link in the ticket mail: for one order.
     */
    public static function orderKey(int $ordercode): string
    {
        return substr(hash_hmac('sha256', 'ticketstation-portal-order:' . $ordercode, self::secret()), 0, 32);
    }

    /**
     * The link in the portal mail. Unrouted, like the other task links: the SEF router would
     * drop the parameters.
     */
    public static function mailLink(int $clientid): string
    {
        $expires = time() + self::LINK_MINUTES * 60;

        return Uri::root() . 'index.php?option=com_ticketstation&controller=portal&task=enter&c=' . $clientid
            . '&x=' . $expires . '&key=' . self::mailKey($clientid, $expires);
    }

    /**
     * The link to one order, for the ticket mail.
     */
    public static function orderLink(int $ordercode): string
    {
        return Uri::root() . 'index.php?option=com_ticketstation&controller=portal&task=order&order=' . $ordercode
            . '&key=' . self::orderKey($ordercode);
    }

    /**
     * The "manage your order" block for the ticket mails: a line and the link, or an empty string
     * when the portal is off or the order's events are over (the link would not work any more).
     */
    public static function mailBlock(int $ordercode): string
    {
        if (!self::enabled() || !self::orderOpen($ordercode))
        {
            return '';
        }

        // Only promises what is there: the wallet when a wallet is on, sending on when that is allowed
        $key   = 'COM_TICKETSTATION_PORTAL_EMAIL_HINT' . (Wallet::available() ? '_WALLET' : '') . (self::sharingEnabled() ? '_SHARE' : '');
        $hint  = htmlspecialchars(TicketLanguage::_($key), ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars(TicketLanguage::_('COM_TICKETSTATION_PORTAL_EMAIL_LINK'), ENT_QUOTES, 'UTF-8');
        $href  = htmlspecialchars(self::orderLink($ordercode), ENT_QUOTES, 'UTF-8');

        return '<div style="margin:16px 0;"><p style="margin:0 0 8px 0;">' . $hint . '</p>'
            . '<p style="margin:0;"><a href="' . $href . '">' . $label . '</a></p></div>';
    }

    // ------------------------------------------------------------------ the visitor

    /**
     * The portal state of this session, without what has expired.
     *
     * @return  array{email: string, since: int, orders: array<int, int>}
     */
    private static function state(): array
    {
        $data   = Factory::getApplication()->getSession()->get(self::SESSION_KEY, []);
        $data   = is_array($data) ? $data : [];
        $cutoff = time() - self::SESSION_MINUTES * 60;
        $state  = ['email' => '', 'since' => 0, 'orders' => []];

        if (!empty($data['email']) && (int) ($data['since'] ?? 0) >= $cutoff)
        {
            $state['email'] = (string) $data['email'];
            $state['since'] = (int) $data['since'];
        }

        foreach ((array) ($data['orders'] ?? []) as $ordercode => $since)
        {
            if ((int) $since >= $cutoff)
            {
                $state['orders'][(int) $ordercode] = (int) $since;
            }
        }

        return $state;
    }

    private static function save(array $state): void
    {
        Factory::getApplication()->getSession()->set(self::SESSION_KEY, $state);
    }

    /**
     * The email address this visitor has proven to have in this session (a link opened), or ''.
     */
    public static function visitorEmail(): string
    {
        return self::state()['email'];
    }

    /**
     * The id of the Joomla account the visitor is logged in with, when accounts can be linked; else 0.
     */
    private static function userId(): int
    {
        if (!self::accountsEnabled())
        {
            return 0;
        }

        $user = Factory::getApplication()->getIdentity();

        return $user && !$user->guest ? (int) $user->id : 0;
    }

    /**
     * The addresses linked to this visitor's account: id, emailaddress, created. Linking is
     * permanent until the customer removes it, and takes effect at once.
     *
     * @return  object[]
     */
    public static function links(): array
    {
        $userid = self::userId();

        if ($userid === 0)
        {
            return [];
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['id', 'emailaddress', 'created'])
            ->from($db->quoteName('#__ticketstation_portal_links'))
            ->where($db->quoteName('userid') . ' = ' . $userid)
            ->order($db->quoteName('id'));

        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    /**
     * Every address whose orders this visitor sees: the one of a link opened in this session and
     * the ones linked to the account, each once.
     *
     * @return  string[]
     */
    public static function emails(): array
    {
        $emails = [];
        $email  = self::state()['email'];

        if ($email !== '')
        {
            $emails[] = $email;
        }

        foreach (self::links() as $link)
        {
            $emails[] = self::normalise((string) $link->emailaddress);
        }

        return array_values(array_unique($emails));
    }

    /**
     * The address of the link opened in this session when it isn't linked to the account yet and
     * the visitor is logged in: the portal then offers to link it. Else ''.
     */
    public static function linkOffer(): string
    {
        $email = self::state()['email'];

        if ($email === '' || self::userId() === 0)
        {
            return '';
        }

        foreach (self::links() as $link)
        {
            if (self::normalise((string) $link->emailaddress) === $email)
            {
                return '';
            }
        }

        return $email;
    }

    /**
     * Links the address opened in this session to the account the visitor is logged in with. The
     * link in the mail is the confirmation: without one in this session nothing is linked.
     *
     * @return  bool
     */
    public static function link(): bool
    {
        $userid = self::userId();
        $email  = self::linkOffer();

        if ($userid === 0 || $email === '' || count(self::links()) >= self::MAX_LINKS)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__ticketstation_portal_links'))
            ->columns($db->quoteName(['userid', 'emailaddress', 'created']))
            ->values(implode(',', [$userid, $db->quote($email), $db->quote(Factory::getDate()->toSql())]));

        $db->setQuery($query);

        try
        {
            $db->execute();
        }
        catch (\Throwable $e)
        {
            // Already linked (the same pair twice): nothing to do
            return false;
        }

        return true;
    }

    /**
     * Removes a link of this account. Only the visitor's own links can be removed.
     */
    public static function unlink(int $id): bool
    {
        $userid = self::userId();

        if ($userid === 0 || $id <= 0)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_portal_links'))
            ->where($db->quoteName('id') . ' = ' . $id)
            ->where($db->quoteName('userid') . ' = ' . $userid);

        $db->setQuery($query);
        $db->execute();

        return $db->getAffectedRows() > 0;
    }

    /**
     * Forgets the links of an account, when the account is deleted.
     */
    public static function forgetUser(int $userid): void
    {
        if ($userid <= 0)
        {
            return;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_portal_links'))
            ->where($db->quoteName('userid') . ' = ' . $userid);

        $db->setQuery($query);
        $db->execute();
    }

    /**
     * The orders opened with an order link in this session.
     *
     * @return  int[]
     */
    public static function grantedOrders(): array
    {
        return array_keys(self::state()['orders']);
    }

    /**
     * Whether this visitor has any access at all (shows the orders, not the form).
     */
    public static function hasAccess(): bool
    {
        return self::emails() !== [] || self::state()['orders'] !== [];
    }

    /**
     * Ends the visitor's access.
     */
    public static function leave(): void
    {
        Factory::getApplication()->getSession()->clear(self::SESSION_KEY);
    }

    /**
     * Opens the portal for the customer a mail link was made for.
     *
     * @return  bool  False when the link is wrong or has run out.
     */
    public static function enter(int $clientid, int $expires, string $key): bool
    {
        if ($clientid <= 0 || $expires < time() || $key === '' || !hash_equals(self::mailKey($clientid, $expires), $key))
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select($db->quoteName('emailaddress'))
            ->from($db->quoteName('#__ticketstation_clients'))
            ->where($db->quoteName('clientid') . ' = ' . $clientid);

        $db->setQuery($query);
        $email = self::normalise((string) $db->loadResult());

        if ($email === '')
        {
            return false;
        }

        $state          = self::state();
        $state['email'] = $email;
        $state['since'] = time();
        self::save($state);

        return true;
    }

    /**
     * Opens one order, with the link from the ticket mail.
     *
     * @return  bool  False when the key is wrong, the order isn't there or its events are over.
     */
    public static function grantOrder(int $ordercode, string $key): bool
    {
        if ($ordercode <= 0 || $key === '' || !hash_equals(self::orderKey($ordercode), $key) || !self::orderOpen($ordercode))
        {
            return false;
        }

        $state = self::state();

        // The oldest grant makes room
        asort($state['orders']);

        while (count($state['orders']) >= self::MAX_GRANTS)
        {
            array_shift($state['orders']);
        }

        $state['orders'][$ordercode] = time();
        self::save($state);

        return true;
    }

    /**
     * Whether this visitor may see and use the order. Every download and every page of the portal
     * asks this first.
     */
    public static function canAccess(int $ordercode): bool
    {
        if ($ordercode <= 0)
        {
            return false;
        }

        $state = self::state();

        if (isset($state['orders'][$ordercode]))
        {
            return true;
        }

        $emails = self::emails();

        if (!$emails)
        {
            return false;
        }

        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            ->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->where($db->quoteName('c.emailaddress') . ' IN (' . implode(',', array_map([$db, 'quote'], $emails)) . ')')
            ->whereIn($db->quoteName('o.paid'), self::PAID_STATES);

        // What the page leaves out as too old (Show past orders) can't be fetched by its number either
        $months = self::pastMonths();

        if ($months > 0)
        {
            $query->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
                ->where($db->quoteName('t.enddate') . ' >= ' . $db->quote(Date::localNow('Y-m-d H:i:s', '-' . $months . ' months')));
        }

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    // ------------------------------------------------------------------ the link by email

    /**
     * Sends the mail with the link, when this address has orders and hasn't asked too often. The
     * caller always gives the same answer, whatever happened here.
     */
    public static function requestLink(string $email, string $ip): bool
    {
        $email = self::normalise($email);

        if (!MailHelper::isEmailAddress($email))
        {
            return false;
        }

        $hash = hash_hmac('sha256', $email, self::secret());

        if (!self::within($hash, $ip, self::MAX_PER_EMAIL, self::MAX_PER_IP))
        {
            return false;
        }

        $clientid = self::clientFor($email);

        if ($clientid === 0)
        {
            return false;
        }

        $message = new eTicketsMessage;
        $link    = self::mailLink($clientid);

        $message->id((string) self::MAIL_TEMPLATE)
            ->user($clientid)
            ->variables([
                'portallink' => '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '</a>',
                'minutes'    => self::LINK_MINUTES,
            ]);

        return $message->send();
    }

    /**
     * Counts this request and says whether the address and the visitor are within the limits.
     */
    private static function within(string $hash, string $ip, int $maxHash, int $maxIp): bool
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $since = Factory::getDate('-1 hour')->toSql();

        // Old requests don't matter any more
        $db->setQuery($db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_portal_requests'))
            ->where($db->quoteName('created') . ' < ' . $db->quote(Factory::getDate('-1 day')->toSql())));
        $db->execute();

        $count = function (string $column, string $value) use ($db, $since): int {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ticketstation_portal_requests'))
                ->where($db->quoteName($column) . ' = ' . $db->quote($value))
                ->where($db->quoteName('created') . ' >= ' . $db->quote($since));

            $db->setQuery($query);

            return (int) $db->loadResult();
        };

        $allowed = $count('emailhash', $hash) < $maxHash && ($ip === '' || $count('ip', $ip) < $maxIp);

        $row = (object) [
            'emailhash' => $hash,
            'ip'        => substr($ip, 0, 60),
            'created'   => Factory::getDate()->toSql(),
        ];

        $db->insertObject('#__ticketstation_portal_requests', $row);

        return $allowed;
    }

    /**
     * The customer (client row) to send the link to: one with paid orders in the mode the shop is
     * in. 0 when there is none.
     */
    private static function clientFor(string $email): int
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('MIN(' . $db->quoteName('c.clientid') . ')')
            ->from($db->quoteName('#__ticketstation_clients', 'c'))
            ->join('INNER', $db->quoteName('#__ticketstation_orders', 'o') . ' ON ' . $db->quoteName('o.userid') . ' = ' . $db->quoteName('c.clientid'))
            ->where($db->quoteName('c.emailaddress') . ' = ' . $db->quote($email))
            ->where(TestData::condition('c.test'))
            ->whereIn($db->quoteName('o.paid'), self::PAID_STATES);

        $db->setQuery($query);

        return (int) $db->loadResult();
    }

    private static function normalise(string $email): string
    {
        return strtolower(trim($email));
    }

    // ------------------------------------------------------------------ orders

    /**
     * Whether an order still has an event that isn't over: the link in its ticket mail works up
     * to then. An order that isn't paid or isn't there is never open.
     */
    public static function orderOpen(int $ordercode): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('MAX(' . $db->quoteName('t.enddate') . ')')
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->whereIn($db->quoteName('o.paid'), self::PAID_STATES);

        $db->setQuery($query);
        $end = (string) $db->loadResult();

        return $end !== '' && $end >= Date::localNow();
    }

    /**
     * Whether the order has a valid ticket for an event that isn't over: only then there is
     * anything to download, to add to a wallet or to a calendar.
     */
    public static function usable(int $ordercode): bool
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->where($db->quoteName('o.paid') . ' = 1')
            ->where(Refund::validSql('o'))
            ->where('(' . $db->quoteName('o.blacklisted') . ' IS NULL OR ' . $db->quoteName('o.blacklisted') . ' = 0)')
            ->where($db->quoteName('t.enddate') . ' >= ' . $db->quote(Date::localNow()));

        $db->setQuery($query);

        return (int) $db->loadResult() > 0;
    }

    /**
     * The orders this visitor may see, split in what is still to come and what is over.
     *
     * Each order has: ordercode, orderdate, items (tickets), valid (tickets still valid), paid,
     * startdate, enddate, upcoming, total (formatted), refunded, events (see lines()), file (the
     * ticket PDF there is to download, or ''), invoice (the invoice PDF, or ''), wallet (the
     * "add to wallet" buttons), calendar (whether there is a file for the calendar) and email (a
     * masked address, only when the visitor came in with an order link).
     *
     * @return  object  upcoming: object[], past: object[], hidden: int past orders not shown
     */
    public static function orders(): object
    {
        $result = (object) ['upcoming' => [], 'past' => [], 'hidden' => 0];
        $state  = self::state();

        $db    = Factory::getContainer()->get('DatabaseDriver');
        $where = [];

        $emails = self::emails();

        if ($emails)
        {
            $where[] = '(' . $db->quoteName('c.emailaddress') . ' IN (' . implode(',', array_map([$db, 'quote'], $emails)) . ') AND ' . TestData::condition('o.test') . ')';
        }

        if ($state['orders'])
        {
            $where[] = $db->quoteName('o.ordercode') . ' IN (' . implode(',', array_map('intval', array_keys($state['orders']))) . ')';
        }

        if (!$where)
        {
            return $result;
        }

        $valid = $db->quoteName('o.refund_state') . ' < ' . Refund::TICKET_INVALID
            . ' AND (' . $db->quoteName('o.blacklisted') . ' IS NULL OR ' . $db->quoteName('o.blacklisted') . ' = 0)';

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('o.ordercode'),
                'MIN(' . $db->quoteName('o.orderdate') . ') AS orderdate',
                'COUNT(*) AS items',
                'SUM(CASE WHEN ' . $valid . ' THEN 1 ELSE 0 END) AS valid',
                'MAX(' . $db->quoteName('o.paid') . ') AS paid',
                'MIN(' . $db->quoteName('t.startdate') . ') AS startdate',
                'MAX(' . $db->quoteName('t.enddate') . ') AS enddate',
                'MIN(' . $db->quoteName('c.emailaddress') . ') AS emailaddress',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            ->whereIn($db->quoteName('o.paid'), self::PAID_STATES)
            ->where('(' . implode(' OR ', $where) . ')')
            ->group($db->quoteName('o.ordercode'))
            ->order([$db->quoteName('enddate') . ' DESC', $db->quoteName('o.ordercode') . ' DESC'])
            ->setLimit(self::MAX_ORDERS);

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        $now    = Date::localNow();
        $months = self::pastMonths();
        $limit  = $months > 0 ? Date::localNow('Y-m-d H:i:s', '-' . $months . ' months') : '';
        $shown  = [];

        foreach ($rows as $row)
        {
            $row->ordercode = (int) $row->ordercode;
            $row->upcoming  = (string) $row->enddate >= $now;

            if (!$row->upcoming && $limit !== '' && (string) $row->enddate < $limit)
            {
                $result->hidden++;

                continue;
            }

            $shown[] = $row;
        }

        if (!$shown)
        {
            return $result;
        }

        $lines   = self::lines(array_map(static fn ($row) => $row->ordercode, $shown));
        $sharing = self::sharingEnabled();
        $mask  = $emails === [];

        foreach ($shown as $row)
        {
            $row->items    = (int) $row->items;
            $row->valid    = (int) $row->valid;
            $row->refunded = (int) $row->paid === 2 || $row->valid === 0;
            $row->events   = $lines[$row->ordercode] ?? [];
            $row->total    = self::total($row->ordercode);
            $row->email    = $mask ? self::maskEmail((string) $row->emailaddress) : '';
            $row->mailto   = self::maskEmail((string) $row->emailaddress);
            unset($row->emailaddress);

            $usable         = $row->upcoming && self::usable($row->ordercode);
            $row->wallet    = $usable ? Wallet::buttons($row->ordercode, false) : '';
            $row->calendar  = $usable && Calendar::ics($row->ordercode) !== '';
            $row->invoice   = self::invoiceFile($row->ordercode);
            $row->share     = $sharing;
            $row->pick      = $usable ? self::pickable($row->ordercode) : [];

            // There is something to download when the file is there, or can be made when it is asked for
            $row->file      = $usable && ($row->pick || self::ticketFile($row->ordercode, false) !== '') ? 'yes' : '';

            if ($row->upcoming)
            {
                $result->upcoming[] = $row;
            }
            else
            {
                $result->past[] = $row;
            }
        }

        // What is coming, soonest on top; what is over, the latest on top. With the same date the
        // order number decides: oldest first for what is coming, the last placed on top for the past.
        usort($result->upcoming, static fn ($a, $b) => strcmp((string) $a->startdate, (string) $b->startdate) ?: $a->ordercode <=> $b->ordercode);
        usort($result->past, static fn ($a, $b) => strcmp((string) $b->enddate, (string) $a->enddate) ?: $b->ordercode <=> $a->ordercode);

        return $result;
    }

    /**
     * The tickets of the orders, grouped by event and ticket: [ordercode => event[]], each event
     * with eventname, startdate, enddate, venue and tickets: [name, quantity, seats[], invalid].
     */
    private static function lines(array $ordercodes): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select([
                'o.orderid', 'o.ordercode', 'o.refund_state', 'o.blacklisted', 'o.eventid',
                'e.eventname', 't.startdate', 't.enddate',
                "IF(p.ticketid IS NULL, t.ticketname, CONCAT(p.ticketname, ' - ', t.ticketname)) AS ticketname",
                'v.venue', 'v.city',
                's.row_name', 's.seatid',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('v.id') . ' = IF(' . $db->quoteName('t.venue') . ' > 0, ' . $db->quoteName('t.venue') . ', ' . $db->quoteName('p.venue') . ')')
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplancoords', 's') . ' ON ' . $db->quoteName('s.id') . ' = ' . $db->quoteName('o.seat_sector') . ' AND ' . $db->quoteName('o.seat_sector') . ' > 0')
            ->where($db->quoteName('o.ordercode') . ' IN (' . implode(',', array_map('intval', $ordercodes)) . ')')
            ->order([
                $db->quoteName('o.ordercode'),
                $db->quoteName('t.startdate'),
                $db->quoteName('o.eventid'),
                $db->quoteName('ticketname'),
                $db->quoteName('s.row_name'),
                'CAST(' . $db->quoteName('s.seatid') . ' AS UNSIGNED)',
                $db->quoteName('o.orderid'),
            ]);

        $db->setQuery($query);

        $result = [];

        foreach ($db->loadObjectList() as $row)
        {
            $code    = (int) $row->ordercode;
            $eventkey = $row->eventid . '|' . $row->startdate;
            $invalid = (int) $row->refund_state >= Refund::TICKET_INVALID || !empty($row->blacklisted);

            if (!isset($result[$code][$eventkey]))
            {
                $result[$code][$eventkey] = (object) [
                    'eventname' => (string) $row->eventname,
                    'startdate' => (string) $row->startdate,
                    'enddate'   => (string) $row->enddate,
                    'venue'     => trim(trim((string) $row->venue) . ', ' . trim((string) $row->city), ', '),
                    'tickets'   => [],
                ];
            }

            $event = $result[$code][$eventkey];
            $name  = (string) $row->ticketname;

            if (!isset($event->tickets[$name]))
            {
                $event->tickets[$name] = (object) ['name' => $name, 'quantity' => 0, 'seats' => [], 'invalid' => 0];
            }

            $ticket = $event->tickets[$name];
            $ticket->quantity++;

            if ($invalid)
            {
                $ticket->invalid++;
            }

            $seat = Wallet::seat($row);

            if ($seat !== '')
            {
                $ticket->seats[] = $seat;
            }
        }

        foreach ($result as $code => $events)
        {
            $result[$code] = array_values($events);
        }

        return $result;
    }

    /**
     * The total of an order as text, in the price notation of the Configuration.
     */
    private static function total(int $ordercode): string
    {
        $config = (new Config)->get();

        return TicketstationFunctions::showprice($config->priceformat, OrderTotals::get($ordercode)->total, $config->valuta);
    }

    /**
     * The ticket PDF of an order: its path when there is one to download, else ''.
     *
     * @param   bool  $make  Make the file again first when a ticket was made invalid after it was made
     *                       (a download does; looking at the list doesn't write anything)
     */
    public static function ticketFile(int $ordercode, bool $make = true): string
    {
        if ($make)
        {
            Refund::refreshTicketFiles($ordercode);
        }

        foreach ([Tickets::combinedPath($ordercode), Tickets::singlePath($ordercode)] as $path)
        {
            if (is_file($path))
            {
                return $path;
            }
        }

        return '';
    }

    /**
     * The invoice PDF of an order: its path when there is one, else ''.
     */
    public static function invoiceFile(int $ordercode): string
    {
        $invoices = new Invoice;
        $invoice  = $invoices->getInvoiceByOrderode($ordercode);

        if (!$invoice)
        {
            return '';
        }

        $path = $invoices->getPdfPath($invoice);

        return $path !== '' && is_file($path) ? $path : '';
    }

    // ------------------------------------------------------------------ some tickets of an order

    /**
     * Whether customers may send tickets to someone else (Configuration > Customer portal).
     */
    public static function sharingEnabled(): bool
    {
        return self::enabled() && (int) ((new Config)->get()->portal_share ?? 1) === 1;
    }

    /**
     * The tickets of an order a customer can pick from: valid, made (they have their QR code) and
     * for an event that isn't over, in the order of the PDF.
     *
     * @return  array<int, object>  orderid => ticket (see Wallet::tickets())
     */
    public static function selectable(int $ordercode): array
    {
        $now    = Date::localNow();
        $result = [];

        foreach (Wallet::tickets($ordercode) as $ticket)
        {
            if ((string) $ticket->enddate >= $now)
            {
                $result[(int) $ticket->orderid] = $ticket;
            }
        }

        return $result;
    }

    /**
     * What the order card offers to pick: orderid, ticket (name with its seat), event and date.
     *
     * @return  object[]
     */
    private static function pickable(int $ordercode): array
    {
        $list = [];

        foreach (self::selectable($ordercode) as $orderid => $ticket)
        {
            $seat   = Wallet::seat($ticket);
            $list[] = (object) [
                'orderid'   => $orderid,
                'ticket'    => (string) $ticket->ticketname . ($seat !== '' ? ' (' . $seat . ')' : ''),
                'eventname' => (string) $ticket->eventname,
                'startdate' => (string) $ticket->startdate,
            ];
        }

        return $list;
    }

    /**
     * The tickets asked for that really are the visitor's to pick, in the order of the PDF. What
     * isn't in this order, or can't be used any more, is left out.
     *
     * @param   mixed  $requested  Order ids as submitted
     *
     * @return  int[]
     */
    public static function pick(int $ordercode, $requested): array
    {
        $wanted = array_map('intval', (array) $requested);

        return array_values(array_filter(
            array_keys(self::selectable($ordercode)),
            static fn ($orderid) => in_array($orderid, $wanted, true)
        ));
    }

    /**
     * Whether the visitor may mail tickets of this order again: a limit per order and per visitor.
     * Every call counts.
     */
    public static function maySend(int $ordercode, string $ip): bool
    {
        return self::within(hash_hmac('sha256', 'send:' . $ordercode, self::secret()), $ip, self::MAX_SENDS_PER_ORDER, self::MAX_SENDS_PER_IP);
    }

    /**
     * The PDF with the tickets picked: the file of the order itself when they are all of its
     * tickets, else a document made for the occasion. Nothing is stored.
     *
     * @param   int[]  $orderids  From pick()
     *
     * @return  string  The document; empty when it couldn't be made
     */
    public static function ticketsPdf(int $ordercode, array $orderids): string
    {
        if (!$orderids)
        {
            return '';
        }

        $all = array_map(static fn ($ticket) => (int) $ticket->orderid, Wallet::tickets($ordercode));

        if ($orderids === $all && ($file = self::ticketFile($ordercode)) !== '')
        {
            return (string) file_get_contents($file);
        }

        try
        {
            return ticketcreator::selectionPdf($orderids);
        }
        catch (\Throwable $e)
        {
            return '';
        }
    }

    /**
     * Mails the picked tickets of an order: to the customer who ordered (no address given), or to
     * someone else with a short message. The mail to someone else holds only these tickets and no
     * link to the order, and the order's history says what was sent where.
     *
     * @param   int[]   $orderids  From pick()
     * @param   string  $to        The address of the other person; empty for the customer
     * @param   string  $note      A short message for the other person
     *
     * @return  bool  Whether the mail went out.
     */
    public static function sendTickets(int $ordercode, array $orderids, string $to = '', string $note = ''): bool
    {
        $client = self::client($ordercode);
        $pdf    = $client ? self::ticketsPdf($ordercode, $orderids) : '';

        if (!$client || $pdf === '')
        {
            return false;
        }

        $all     = array_map(static fn ($ticket) => (int) $ticket->orderid, Wallet::tickets($ordercode));
        $partial = $orderids !== $all;
        $wallet  = Wallet::buttons($ordercode, true, '', $partial ? $orderids : []);
        $message = new eTicketsMessage;

        if ($to === '')
        {
            $variables                  = eTicketsMessage::orderVariables($ordercode);
            $variables['walletbuttons'] = $wallet;
            $variables['portallink']    = self::mailBlock($ordercode);

            $message->id('2')->user((int) $client->clientid)->variables($variables);

            foreach (['walletbuttons', 'portallink'] as $tag)
            {
                if ($variables[$tag] !== '')
                {
                    $message->appendPlaceholder($tag);
                }
            }

            $message->stringAttachment(Calendar::ics($ordercode), 'event-' . $ordercode . '.ics', 'text/calendar');
            $address = (string) $client->emailaddress;
        }
        else
        {
            $sender = trim((string) preg_replace('/\s+/', ' ', strip_tags((string) $client->name)));
            $sender = $sender !== '' ? $sender : self::maskEmail((string) $client->emailaddress);
            $note   = trim((string) preg_replace('/\s+/', ' ', strip_tags($note)));

            $message->id('10')
                ->user(0)
                ->variables([
                    'emailaddress'  => $to,
                    'name'          => '',
                    'firstname'     => '',
                    'sendername'    => htmlspecialchars($sender, ENT_NOQUOTES, 'UTF-8'),
                    'ticketlist'    => self::ticketList($ordercode, $orderids),
                    'note'          => $note === '' ? '' : '<p style="margin:16px 0;padding:8px 12px;border-left:3px solid #9ca3af;"><em>'
                        . htmlspecialchars(mb_substr($note, 0, 200), ENT_QUOTES, 'UTF-8') . '</em></p>',
                    'walletbuttons' => $wallet,
                ]);

            if ($wallet !== '')
            {
                $message->appendPlaceholder('walletbuttons');
            }

            $address = $to;
        }

        $message->stringAttachment($pdf, 'Tickets-' . $ordercode . '.pdf', 'application/pdf');

        if (!$message->send())
        {
            return false;
        }

        History::log(
            $ordercode,
            $to === '' ? 'portal_tickets_sent' : 'portal_tickets_shared',
            ($to === ''
                ? 'Tickets mailed to the customer from My orders'
                : 'Tickets shared by the customer from My orders') . ': ' . count($orderids) . ' ticket(s) sent to ' . $address,
            ['orderids' => $orderids, 'to' => $address]
        );

        return true;
    }

    /**
     * The picked tickets as a list for the mail to someone else: per event the date and place,
     * with the tickets below it.
     */
    private static function ticketList(int $ordercode, array $orderids): string
    {
        $events = [];

        foreach (Wallet::tickets($ordercode) as $ticket)
        {
            if (!in_array((int) $ticket->orderid, $orderids, true))
            {
                continue;
            }

            $key  = $ticket->eventid . '|' . $ticket->startdate;
            $seat = Wallet::seat($ticket);

            $events[$key]['title']   ??= htmlspecialchars((string) $ticket->eventname, ENT_QUOTES, 'UTF-8')
                . ' &ndash; ' . htmlspecialchars(Date::long($ticket->startdate, true, true), ENT_QUOTES, 'UTF-8');
            $events[$key]['place']   ??= htmlspecialchars(trim(trim((string) $ticket->venue) . ', ' . trim((string) $ticket->city), ', '), ENT_QUOTES, 'UTF-8');
            $events[$key]['lines'][] = htmlspecialchars((string) $ticket->ticketname . ($seat !== '' ? ' (' . $seat . ')' : ''), ENT_QUOTES, 'UTF-8');
        }

        $html = '<ul>';

        foreach ($events as $event)
        {
            $html .= '<li><strong>' . $event['title'] . '</strong>' . ($event['place'] !== '' ? '<br />' . $event['place'] : '') . '<ul>';

            foreach (array_count_values($event['lines']) as $line => $count)
            {
                $html .= '<li>' . ($count > 1 ? $count . ' &times; ' : '') . $line . '</li>';
            }

            $html .= '</ul></li>';
        }

        return $html . '</ul>';
    }

    /**
     * The address of an order's customer, masked ("k***@gmail.com"), for the message that says
     * where the tickets went.
     */
    public static function customerAddress(int $ordercode): string
    {
        $client = self::client($ordercode);

        return $client ? self::maskEmail((string) $client->emailaddress) : '';
    }

    /**
     * The customer of an order: clientid, name and emailaddress; null when there is none.
     */
    private static function client(int $ordercode): ?object
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->select(['c.clientid', 'c.name', 'c.emailaddress'])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            ->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->setLimit(1);

        $db->setQuery($query);

        return $db->loadObject() ?: null;
    }

    /**
     * An address as the portal shows it to someone who only has an order link: the first letter
     * and the domain ("k***@gmail.com").
     */
    public static function maskEmail(string $email): string
    {
        $email = trim($email);
        $at    = strrpos($email, '@');

        if ($at === false || $at === 0)
        {
            return '';
        }

        return mb_substr($email, 0, 1) . '***' . substr($email, $at);
    }
}
