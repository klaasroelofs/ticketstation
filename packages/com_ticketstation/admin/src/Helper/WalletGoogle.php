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

use Joomla\CMS\Uri\Uri;

/**
 * Google Wallet event tickets through a "Save to Google Wallet" link: a JWT, signed with the
 * key of the site's Google Cloud service account, that holds the passes themselves. Google
 * creates them when the customer saves them, so no call to the Google Wallet API is needed.
 *
 * Every ticket type (with its event, date and venue) becomes a pass class, every ticket a pass
 * object. Their IDs include a hash of what they show, so a changed event date or a new QR code
 * gives a new class or object instead of reusing the one Google already has: passes saved
 * before are not updated (version 1 has no live updates).
 *
 * @since 2.16.0
 */
class WalletGoogle
{
    public const SAVE_URL = 'https://pay.google.com/gp/v/save/';

    /**
     * An issuer ID is the number the Google Pay & Wallet Console shows.
     */
    public static function validIssuerId(string $issuerId): bool
    {
        return (bool) preg_match('/^[0-9]{5,25}$/', trim($issuerId));
    }

    /**
     * The service account from its JSON key file, or null when it isn't a usable one.
     *
     * @return  array{client_email: string, private_key: string}|null
     */
    public static function serviceAccount(?string $json): ?array
    {
        $data = $json ? json_decode($json, true) : null;

        if (!is_array($data)
            || ($data['type'] ?? '') !== 'service_account'
            || !filter_var($data['client_email'] ?? '', FILTER_VALIDATE_EMAIL)
            || empty($data['private_key'])
            || !@openssl_pkey_get_private($data['private_key']))
        {
            return null;
        }

        return ['client_email' => $data['client_email'], 'private_key' => $data['private_key']];
    }

    /**
     * The link that saves the given tickets to Google Wallet in one go.
     *
     * @param   object[]  $tickets  From Wallet::tickets()
     *
     * @throws  \RuntimeException
     */
    public static function saveUrl(array $tickets): string
    {
        $config   = Wallet::config();
        $account  = self::serviceAccount($config->wallet_google_key ?? null);
        $issuerId = trim((string) ($config->wallet_google_issuer_id ?? ''));

        if ($account === null || !self::validIssuerId($issuerId))
        {
            throw new \RuntimeException('Google Wallet has no issuer ID or service account key.');
        }

        $classes = [];
        $objects = [];
        $total   = count($tickets);

        foreach ($tickets as $index => $ticket)
        {
            $class = self::passClass($ticket, $issuerId);

            $classes[$class['id']] = $class;
            $objects[]             = self::passObject($ticket, $class['id'], $issuerId, $index + 1, $total);
        }

        $root = Uri::getInstance(Uri::root());

        $claims = [
            'iss'     => $account['client_email'],
            'aud'     => 'google',
            'typ'     => 'savetowallet',
            'iat'     => time(),
            'origins' => [$root->toString(['scheme', 'host', 'port'])],
            'payload' => [
                'eventTicketClasses' => array_values($classes),
                'eventTicketObjects' => $objects,
            ],
        ];

        $url = self::SAVE_URL . self::jwt($claims, $account['private_key']);

        foreach ($tickets as $index => $ticket)
        {
            Wallet::record(Wallet::GOOGLE, $ticket, $objects[$index]['id'], $objects[$index]['classId']);
        }

        return $url;
    }

