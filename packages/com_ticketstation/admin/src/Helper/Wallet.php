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
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/**
 * "Add to Apple Wallet" / "Add to Google Wallet": the parts both wallets share.
 *
 * Customers get the links in the ticket mail and on the payment result page. A link is signed
 * per order (see token()), so it keeps working for as long as the order exists and can't be
 * guessed from an order number. The passes are made at the moment the link is opened
 * (site WalletController), with the QR code each ticket has at that moment; the scanner reads
 * them like the QR code on the PDF ticket. Passes already in a wallet are not updated when
 * the ticket changes afterwards (version 1 has no live updates).
 *
 * @since 2.16.0
 */
class Wallet
{
    public const APPLE  = 'apple';
    public const GOOGLE = 'google';

    /** The look of a pass when the Configuration has no valid colour. */
    public const DEFAULT_BACKGROUND = '#1f2937';
    public const DEFAULT_FOREGROUND = '#ffffff';

    /**
     * @var object|null
     */
    private static $config;

    /**
     * The wallet settings with the company details a pass shows.
     */
    public static function config(): object
    {
        if (self::$config === null)
        {
            self::$config = (new Config)->get([
                'wallet_apple', 'wallet_apple_cert', 'wallet_apple_key', 'wallet_apple_pending_key', 'wallet_apple_updates',
                'wallet_google', 'wallet_google_issuer_id', 'wallet_google_key', 'wallet_google_updates',
                'wallet_logo', 'wallet_bg_color', 'wallet_fg_color',
                'companyname', 'website', 'email', 'phone',
            ]) ?: (object) [];
        }

        return self::$config;
    }

    /**
     * Forgets the cached settings, after the Configuration was saved.
     */
    public static function reset(): void
    {
        self::$config = null;
    }

    /**
     * Apple Wallet is switched on and has a certificate it can sign passes with.
     */
    public static function appleReady(): bool
    {
        $config = self::config();

        if ((int) ($config->wallet_apple ?? 0) !== 1 || empty($config->wallet_apple_key))
        {
            return false;
        }

        $info = WalletApple::certificateInfo($config->wallet_apple_cert ?? null);

        return $info !== null && ! $info['expired'];
    }

    /**
     * Google Wallet is switched on and has an issuer ID and a service account key.
     */
    public static function googleReady(): bool
    {
        $config = self::config();

        return (int) ($config->wallet_google ?? 0) === 1
            && WalletGoogle::validIssuerId((string) ($config->wallet_google_issuer_id ?? ''))
            && WalletGoogle::serviceAccount($config->wallet_google_key ?? null) !== null;
    }

    /**
     * The wallets customers are offered, in the order the buttons are shown.
     *
     * @return  string[]
     */
    public static function available(): array
    {
        return array_values(array_filter([
            self::appleReady() ? self::APPLE : null,
            self::googleReady() ? self::GOOGLE : null,
        ]));
    }

    /**
     * The key that signs the wallet links of an order: derived from the site's own secret
     * (Global Configuration), so nobody can make one for another order number.
     */
    public static function token(int $ordercode): string
    {
        $secret = (string) Factory::getApplication()->get('secret');

        return substr(hash_hmac('sha256', 'ticketstation-wallet:' . $ordercode, $secret), 0, 32);
    }

    /**
     * The token an Apple pass carries (authenticationToken) and the device sends back to the web
     * service: derived from the site's secret and the pass's serial number, so it can't be made
     * for another pass.
     */
    public static function appleToken(string $serial): string
    {
        $secret = (string) Factory::getApplication()->get('secret');

        return substr(hash_hmac('sha256', 'ticketstation-apple-pass:' . $serial, $secret), 0, 32);
    }

    public static function checkToken(int $ordercode, string $token): bool
    {
        return $ordercode > 0 && $token !== '' && hash_equals(self::token($ordercode), $token);
    }

    /**
     * The link that adds the tickets of an order to the given wallet. Unrouted, like the other
     * task links: the SEF router would drop the parameters.
     */
    public static function link(string $wallet, int $ordercode): string
    {
        return Uri::root() . 'index.php?option=com_ticketstation&controller=wallet&task=' . $wallet
            . '&order=' . $ordercode . '&key=' . self::token($ordercode);
    }

