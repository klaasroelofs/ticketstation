<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticket scanning topic on the central documentation page. Content lives here now;
 * the Scanners list screen (admin/tmpl/scanners/default.php) only links to it.
 */

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$scanningUrl = Uri::root() . 'index.php?option=com_ticketstation&view=ticketscanning';
$endpoint    = Uri::root() . 'index.php?option=com_ticketstation&controller=codescanner&task=validation';

$steps = function (string $prefix, int $count, array $args = []) {
    echo '<ol>';
    for ($i = 1; $i <= $count; $i++) {
        $key = $prefix . $i;
        echo '<li class="mb-1">' . (isset($args[$i]) ? Text::sprintf($key, $args[$i]) : Text::_($key)) . '</li>';
    }
    echo '</ol>';
};

$exampleRequest = "curl -H \"X-Scanner-Key: <API key>\" \\\n     \"" . $endpoint . "&tid=<QR code>&eventid=12\"";

$exampleResponse = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<xml>
    <message>
        <status>1</status>
        <text>Ticket goedgekeurd</text>
        <order>ABC123-10234</order>
        <totalscanned>57</totalscanned>
    </message>
</xml>
XML;

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-qrcode me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_INTRO') ?></p>
        <p class="text-muted small"><?= Text::sprintf('COM_TICKETSTATION_DOCS_SCREEN_LINK', '<a href="index.php?option=com_ticketstation&view=scanners">' . Text::_('COM_TICKETSTATION_VIEW_SCANNERS_TITLE') . '</a>') ?></p>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-mobile-alt me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_WEB_TITLE') ?></summary>

            <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_SETUP') ?></h3>
            <?php $steps('COM_TICKETSTATION_SCANNING_DOCS_WEB_SETUP_', 5, [4 => '<a href="' . $this->escape($scanningUrl) . '" target="_blank" rel="noopener"><code>' . $this->escape($scanningUrl) . '</code></a>']); ?>

            <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_USE') ?></h3>
            <?php $steps('COM_TICKETSTATION_SCANNING_DOCS_WEB_USE_', 5); ?>
        </details>

        <details class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-barcode me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_TITLE') ?></summary>

            <p class="mt-3"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_INTRO') ?></p>

            <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_SETUP') ?></h3>
            <?php $steps('COM_TICKETSTATION_SCANNING_DOCS_HW_SETUP_', 3); ?>

            <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_REQUEST') ?></h3>
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <th scope="row" class="w-25"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_ADDRESS') ?></th>
                        <td><code><?= $this->escape($endpoint) ?></code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_METHOD') ?></th>
                        <td><code>GET</code> / <code>POST</code></td>
                    </tr>
                    <tr>
                        <th scope="row"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_AUTH') ?></th>
                        <td><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_AUTH_DESC') ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><code>tid</code></th>
                        <td><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_TID_DESC') ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><code>eventid</code> / <code>ticketid</code></th>
                        <td><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_FILTER_DESC') ?></td>
                    </tr>
                </tbody>
            </table>

            <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_EXAMPLE') ?></h3>
            <pre class="bg-light border rounded p-2"><code><?= $this->escape($exampleRequest) ?></code></pre>

            <h3 class="h6 mt-3"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_RESPONSE') ?></h3>
            <pre class="bg-light border rounded p-2"><code><?= $this->escape($exampleResponse) ?></code></pre>
            <table class="table table-sm">
                <tbody>
                    <tr>
                        <th scope="row" class="w-25"><code>status</code></th>
                        <td><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_STATUS_DESC') ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><code>text</code></th>
                        <td><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_TEXT_DESC') ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><code>order</code></th>
                        <td><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_ORDER_DESC') ?></td>
                    </tr>
                    <tr>
                        <th scope="row"><code>totalscanned</code></th>
                        <td><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_HW_TOTAL_DESC') ?></td>
                    </tr>
                </tbody>
            </table>
        </details>

        <details class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-check-double me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_CHECKS_TITLE') ?></summary>
            <p class="mt-3 mb-0"><?= Text::_('COM_TICKETSTATION_SCANNING_DOCS_CHECKS') ?></p>
        </details>
    </div>
</div>