    /**
     * The pass class of a ticket type: what all its tickets share.
     */
    private static function passClass(object $ticket, string $issuerId): array
    {
        [$background] = Wallet::colors();

        $language  = TicketLanguage::get()->getTag();
        $venue     = trim((string) $ticket->venue);
        $address   = Wallet::address($ticket);
        $start     = Wallet::isoDate($ticket->startdate);
        $end       = Wallet::isoDate($ticket->enddate);
        $doors     = Wallet::isoDate(Date::doorsOpen($ticket->startdate, $ticket->doors_open ?? ''));
        $eventname = trim(strip_tags((string) $ticket->eventname));

        $class = [
            'issuerName'         => Wallet::organisation(),
            'reviewStatus'       => 'UNDER_REVIEW',
            'eventName'          => self::text($eventname, $language),
            'hexBackgroundColor' => $background,
            // The customer can share a ticket from Google Wallet, for example with the friend
            // it was bought for. A shared pass is the same ticket with the same QR code, like a
            // forwarded PDF: the scanner lets the first one in.
            'multipleDevicesAndHoldersAllowedStatus' => 'MULTIPLE_HOLDERS',
        ];

        // Google wants both a name and an address, or no venue at all
        if ($venue !== '' || $address !== '')
        {
            $class['venue'] = [
                'name'    => self::text($venue !== '' ? $venue : $address, $language),
                'address' => self::text($address !== '' ? $address : $venue, $language),
            ];
        }

        if ($start !== '')
        {
            $class['dateTime'] = ['start' => $start];

            if ($end !== '' && $end > $start)
            {
                $class['dateTime']['end'] = $end;
            }

            if ($doors !== '')
            {
                $class['dateTime']['doorsOpen'] = $doors;
            }
        }

        // Google fetches the logo itself, so only from a site it can reach
        if (Wallet::logoUsable() && Uri::getInstance(Uri::root())->getScheme() === 'https')
        {
            $class['logo'] = [
                'sourceUri'          => ['uri' => Uri::root() . 'index.php?option=com_ticketstation&controller=wallet&task=logo&v=' . substr(sha1((string) Wallet::config()->wallet_logo . $background), 0, 8)],
                'contentDescription' => self::text(Wallet::organisation(), $language),
            ];
        }

        $website = trim((string) (Wallet::config()->website ?? ''));

        if (filter_var($website, FILTER_VALIDATE_URL))
        {
            $class['homepageUri'] = ['uri' => $website, 'description' => Wallet::organisation()];
        }

        $class['id'] = $issuerId . '.ts_t' . (int) $ticket->ticketid . '_' . substr(sha1(json_encode($class)), 0, 12);

        return $class;
    }

    /**
     * The pass object of one ticket.
     */
    private static function passObject(object $ticket, string $classId, string $issuerId, int $number, int $total): array
    {
        [$background] = Wallet::colors();

        $language = TicketLanguage::get()->getTag();
        $seat     = trim((string) $ticket->seatid);
        $row      = trim((string) $ticket->row_name);
        $holder   = Wallet::holder($ticket);

        $object = [
            'id'                 => $issuerId . '.ts_o' . (int) $ticket->orderid . '_' . substr(sha1($classId . '|' . $ticket->barcode), 0, 12),
            'classId'            => $classId,
            'state'              => 'ACTIVE',
            'barcode'            => ['type' => 'QR_CODE', 'value' => (string) $ticket->barcode],
            'ticketNumber'       => $ticket->ordercode . '-' . $ticket->orderid,
            'ticketType'         => self::text(trim(strip_tags((string) $ticket->ticketname)), $language),
            'reservationInfo'    => ['confirmationCode' => (string) $ticket->ordercode],
            'hexBackgroundColor' => $background,
        ];

        if ($holder !== '')
        {
            $object['ticketHolderName'] = $holder;
        }

        if ($seat !== '' && $seat !== '0')
        {
            $object['seatInfo'] = ['seat' => self::text($seat, $language)];

            if ($row !== '')
            {
                $object['seatInfo']['row'] = self::text($row, $language);
            }
        }

        if ($total > 1)
        {
            $object['textModulesData'] = [[
                'id'     => 'number',
                'header' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_NUMBER'),
                'body'   => TicketLanguage::sprintf('COM_TICKETSTATION_WALLET_NUMBER_OF', $number, $total),
            ]];
        }

        return $object;
    }

    /**
     * A LocalizedString with one value.
     */
    private static function text(string $value, string $language): array
    {
        return ['defaultValue' => ['language' => $language, 'value' => $value]];
    }

    /**
     * A JWT signed with RS256.
     */
    private static function jwt(array $claims, string $privateKey): string
    {
        $encode = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

        $unsigned = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']))
            . '.' . $encode(json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (!openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256))
        {
            throw new \RuntimeException('Signing the Google Wallet link failed: ' . (string) openssl_error_string());
        }

        return $unsigned . '.' . $encode($signature);
    }

    /**
     * The logo Google shows on the passes: the pass logo on a square of the pass colour, with
     * room around it because Google shows it as a circle. Null without a usable logo.
     */
    public static function logo(): ?string
    {
        $file = Wallet::logoFile();

        if ($file === null)
        {
            return null;
        }

        [$background] = Wallet::colors();

        return Wallet::fitImage($file, 660, 660, $background, 0.3);
    }
}
