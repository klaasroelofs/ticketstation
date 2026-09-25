<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app        = Factory::getApplication();
$wa         = $app->getDocument()->getWebAssetManager();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_TICKETSCANNER_TITLE') . ' - ' . $app->get('sitename'));
$document->setMetaData('viewport', 'width=device-width, initial-scale=1, viewport-fit=cover');

$document->addStyleSheet( 'components/com_ticketstation/assets/css/scanner.css' );
$wa->registerAndUseStyle('fontawesome-free.all', 'media/vendor/fontawesome-free/css/all.min.css', [], [], []);

// The scanner page stays open for hours: keep the session (and so the scan token) alive.
HTMLHelper::_('behavior.keepalive');

if (!empty($this->event)) {
    $event  = $this->event->eventname;
    $ticket = '';
    $target = ['eventid' => (int) $this->event->eventid];
} else {
    $event  = $this->ticket->eventname;
    $ticket = $this->ticket->ticketname;
    $target = ['ticketid' => (int) $this->ticket->ticketid];
}

$itemid   = TicketstationFunctions::getSiteItemid();
$linkback = Route::_('index.php?option=com_ticketstation&view=ticketscanning' . ($itemid ? '&Itemid=' . $itemid : ''), false);
$root     = Uri::root(true);
$script   = '/components/com_ticketstation/assets/javascripts/ticketscanner.js';

$config = [
    'scanUrl'      => $root . '/index.php?option=com_ticketstation&controller=codescanner&task=scan',
    'token'        => Session::getFormToken(),
    'target'       => $target,
    'totalsVisible'=> (int) $this->scanner->totals_visible === 1,
    'manualEntry'  => $this->scanner->manual_entry === 1,
    'sounds'       => [
        'success' => $root . '/components/com_ticketstation/assets/sounds/success.mp3',
        'error'   => $root . '/components/com_ticketstation/assets/sounds/error.mp3',
    ],
    'texts'        => [
        'network'      => Text::_('COM_TICKETSTATION_TICKETSCANNING_NETWORK_ERROR'),
        'camera'       => Text::_('COM_TICKETSTATION_TICKETSCANNING_CAMERA_ERROR'),
        'unauthorized' => Text::_('COM_TICKETSTATION_TICKETSCANNING_UNAUTHORIZED'),
    ],
];

?>

<div class="ticketstation">

    <div id="container">
        <div class="btn-scan-background">
            <div id="btn-scan-qr">
                <?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_START'); ?>
            </div>
        </div>

        <div id="scanpage-title-underlay" class="scanpage-title-underlay">
			<span>
				<?php if ($ticket !== '') { ?>
                    <h3 style="margin:5px 0px;"><strong><?php echo $this->escape($event); ?></strong></h3>
                    <h4 style="margin-top:5px;"><strong><?php echo $this->escape($ticket); ?></strong></h4>
                <?php } else { ?>
                    <h3 style="margin-top:18px;"><strong><?php echo $this->escape($event); ?></strong></h3>
                <?php } ?>
			</span>
            <span class="scanpage-copyright">
				© Klaas Roelofs
			</span>
        </div>

        <div id="video-container">
            <div id="scan-region-highlight" style="display:none;">
                <svg class="scan-region-highlight-svg" viewBox="0 0 238 238" preserveAspectRatio="none" style="position:absolute;width:100%;height:100%;left:0;top:0;fill:none;stroke:#fff;stroke-width:5;stroke-linecap:round;stroke-linejoin:round;"><path d="M31 2H10a8 8 0 0 0-8 8v21M207 2h21a8 8 0 0 1 8 8v21m0 176v21a8 8 0 0 1-8 8h-21m-176 0H10a8 8 0 0 1-8-8v-21"></path></svg>
            </div>
            <video id="qr-video" muted playsinline disablepictureinpicture></video>
        </div>

        <div hidden id="scanresultcontainer" class="scanresultcontainer" role="alert">
            <div>
                <span id="scanresultorder"></span>
            </div>
            <div>
                <span id="scanresulttext"></span>
            </div>
        </div>

        <div hidden id="manualentrycontainer">
            <h3 style="text-align:left;text-transform:uppercase;"><strong><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_MANUAL_CHECK'); ?></strong></h3>
            <form class="manualentryform">
                <label for="manualentryInput" style="font-size:initial;"><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_TICKET_ID'); ?>: </label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="9" autocomplete="off" name="manualentryInput" id="manualentryInput" required>

                <button type="submit" class="btn btn-primary btn-manualsubmit" style="margin-top:20px;">
                    <?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_CHECK'); ?>
                </button>
            </form>
        </div>

        <div hidden id="scanhistorycontainer">
            <h4 id="scanhistorytitle" class="scanpage-textshadow"><strong><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_PREVIOUS_RESULT'); ?>:</strong></h4>
            <div id="scanhistory" class="scanhistory scanpage-boxshadow">
                <div>
                    <span id="scanhistoryorder"></span>
                </div>
                <div>
                    <span id="scanhistorytext"></span>
                </div>
            </div>
        </div>

        <div id="control-bar" class="control-bar">
			<span id="backbutton">
				<a class="btn btn-primary btn-back" href="<?php echo $this->escape($linkback); ?>">
					<span><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_BACK'); ?></span>
				</a>
			</span>
            <span hidden id="stopscanning">
				<a class="btn btn-primary btn-stopscanning">
					<span><?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_STOP'); ?></span>
				</a>
			</span>
            <span id="controls">
				<span hidden id="manualentry">
					<a id="btn-manualentry" class="btn btn-manualentry" aria-label="<?php echo $this->escape(Text::_('COM_TICKETSTATION_TICKETSCANNER_MANUAL_ENTRY')); ?>">
						<span id="manualentry-icon" class="bi bi-pen"></span>
					</a>
				</span>
				<span hidden id="torch">
					<a id="btn-torch" class="btn btn-torch" aria-label="<?php echo $this->escape(Text::_('COM_TICKETSTATION_TICKETSCANNER_TORCH')); ?>">
						<span id="torch-icon" class="bi bi-lightbulb-off"></span>
					</a>
				</span>
				<span id="sound">
					<a id="btn-sound" class="btn btn-sound sound-on" aria-label="<?php echo $this->escape(Text::_('COM_TICKETSTATION_TICKETSCANNER_SOUND')); ?>">
						<span id="sound-icon" class="fas fa-volume-up"></span>
					</a>
				</span>
				<span hidden id="vibrate">
					<a id="btn-vibrate" class="btn btn-vibrate vibrate-on" aria-label="<?php echo $this->escape(Text::_('COM_TICKETSTATION_TICKETSCANNER_VIBRATE')); ?>">
						<span id="vibrate-icon" class="bi bi-phone-vibrate"></span>
					</a>
				</span>
			</span>
            <span hidden id="scanned-tickets">
				<?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_TOTAL_SCANNED'); ?>: <span id="total-scanned"></span>/<?php echo (int) $this->sold; ?>
			</span>
            <span hidden id="scanned-tickets-session">
				<?php echo Text::_('COM_TICKETSTATION_TICKETSCANNER_SCANNED_BY_YOU'); ?>: <span id="total-scanned-session">0</span>
			</span>
        </div>

    </div>

</div>

<script type="application/json" id="ticketscanner-config"><?php echo json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES); ?></script>
<script type="module" src="<?php echo $root . $script . '?v=' . @filemtime(JPATH_SITE . $script); ?>"></script>
