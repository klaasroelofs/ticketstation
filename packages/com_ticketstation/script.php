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
    ];

    protected $deleteFolders = [
        '/administrator/components/com_ticketstation/src/View/Seatplansettings',
        '/administrator/components/com_ticketstation/tmpl/seatplansettings',
        '/components/com_ticketstation/src/View/Statistics',
        '/components/com_ticketstation/tmpl/statistics',
    ];

    public function postflight($type, $parent)
    {
        if ($type === 'update')
        {
            $this->moveSeatchartImages();
            $this->removeFiles();
        }

        return true;
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
