<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installer script for com_ticketstation.
 *
 * An update only adds and overwrites files; files that were dropped from the component stay
 * on the site (and keep working) unless they are deleted here. Paths are relative to JPATH_ROOT.
 */
class com_ticketstationInstallerScript extends InstallerScript
{
    protected $deleteFiles = [
        // Frontend statistics view, replaced by the statistics in the backend.
        '/components/com_ticketstation/src/Model/StatisticsModel.php',
        '/components/com_ticketstation/assets/css/statistics.css',
        // Menu item type for the payment result page, which is only reached through redirects.
        '/components/com_ticketstation/tmpl/paymentresult/default.xml',
        // Old Huibuuke/HuiTickets logos, replaced by the Ticketstation logo.
        '/components/com_ticketstation/assets/images/Logo_Huibuuke.png',
        '/administrator/components/com_ticketstation/assets/images/Logo_Huibuuke.png',
        '/administrator/components/com_ticketstation/assets/images/Ticketshop.png',
        '/administrator/components/com_ticketstation/assets/images/qrlogo.jpg',
        '/administrator/components/com_ticketstation/assets/images/qrlogo.png',
        '/administrator/components/com_ticketstation/assets/images/qrlogo.svg',
        // Old fallback ticket design, replaced by the drawn DefaultTicketLayout.
        '/administrator/components/com_ticketstation/assets/etickets/eTicket.pdf',
        // Seat plan settings screen, part of the seat plan editor since 2.6.0.
        '/administrator/components/com_ticketstation/src/Controller/SeatplansettingsController.php',
        '/administrator/components/com_ticketstation/src/Model/SeatplansettingsModel.php',
        '/administrator/components/com_ticketstation/src/Table/SeatplansettingsTable.php',
        '/administrator/components/com_ticketstation/forms/seatplansettings.xml',
        // "Rotate your phone" image; the seat chart scales with the screen since 2.6.0.
        '/components/com_ticketstation/assets/images/rotate-phone.gif',
        // Stylesheet of the old seat plan editor.
        '/administrator/components/com_ticketstation/assets/css/seatchart.css',
        // Unused payment helper; payments go through PaymentAPI.
        '/administrator/components/com_ticketstation/src/Helper/Payment.php',
        // Own class loader for the libraries below; Composer's autoloader in site/vendor since 2.10.0.
        '/administrator/components/com_ticketstation/autoloader.php',
        '/components/com_ticketstation/autoloader.php',
    ];

    protected $deleteFolders = [
        // Mollie log, readable from the web; now in Joomla's log folder.
        '/administrator/components/com_ticketstation/assets/log',
        '/administrator/components/com_ticketstation/src/View/Seatplansettings',
        '/administrator/components/com_ticketstation/tmpl/seatplansettings',
        '/components/com_ticketstation/src/View/Statistics',
        '/components/com_ticketstation/tmpl/statistics',
        // Copies of FPDF/FPDI and the QR code libraries (FPDI 2.5.0 had known security issues);
        // they come through Composer in site/vendor since 2.10.0, the fonts in admin/assets/fonts/pdf.
        '/administrator/components/com_ticketstation/src/Helper/PDF',
        '/administrator/components/com_ticketstation/src/Helper/BaconQrCode',
        '/administrator/components/com_ticketstation/src/Helper/DASPRiD',
        '/administrator/components/com_ticketstation/src/Helper/Endroid',
        '/administrator/components/com_ticketstation/src/Helper/cache',
    ];

    public function postflight($type, $parent)
    {
        if ($type === 'update')
        {
            $this->moveSeatchartImages();
            $this->renameTicketFiles();
            $this->removeFiles();
        }

        if ($type === 'install' || $type === 'update')
        {
            $this->setDefaultPermissions();
            $this->grantTestShop();
        }

        return true;
    }

    /**
     * Default permissions (see admin/access.xml), set when the component has no rules of its own
     * yet: on a new install and on the first update to a version with permissions (2.8.0).
     * Rules an admin has set are never touched. Without them the Ticketstation actions would be
     * denied to everyone but Super Users, so Administrators would lose what they could do before.
     *
     * Manager: open the component, Box Office, reservations and scanners (the core create, edit,
     * publish and delete rights come from the Global Configuration). Administrator: everything,
     * including permissions, configuration, payments, finance and deleting orders.
     */
    private function setDefaultPermissions(): void
    {
        try
        {
            $db    = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('rules')])
                ->from($db->quoteName('#__assets'))
                ->where($db->quoteName('name') . ' = ' . $db->quote('com_ticketstation'));

            $asset = $db->setQuery($query)->loadObject();

            if (!$asset || !in_array(trim((string) $asset->rules), ['', '{}', '[]'], true))
            {
                return;
            }

            // Joomla's standard groups, found by title in case a site's ids differ.
            $query = $db->getQuery(true)
                ->select([$db->quoteName('title'), $db->quoteName('id')])
                ->from($db->quoteName('#__usergroups'))
                ->whereIn($db->quoteName('title'), ['Manager', 'Administrator'], \Joomla\Database\ParameterType::STRING);

            $groups        = $db->setQuery($query)->loadAssocList('title', 'id');
            $manager       = (string) ($groups['Manager'] ?? '');
            $administrator = (string) ($groups['Administrator'] ?? '');

            $defaults = [
                'core.admin'                 => [$administrator],
                'core.options'               => [$administrator],
                'core.manage'                => [$manager, $administrator],
                'ticketstation.boxoffice'    => [$manager, $administrator],
                'ticketstation.reserve'      => [$manager, $administrator],
                'ticketstation.scanners'     => [$manager, $administrator],
                'ticketstation.testshop'     => [$manager, $administrator],
                'ticketstation.payment'      => [$administrator],
                'ticketstation.finance'      => [$administrator],
                'ticketstation.order.delete' => [$administrator],
            ];