    /**
     * The tickets of an order that can go into a wallet: valid (not refunded or blocked) and
     * already created, so they have their QR code. In the order of the PDF tickets. With
     * $validOnly false every ticket of the order, also the invalid ones (for updating passes that
     * are already in a wallet).
     *
     * @return  object[]  orderid, ordercode, barcode, eventid, ticketid, refund_state, blacklisted,
     *                    eventname, ticketname, startdate, enddate, doors_open, venue, street, zipcode,
     *                    city, firstname, name, row_name, seatid
     */
    public static function tickets(int $ordercode, bool $validOnly = true): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        // A child ticket's name includes its parent's, and it takes its parent's venue when it
        // has none of its own (the same as the calendar file, see Calendar::events()).
        $query = $db->getQuery(true)
            ->select([
                'o.orderid', 'o.ordercode', 'o.barcode', 'o.eventid', 'o.ticketid', 'o.refund_state', 'o.blacklisted',
                'e.eventname', 't.startdate', 't.enddate',
                "COALESCE(NULLIF(t.doors_open, ''), NULLIF(p.doors_open, ''), '') AS doors_open",
                "IF(p.ticketid IS NULL, t.ticketname, CONCAT(p.ticketname, ' - ', t.ticketname)) AS ticketname",
                'v.venue', 'v.street', 'v.zipcode', 'v.city',
                'c.firstname', 'c.name',
                's.row_name', 's.seatid',
            ])
            ->from($db->quoteName('#__ticketstation_orders', 'o'))
            ->join('INNER', $db->quoteName('#__ticketstation_tickets', 't') . ' ON ' . $db->quoteName('t.ticketid') . ' = ' . $db->quoteName('o.ticketid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_tickets', 'p') . ' ON ' . $db->quoteName('p.ticketid') . ' = ' . $db->quoteName('t.parent') . ' AND ' . $db->quoteName('t.parent') . ' > 0')
            ->join('LEFT', $db->quoteName('#__ticketstation_events', 'e') . ' ON ' . $db->quoteName('e.eventid') . ' = ' . $db->quoteName('o.eventid'))
            ->join('LEFT', $db->quoteName('#__ticketstation_venues', 'v') . ' ON ' . $db->quoteName('v.id') . ' = IF(' . $db->quoteName('t.venue') . ' > 0, ' . $db->quoteName('t.venue') . ', ' . $db->quoteName('p.venue') . ')')
            ->join('LEFT', $db->quoteName('#__ticketstation_clients', 'c') . ' ON ' . $db->quoteName('c.clientid') . ' = ' . $db->quoteName('o.userid'))
            // The seat printed on the ticket (see TicketCreator)
            ->join('LEFT', $db->quoteName('#__ticketstation_seatplancoords', 's') . ' ON ' . $db->quoteName('s.id') . ' = ' . $db->quoteName('o.seat_sector') . ' AND ' . $db->quoteName('o.seat_sector') . ' > 0')
            // Only for the sort order of the PDF tickets
            ->join('LEFT OUTER', $db->quoteName('#__ticketstation_seatplancoords', 'ext') . ' ON ' . $db->quoteName('ext.orderid') . ' = ' . $db->quoteName('o.orderid'))
            ->where($db->quoteName('o.ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->where($validOnly ? Refund::validSql('o') : '1 = 1')
            ->where($validOnly ? $db->quoteName('o.barcode') . ' NOT IN (' . $db->quote('') . ', ' . $db->quote('0') . ')' : '1 = 1')
            ->where($validOnly ? '(' . $db->quoteName('o.blacklisted') . ' IS NULL OR ' . $db->quoteName('o.blacklisted') . ' = 0)' : '1 = 1');

        Tickets::orderForPdf($query, 'o', 'ext');

        $db->setQuery($query);

        $tickets = [];

        // A seat joined twice would list the ticket twice
        foreach ($db->loadObjectList() as $ticket)
        {
            $tickets[(int) $ticket->orderid] ??= $ticket;
        }

        return array_values($tickets);
    }

    /**
     * Records a pass handed out for a ticket: the Apple serial number, or the Google object ID
     * with its class ID. Version 1 doesn't use this yet; a version with live updates needs it to
     * find the passes customers already have. Recorded when the pass is made, also when the
     * customer then doesn't add it.
     */
    public static function record(string $wallet, object $ticket, string $passId, string $classId = ''): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $values = [
            $db->quote((string) $ticket->ordercode),
            (int) $ticket->orderid,
            $db->quote($wallet),
            $db->quote($passId),
            $db->quote($classId),
            $db->quote(Factory::getDate()->toSql()),
        ];

        // The same pass made again (the customer tapping the button twice) is recorded once
        $db->setQuery('INSERT IGNORE INTO ' . $db->quoteName('#__ticketstation_wallet_passes')
            . ' (' . implode(', ', $db->quoteName(['ordercode', 'orderid', 'wallet', 'pass_id', 'class_id', 'created'])) . ')'
            . ' VALUES (' . implode(', ', $values) . ')');

        try
        {
            $db->execute();
        }
        catch (\RuntimeException $e)
        {
            // Never let the bookkeeping stop a customer from getting the pass
        }
    }

