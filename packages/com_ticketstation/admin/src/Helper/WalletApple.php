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

use Joomla\Archive\Zip;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

/**
 * Apple Wallet passes (.pkpass): a zip with pass.json, the images, a manifest with their SHA-1
 * hashes and a detached signature of that manifest, made with the site's Pass Type ID
 * certificate and Apple's WWDR G4 intermediate certificate (bundled in assets/wallet).
 *
 * The certificate comes from the Apple Developer website. The private key it belongs to is made
 * here (createSigningRequest()), so no Mac or Keychain is needed; a .p12 exported from a Mac
 * can be imported too.
 *
 * @since 2.16.0
 */
class WalletApple
{
    /** The intermediate certificate Pass Type ID certificates are issued by. */
    private const WWDR = '/components/com_ticketstation/assets/wallet/AppleWWDRCAG4.pem';

    /** Fallback configuration for PHP builds without an openssl.cnf of their own. */
    private const OPENSSL_CNF = '/components/com_ticketstation/assets/wallet/openssl.cnf';

    /** The icon when the Configuration has no pass logo. */
    private const DEFAULT_ICON = '/components/com_ticketstation/assets/wallet/';

    /**
     * The details of a Pass Type ID certificate, or null when it isn't one.
     *
     * @return  array{passTypeId: string, teamId: string, organisation: string, validTo: int, expired: bool}|null
     */
    public static function certificateInfo(?string $certificate): ?array
    {
        if (!$certificate)
        {
            return null;
        }

        $data = @openssl_x509_parse($certificate);

        // A Pass Type ID certificate names the pass type in UID and the team in OU
        $passTypeId = (string) ($data['subject']['UID'] ?? '');
        $teamId     = (string) ($data['subject']['OU'] ?? '');

        if (!$data || !str_starts_with($passTypeId, 'pass.') || $teamId === '')
        {
            return null;
        }

        return [
            'passTypeId'   => $passTypeId,
            'teamId'       => $teamId,
            'organisation' => (string) ($data['subject']['O'] ?? ''),
            'validTo'      => (int) $data['validTo_time_t'],
            'expired'      => (int) $data['validTo_time_t'] < time(),
        ];
    }