            $rules = [];

            foreach ($defaults as $action => $groupIds)
            {
                foreach (array_filter($groupIds) as $groupId)
                {
                    $rules[$action][$groupId] = 1;
                }
            }

            $update = (object) ['id' => (int) $asset->id, 'rules' => json_encode($rules)];
            $db->updateObject('#__assets', $update, 'id');
        }
        catch (\Throwable $e)
        {
            // Not fatal: the permissions can still be set by hand under Options.
        }
    }

    /**
     * "In test mode, order on the website" (ticketstation.testshop, since 2.25.1) closes the shop in
     * test mode to everyone without it. Before, any logged-in user could order then, so Managers and
     * Administrators get it when the rules of the component don't mention it yet; a rule an admin has
     * set (also a deny) is never touched. A new install gets it from setDefaultPermissions().
     */
    private function grantTestShop(): void
    {
        try
        {
            $db    = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
            $query = $db->getQuery(true)
                ->select([$db->quoteName('id'), $db->quoteName('rules')])
                ->from($db->quoteName('#__assets'))
                ->where($db->quoteName('name') . ' = ' . $db->quote('com_ticketstation'));

            $asset = $db->setQuery($query)->loadObject();

            if (!$asset)
            {
                return;
            }

            $rules = json_decode((string) $asset->rules, true);

            if (!is_array($rules) || array_key_exists('ticketstation.testshop', $rules))
            {
                return;
            }

            $query = $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__usergroups'))
                ->whereIn($db->quoteName('title'), ['Manager', 'Administrator'], \Joomla\Database\ParameterType::STRING);

            foreach ($db->setQuery($query)->loadColumn() as $groupId)
            {
                $rules['ticketstation.testshop'][(string) $groupId] = 1;
            }

            $update = (object) ['id' => (int) $asset->id, 'rules' => json_encode($rules)];
            $db->updateObject('#__assets', $update, 'id');
        }
        catch (\Throwable $e)
        {
            // Not fatal: the permission can still be set by hand under Options.
        }
    }
    /**
     * Until 2.17.0 the PDF with all tickets of an order was eTickets-<ordercode>.pdf and that of
     * an order with one ticket eTicket-<orderid>.pdf; they are Tickets-<ordercode>.pdf and
     * Ticket-<ordercode>.pdf now. Renames the files of existing orders, so their download and
     * ticket mail keep working; a file that can't be renamed is made again when it is needed.
     */
    private function renameTicketFiles(): void
    {
        $folder = JPATH_ADMINISTRATOR . '/components/com_ticketstation/tickets/';

        foreach ((array) glob($folder . 'eTickets-*.pdf') as $old)
        {
            $new = $folder . substr(basename($old), 1);

            if (is_file($new))
            {
                // Made again under the new name already: the old one is outdated
                @unlink($old);
            }
            else
            {
                @rename($old, $new);
            }
        }

        try
        {
            $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');

            foreach ((array) glob($folder . 'eTicket-*.pdf') as $old)
            {
                $orderid = (int) substr(basename($old), 8);

                $query = $db->getQuery(true)
                    ->select($db->quoteName('ordercode'))
                    ->from($db->quoteName('#__ticketstation_orders'))
                    ->where($db->quoteName('orderid') . ' = ' . $orderid);

                $ordercode = (int) $db->setQuery($query)->loadResult();
                $new       = $folder . 'Ticket-' . $ordercode . '.pdf';

                // No order, or a name that is taken (tickets of one order made one by one and not
                // joined yet): leave the file alone, it is made again when it is needed
                if ($ordercode && !is_file($new) && !is_file($folder . 'Tickets-' . $ordercode . '.pdf'))
                {
                    @rename($old, $new);
                }
            }
        }
        catch (\Throwable $e)
        {
            // Not fatal: see above
        }
    }

    /**
     * Seat chart background images used to live in the component's own folder
     * (administrator/components/com_ticketstation/assets/seatcharts/seatchart<ticketid>.png|jpg).
     * Since 2.6.0 they are site media under images/ticketstation/seatplans, referenced by the
     * chart's settings row. Copies every image that isn't referenced yet; the old file stays,
     * so a failed copy loses nothing.
     */
    private function moveSeatchartImages(): void
    {
        $db      = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');
        $oldDir  = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/seatcharts';
        $newDir  = 'images/ticketstation/seatplans';

        if (!is_dir($oldDir))
        {
            return;
        }

        try
        {
            $query = $db->getQuery(true)
                ->select(['id', 'ticketid'])
                ->from($db->quoteName('#__ticketstation_seatplansettings'))
                ->where($db->quoteName('background_image') . " = ''");

            $rows = $db->setQuery($query)->loadObjectList();
        }
        catch (\Throwable $e)
        {
            return;
        }

        foreach ($rows as $row)
        {
            foreach (['png', 'jpg'] as $extension)
            {
                $source = $oldDir . '/seatchart' . (int) $row->ticketid . '.' . $extension;

                if (!is_file($source))
                {
                    continue;
                }

                if (!is_dir(JPATH_ROOT . '/' . $newDir) && !mkdir(JPATH_ROOT . '/' . $newDir, 0755, true))
                {
                    return;
                }

                $target = $newDir . '/seatchart-' . (int) $row->ticketid . '.' . $extension;

                if (is_file(JPATH_ROOT . '/' . $target) || copy($source, JPATH_ROOT . '/' . $target))
                {
                    $update = (object) ['id' => (int) $row->id, 'background_image' => $target];
                    $db->updateObject('#__ticketstation_seatplansettings', $update, 'id');
                }

                break;
            }
        }
    }
}