    /**
     * Forgets the passes of an order, when the order itself is removed. Passes marked removed stay
     * until the wallet has been told (see WalletUpdate::ticketsRemoved()): Google's are deleted
     * then, Apple's are kept for the iPhones that still have the pass.
     */
    public static function forget($ordercode): void
    {
        $db = Factory::getContainer()->get('DatabaseDriver');

        $query = $db->getQuery(true)
            ->delete($db->quoteName('#__ticketstation_wallet_passes'))
            ->where($db->quoteName('ordercode') . ' = ' . $db->quote((string) $ordercode))
            ->where($db->quoteName('removed') . ' = 0');

        $db->setQuery($query)->execute();
    }

    /**
     * The "Add to wallet" buttons for an order, or an empty string when no wallet is on or the
     * order has no tickets for it yet.
     *
     * @param   int     $ordercode
     * @param   bool    $email     True for the ticket mail (inline styles, absolute image URLs),
     *                             false for the payment result page (the site stylesheet)
     * @param   string  $language  Language tag for the badges and texts; the site language when empty
     */
    public static function buttons(int $ordercode, bool $email, string $language = ''): string
    {
        $wallets = self::available();

        if (!$wallets || !self::tickets($ordercode))
        {
            return '';
        }

        $language = $language ?: TicketLanguage::get()->getTag();
        $text     = $email ? [TicketLanguage::class, '_'] : [Text::class, '_'];
        $links    = [];

        foreach ($wallets as $wallet)
        {
            $href  = htmlspecialchars(self::link($wallet, $ordercode), ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($text('COM_TICKETSTATION_WALLET_ADD_TO_' . strtoupper($wallet)), ENT_QUOTES, 'UTF-8');
            $badge = self::badge($wallet, $language);

            if ($badge !== null)
            {
                // The PNGs are made for sharp screens; a mail client like Outlook needs the
                // width spelled out, or it shows them at their full size
                $width = $badge['width'] ? (int) round($badge['width'] * 48 / $badge['height']) : 0;

                $image = '<img src="' . htmlspecialchars($badge['url'], ENT_QUOTES, 'UTF-8') . '" alt="' . $label . '" height="48"'
                    . ($width ? ' width="' . $width . '"' : '')
                    . ($email
                        ? ' style="height:48px;width:' . ($width ? $width . 'px' : 'auto') . ';border:0;display:inline-block;vertical-align:middle;"'
                        : ' class="ts-wallet__badge"')
                    . ' />';

                $links[] = '<a href="' . $href . '"' . ($email ? ' style="display:inline-block;margin:0 8px 8px 0;text-decoration:none;"' : ' class="ts-wallet__link"') . '>' . $image . '</a>';
            }
            else
            {
                // No official badge in the package (yet): a plain text button
                $links[] = '<a href="' . $href . '"'
                    . ($email
                        ? ' style="display:inline-block;margin:0 8px 8px 0;padding:12px 18px;border:1px solid #1f2937;border-radius:8px;color:#1f2937;font-weight:bold;text-decoration:none;vertical-align:middle;"'
                        : ' class="ts-btn ts-btn--secondary ts-wallet__link"')
                    . '>' . $label . '</a>';
            }
        }

        $hint = htmlspecialchars($text($email ? 'COM_TICKETSTATION_WALLET_EMAIL_HINT' : 'COM_TICKETSTATION_WALLET_PAGE_HINT'), ENT_QUOTES, 'UTF-8');

        if ($email)
        {
            return '<div style="margin:16px 0;">'
                . '<p style="margin:0 0 8px 0;">' . $hint . '</p>'
                . '<div>' . implode('', $links) . '</div>'
                . '</div>';
        }

        return '<p>' . $hint . '</p><div class="ts-wallet__buttons">' . implode('', $links) . '</div>';
    }

    /**
     * The official "Add to ... Wallet" badge in the given language, falling back on English;
     * null when the package has none (site/assets/images/wallet/<wallet>-wallet-<lang>.png).
     *
     * @return  array{url: string, width: int, height: int}|null
     */
    public static function badge(string $wallet, string $language): ?array
    {
        $folder = '/components/com_ticketstation/assets/images/wallet/';
        $code   = strtolower(substr($language, 0, 2));

        foreach (array_unique([$code, 'en']) as $candidate)
        {
            $file = $wallet . '-wallet-' . $candidate . '.png';

            if (is_file(JPATH_SITE . $folder . $file))
            {
                $size = @getimagesize(JPATH_SITE . $folder . $file) ?: [0, 0];

                return [
                    'url'    => rtrim(Uri::root(), '/') . $folder . $file,
                    'width'  => (int) $size[0],
                    'height' => (int) $size[1] ?: 1,
                ];
            }
        }

        return null;
    }

    /**
     * A colour from the Configuration as #rrggbb, or the default when it isn't one.
     */
    public static function color(?string $value, string $default): string
    {
        $value = trim((string) $value);

        return preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : $default;
    }

    /**
     * The pass colours: [background, foreground] as #rrggbb.
     */
    public static function colors(): array
    {
        $config = self::config();

        return [
            self::color($config->wallet_bg_color ?? '', self::DEFAULT_BACKGROUND),
            self::color($config->wallet_fg_color ?? '', self::DEFAULT_FOREGROUND),
        ];
    }

    /**
     * The organisation shown on the passes: the company name, else the site name.
     */
    public static function organisation(): string
    {
        $name = trim((string) (self::config()->companyname ?? ''));

        return $name !== '' ? $name : (string) Factory::getApplication()->get('sitename');
    }

    /**
     * A date as stored (site-local time, see Calendar) as an ISO 8601 date with offset, or ''.
     */
    public static function isoDate($localDate): string
    {
        if (!$localDate || str_starts_with((string) $localDate, '0000-00-00'))
        {
            return '';
        }

        $offset = Factory::getApplication()->get('offset') ?: 'UTC';

        try
        {
            $date = new \DateTime((string) $localDate, new \DateTimeZone($offset));
        }
        catch (\Exception $e)
        {
            return '';
        }

        return $date->format('Y-m-d\TH:i:sP');
    }

    /**
     * The address line of a ticket's venue ("Street 1, 1234 AB City"), or ''.
     */
    public static function address(object $ticket): string
    {
        return trim(trim((string) $ticket->street) . ', ' . trim(trim((string) $ticket->zipcode) . ' ' . trim((string) $ticket->city)), ', ');
    }

    /**
     * The seat of a ticket as printed on the PDF ticket ("A12"), or ''.
     */
    public static function seat(object $ticket): string
    {
        return trim((string) $ticket->row_name . (string) $ticket->seatid);
    }

    /**
     * The ticket holder's name, or ''.
     */
    public static function holder(object $ticket): string
    {
        return trim(trim((string) $ticket->firstname) . ' ' . trim((string) $ticket->name));
    }

    /**
     * The file of the pass logo chosen in the Configuration, or null when there is none.
     */
    public static function logoFile(): ?string
    {
        $path = trim((string) (self::config()->wallet_logo ?? ''));

        if ($path === '')
        {
            return null;
        }

        // Joomla's media field stores "path#joomlaImage://..."; only the part before "#" is the
        // file, which may be URL-encoded (spaces and other special characters in the name)
        $path = ltrim((string) strtok($path, '#'), '/');

        foreach (array_unique([$path, rawurldecode($path)]) as $candidate)
        {
            if (is_file(JPATH_ROOT . '/' . $candidate))
            {
                return JPATH_ROOT . '/' . $candidate;
            }
        }

        return null;
    }

    /**
     * Whether this server can turn the pass logo into the images of a pass: GD has to be able
     * to read its format, which rules out SVG and, depending on the PHP build, WebP or AVIF.
     */
    public static function logoUsable(): bool
    {
        $file = self::logoFile();

        if ($file === null || !function_exists('imagetypes'))
        {
            return false;
        }

        $type = @getimagesize($file)[2] ?? 0;

        $supported = [
            IMAGETYPE_PNG  => IMG_PNG,
            IMAGETYPE_JPEG => IMG_JPG,
            IMAGETYPE_GIF  => IMG_GIF,
            IMAGETYPE_WEBP => IMG_WEBP,
        ];

        if (defined('IMAGETYPE_AVIF') && defined('IMG_AVIF'))
        {
            $supported[IMAGETYPE_AVIF] = IMG_AVIF;
        }

        return isset($supported[$type]) && (imagetypes() & $supported[$type]);
    }

    /**
     * Why the pass logo chosen in the Configuration doesn't make it onto the passes, as a
     * language key; null when it does (or when none was chosen).
     */
    public static function logoProblem(): ?string
    {
        if (trim((string) (self::config()->wallet_logo ?? '')) === '')
        {
            return null;
        }

        if (self::logoFile() === null)
        {
            return 'COM_TICKETSTATION_WALLET_LOGO_NOT_FOUND';
        }

        if (!self::logoUsable())
        {
            return 'COM_TICKETSTATION_WALLET_LOGO_UNREADABLE';
        }

        if (Uri::getInstance(Uri::root())->getScheme() !== 'https')
        {
            return 'COM_TICKETSTATION_WALLET_LOGO_NO_HTTPS';
        }

        return null;
    }

    /**
     * An image fitted into a box of the given size as PNG, keeping its aspect ratio: on a
     * transparent background, or centred on a square of the given colour with $padding (0-1)
     * around it. Null without GD or when the file isn't an image GD can read (e.g. SVG).
     */
    public static function fitImage(string $file, int $width, int $height, ?string $background = null, float $padding = 0.0): ?string
    {
        if (!function_exists('imagecreatefromstring'))
        {
            return null;
        }

        $source = @imagecreatefromstring((string) file_get_contents($file));

        if (!$source)
        {
            return null;
        }

        $sw = imagesx($source);
        $sh = imagesy($source);

        $boxW  = (int) round($width * (1 - $padding));
        $boxH  = (int) round($height * (1 - $padding));
        $scale = min($boxW / $sw, $boxH / $sh);
        $dw    = max(1, (int) round($sw * $scale));
        $dh    = max(1, (int) round($sh * $scale));

        // A pass logo only as wide as the image itself needs
        if ($background === null)
        {
            $width = $dw;
        }

        $image = imagecreatetruecolor($width, $height);
        imagesavealpha($image, true);
        imagealphablending($image, false);

        if ($background !== null)
        {
            [$r, $g, $b] = sscanf($background, '#%02x%02x%02x');
            imagefill($image, 0, 0, imagecolorallocate($image, $r, $g, $b));
            imagealphablending($image, true);
        }
        else
        {
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
        }

        imagecopyresampled($image, $source, (int) (($width - $dw) / 2), (int) (($height - $dh) / 2), 0, 0, $dw, $dh, $sw, $sh);

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }
}