    /**
     * Creates a private key and the certificate signing request (CSR) to upload to Apple.
     *
     * @return  array{0: string, 1: string}  [CSR as PEM, private key as PEM]
     *
     * @throws  \RuntimeException  With a language key
     */
    public static function createSigningRequest(string $commonName, string $email): array
    {
        $subject = array_filter([
            'commonName'   => mb_substr($commonName !== '' ? $commonName : 'Ticketstation', 0, 64),
            'emailAddress' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
        ]);

        // First with PHP's own OpenSSL configuration; on Windows that is often missing, then the
        // bundled minimal one does the job.
        foreach ([[], ['config' => JPATH_ADMINISTRATOR . self::OPENSSL_CNF]] as $extra)
        {
            $options = $extra + ['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 2048, 'digest_alg' => 'sha256'];

            $key = @openssl_pkey_new($options);

            if (!$key)
            {
                continue;
            }

            $csr = @openssl_csr_new($subject, $key, $options);

            if ($csr && @openssl_csr_export($csr, $csrPem) && @openssl_pkey_export($key, $keyPem, null, $options))
            {
                return [$csrPem, $keyPem];
            }
        }

        while (openssl_error_string() !== false)
        {
            // Clear OpenSSL's error queue so it doesn't end up in a later message
        }

        throw new \RuntimeException('COM_TICKETSTATION_WALLET_APPLE_CSR_FAILED');
    }

    /**
     * Reads the certificate downloaded from Apple (.cer, DER or PEM) and finds the private key
     * it belongs to among the given keys.
     *
     * @param   string    $data  The uploaded file
     * @param   string[]  $keys  Private keys (PEM) that may belong to it
     *
     * @return  array{0: string, 1: string}  [certificate as PEM, private key as PEM]
     *
     * @throws  \RuntimeException  With a language key
     */
    public static function importCertificate(string $data, array $keys): array
    {
        $certificate = str_contains($data, '-----BEGIN CERTIFICATE-----')
            ? $data
            : "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($data), 64, "\n") . "-----END CERTIFICATE-----\n";

        self::checkCertificate($certificate);

        foreach (array_filter($keys) as $key)
        {
            if (@openssl_x509_check_private_key($certificate, $key))
            {
                return [self::exportCertificate($certificate), $key];
            }
        }

        throw new \RuntimeException('COM_TICKETSTATION_WALLET_APPLE_KEY_MISMATCH');
    }

    /**
     * Reads a .p12 file exported from the Keychain on a Mac.
     *
     * @return  array{0: string, 1: string}  [certificate as PEM, private key as PEM]
     *
     * @throws  \RuntimeException  With a language key
     */
    public static function importP12(string $data, string $password): array
    {
        if (!@openssl_pkcs12_read($data, $parts, $password))
        {
            while (openssl_error_string() !== false)
            {
            }

            // Wrong password, or the old encryption macOS used to export with, which OpenSSL 3
            // can't read: the certificate request made by Ticketstation avoids both.
            throw new \RuntimeException('COM_TICKETSTATION_WALLET_APPLE_P12_FAILED');
        }

        self::checkCertificate($parts['cert']);

        if (!@openssl_pkey_export($parts['pkey'], $key))
        {
            throw new \RuntimeException('COM_TICKETSTATION_WALLET_APPLE_P12_FAILED');
        }

        return [self::exportCertificate($parts['cert']), $key];
    }

    /**
     * @throws  \RuntimeException  When it isn't a usable Pass Type ID certificate from Apple
     */
    private static function checkCertificate(string $certificate): void
    {
        $info = self::certificateInfo($certificate);

        if ($info === null)
        {
            throw new \RuntimeException('COM_TICKETSTATION_WALLET_APPLE_NOT_A_PASS_CERTIFICATE');
        }

        if ($info['expired'])
        {
            throw new \RuntimeException('COM_TICKETSTATION_WALLET_APPLE_CERTIFICATE_EXPIRED');
        }

        // Issued by the bundled WWDR intermediate, or the passes would never verify
        $issuer = openssl_x509_parse($certificate)['issuer'] ?? [];
        $wwdr   = openssl_x509_parse((string) file_get_contents(JPATH_ADMINISTRATOR . self::WWDR))['subject'] ?? [];

        if (($issuer['CN'] ?? '') !== ($wwdr['CN'] ?? '-') || ($issuer['OU'] ?? '') !== ($wwdr['OU'] ?? '-'))
        {
            throw new \RuntimeException('COM_TICKETSTATION_WALLET_APPLE_UNKNOWN_ISSUER');
        }
    }

    private static function exportCertificate($certificate): string
    {
        openssl_x509_export($certificate, $pem);

        return $pem;
    }

    /**
     * The passes for the tickets of an order: one .pkpass, or for more tickets a .pkpasses
     * bundle that adds them all at once (iOS 15 and later).
     *
     * @param   object[]  $tickets  From Wallet::tickets()
     *
     * @return  array{content: string, type: string, filename: string}
     *
     * @throws  \RuntimeException
     */
    public static function download(array $tickets): array
    {
        $images = self::images();
        $total  = count($tickets);

        if ($total === 1)
        {
            return [
                'content'  => self::pass($tickets[0], 1, 1, $images),
                'type'     => 'application/vnd.apple.pkpass',
                'filename' => 'ticket-' . (int) $tickets[0]->orderid . '.pkpass',
            ];
        }

        $files = [];

        foreach ($tickets as $index => $ticket)
        {
            $files['ticket-' . (int) $ticket->orderid . '.pkpass'] = self::pass($ticket, $index + 1, $total, $images);
        }

        return [
            'content'  => self::zip($files),
            'type'     => 'application/vnd.apple.pkpasses',
            'filename' => 'tickets-' . (int) $tickets[0]->ordercode . '.pkpasses',
        ];
    }

    /**
     * One signed pass.
     *
     * @param   object  $ticket  From Wallet::tickets()
     * @param   int     $number  Its position in the order (1-based)
     * @param   int     $total   The number of tickets in the order
     * @param   array   $images  File name => PNG, see images()
     *
     * @throws  \RuntimeException
     */
    public static function pass(object $ticket, int $number, int $total, array $images): string
    {
        $config = Wallet::config();
        $info   = self::certificateInfo($config->wallet_apple_cert ?? null);

        if ($info === null || empty($config->wallet_apple_key))
        {
            throw new \RuntimeException('Apple Wallet has no certificate.');
        }

        $json  = self::passJson($ticket, $number, $total, $info, isset($images['logo.png']));
        $files = ['pass.json' => json_encode($json, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)] + $images;

        $manifest = [];

        foreach ($files as $name => $content)
        {
            $manifest[$name] = sha1($content);
        }

        $files['manifest.json'] = json_encode($manifest, JSON_UNESCAPED_SLASHES);
        $files['signature']     = self::sign($files['manifest.json'], $config->wallet_apple_cert, $config->wallet_apple_key);

        Wallet::record(Wallet::APPLE, $ticket, $json['passTypeIdentifier'] . '/' . $json['serialNumber']);

        return self::zip($files);
    }

    /**
     * The pass.json of a ticket.
     */
    private static function passJson(object $ticket, int $number, int $total, array $info, bool $hasLogo): array
    {
        [$background, $foreground] = Wallet::colors();

        $organisation = Wallet::organisation();
        $date         = Wallet::isoDate($ticket->startdate);
        $seat         = Wallet::seat($ticket);
        $holder       = Wallet::holder($ticket);
        $address      = Wallet::address($ticket);
        $venue        = trim((string) $ticket->venue);
        $eventname    = trim(strip_tags((string) $ticket->eventname));
        $ticketname   = trim(strip_tags((string) $ticket->ticketname));

        $secondary = [];
        $auxiliary = [];

        if ($date !== '')
        {
            $secondary[] = [
                'key'       => 'date',
                'label'     => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_DATE'),
                'value'     => $date,
                'dateStyle' => 'PKDateStyleMedium',
                'timeStyle' => 'PKTimeStyleShort',
            ];
        }

        if ($venue !== '')
        {
            $secondary[] = ['key' => 'venue', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_VENUE'), 'value' => $venue];
        }

        $auxiliary[] = ['key' => 'ticket', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_TICKET'), 'value' => $ticketname];

        if ($seat !== '')
        {
            $auxiliary[] = ['key' => 'seat', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_SEAT'), 'value' => $seat];
        }

        if ($total > 1)
        {
            $auxiliary[] = ['key' => 'number', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_NUMBER'), 'value' => TicketLanguage::sprintf('COM_TICKETSTATION_WALLET_NUMBER_OF', $number, $total)];
        }

        $back = [
            ['key' => 'order', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_ORDER'), 'value' => (string) $ticket->ordercode],
            ['key' => 'ticketnumber', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_TICKETNUMBER'), 'value' => $ticket->ordercode . '-' . $ticket->orderid],
        ];

        if ($holder !== '')
        {
            $back[] = ['key' => 'holder', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_HOLDER'), 'value' => $holder];
        }

        if ($address !== '')
        {
            $back[] = ['key' => 'address', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_ADDRESS'), 'value' => implode(', ', array_filter([$venue, $address]))];
        }

        $contact = implode("\n", array_filter([
            $organisation,
            trim((string) (Wallet::config()->website ?? '')) ?: rtrim(Uri::root(), '/'),
            trim((string) (Wallet::config()->email ?? '')),
            trim((string) (Wallet::config()->phone ?? '')),
        ]));

        $back[] = ['key' => 'organiser', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_ORGANISER'), 'value' => $contact];

        $pass = [
            'formatVersion'      => 1,
            'passTypeIdentifier' => $info['passTypeId'],
            'teamIdentifier'     => $info['teamId'],
            // Changes with the QR code, so a ticket with a new code is a new pass
            'serialNumber'       => 'ts-' . (int) $ticket->orderid . '-' . substr(sha1((string) $ticket->barcode), 0, 10),
            'organizationName'   => $organisation,
            'description'        => TicketLanguage::sprintf('COM_TICKETSTATION_WALLET_DESCRIPTION', $eventname),
            'backgroundColor'    => self::rgb($background),
            'foregroundColor'    => self::rgb($foreground),
            'labelColor'         => self::rgb($foreground),
            'barcodes'           => [[
                'format'          => 'PKBarcodeFormatQR',
                'message'         => (string) $ticket->barcode,
                'messageEncoding' => 'iso-8859-1',
            ]],
            // For iOS versions before 9
            'barcode'            => [
                'format'          => 'PKBarcodeFormatQR',
                'message'         => (string) $ticket->barcode,
                'messageEncoding' => 'iso-8859-1',
            ],
            'eventTicket'        => [
                'primaryFields'   => [['key' => 'event', 'label' => TicketLanguage::_('COM_TICKETSTATION_WALLET_FIELD_EVENT'), 'value' => $eventname]],
                'secondaryFields' => $secondary,
                'auxiliaryFields' => $auxiliary,
                'backFields'      => $back,
            ],
        ];

        // Without a logo the organisation's name takes its place at the top
        if (!$hasLogo)
        {
            $pass['logoText'] = $organisation;
        }

        // Shows the pass on the lock screen around the start of the event
        if ($date !== '')
        {
            $pass['relevantDate'] = $date;
        }

        return $pass;
    }

    /**
     * The images every pass of this site gets: the icon (required) and the logo, made from the
     * pass logo in the Configuration. Without one the default icon is used and no logo.
     *
     * @return  array<string, string>  File name => PNG
     */
    public static function images(): array
    {
        [$background] = Wallet::colors();

        $logo   = Wallet::logoFile();
        $images = [];

        foreach ([1 => '', 2 => '@2x', 3 => '@3x'] as $scale => $suffix)
        {
            $icon = $logo ? Wallet::fitImage($logo, 29 * $scale, 29 * $scale, $background, 0.1) : null;

            $images['icon' . $suffix . '.png'] = $icon ?? (string) file_get_contents(JPATH_ADMINISTRATOR . self::DEFAULT_ICON . 'icon' . $suffix . '.png');

            if ($logo && ($png = Wallet::fitImage($logo, 160 * $scale, 50 * $scale)) !== null)
            {
                $images['logo' . $suffix . '.png'] = $png;
            }
        }

        return $images;
    }

    /**
     * The detached signature (DER) of the manifest.
     *
     * openssl_pkcs7_sign() only writes S/MIME, so the signature is taken from its smime.p7s
     * part. Not openssl_cms_sign(): its signatures failed verification by OpenSSL itself
     * (content verify error) in tests with PHP 8.3 / OpenSSL 3.
     *
     * @throws  \RuntimeException
     */
    private static function sign(string $manifest, string $certificate, string $key): string
    {
        $tmp    = (string) Factory::getApplication()->get('tmp_path') ?: sys_get_temp_dir();
        $input  = tempnam(is_dir($tmp) && is_writable($tmp) ? $tmp : sys_get_temp_dir(), 'tsw');
        $output = $input . '.p7s';

        try
        {
            file_put_contents($input, $manifest);

            $signed = openssl_pkcs7_sign(
                $input,
                $output,
                $certificate,
                $key,
                [],
                PKCS7_BINARY | PKCS7_DETACHED,
                JPATH_ADMINISTRATOR . self::WWDR
            );

            $smime = $signed && is_file($output) ? (string) file_get_contents($output) : '';

            // The base64 body of the smime.p7s part: after its headers, up to the next boundary
            $start = strpos($smime, 'smime.p7s');
            $body  = $start !== false && preg_match('/\R\R(.+?)\R\R?--/s', $smime, $match, 0, $start) ? $match[1] : '';
            $der   = base64_decode(preg_replace('/\s+/', '', $body), true);

            if (!$der)
            {
                throw new \RuntimeException('Signing the Apple Wallet pass failed: ' . (string) openssl_error_string());
            }

            return $der;
        }
        finally
        {
            @unlink($input);
            @unlink($output);
        }
    }

    /**
     * A zip of the given files (name => content), as a string.
     */
    private static function zip(array $files): string
    {
        $tmp  = (string) Factory::getApplication()->get('tmp_path') ?: sys_get_temp_dir();
        $file = tempnam(is_dir($tmp) && is_writable($tmp) ? $tmp : sys_get_temp_dir(), 'tsw');

        try
        {
            // PHP's zip extension when it's there, else Joomla's own zip writer
            if (class_exists(\ZipArchive::class))
            {
                $zip = new \ZipArchive();

                if ($zip->open($file, \ZipArchive::OVERWRITE) !== true)
                {
                    throw new \RuntimeException('Creating the Apple Wallet pass failed: no zip file.');
                }

                foreach ($files as $name => $content)
                {
                    $zip->addFromString($name, $content);
                }

                $zip->close();
            }
            else
            {
                $entries = [];

                foreach ($files as $name => $content)
                {
                    $entries[] = ['name' => $name, 'data' => $content, 'time' => time()];
                }

                (new Zip)->create($file, $entries);
            }

            return (string) file_get_contents($file);
        }
        finally
        {
            @unlink($file);
        }
    }

    /**
     * #rrggbb as the rgb(r, g, b) notation pass.json wants.
     */
    private static function rgb(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return 'rgb(' . $r . ', ' . $g . ', ' . $b . ')';
    }
}
