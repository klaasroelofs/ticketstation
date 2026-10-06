<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Editor\Editor;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

defined('_JEXEC') or die('Restricted Access');

$app    = Factory::getApplication();
$editor = Editor::getInstance($app->get('editor'));

// Buttons that fill in a suggested subject and text; the admin edits them before sending.
$presets = ['time' => 'TIME', 'location' => 'LOCATION', 'cancel' => 'CANCEL'];

$name    = $this->event->eventname;
$subject = $this->draft['subject'] ?? '';
$message = $this->draft['message'] ?? '';

$js = [];

foreach ($presets as $key => $suffix) {
    $js[$key] = [
        'subject' => Text::sprintf('COM_TICKETSTATION_EVENTMAIL_PRESET_' . $suffix . '_SUBJECT', $name),
        'text'    => Text::sprintf('COM_TICKETSTATION_EVENTMAIL_PRESET_' . $suffix . '_TEXT', $name),
    ];
}

$summary = $this->summary;
$status  = $this->walletStatus;

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&controller=eventmail'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="card mb-3">
        <h3 class="card-header"><span class="fa fa-envelope me-2" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_MESSAGE_TITLE'); ?></h3>
        <div class="card-body">
            <p><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_MESSAGE_INTRO'); ?></p>

            <?php if ($this->buyers === 0) { ?>
                <div class="alert alert-warning"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_NO_BUYERS'); ?></div>
            <?php } else { ?>
                <div class="alert alert-info">
                    <?php echo Text::plural('COM_TICKETSTATION_EVENTMAIL_BUYERS', $this->buyers); ?>
                    <?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_INTRO'); ?>
                </div>
            <?php } ?>

            <div class="mb-3">
                <span class="form-label d-block"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_PRESETS'); ?></span>
                <?php foreach ($presets as $key => $suffix) { ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm me-1" data-preset="<?php echo $key; ?>"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_PRESET_' . $suffix); ?></button>
                <?php } ?>
                <div class="form-text"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_PRESETS_DESC'); ?></div>
            </div>

            <div class="mb-3">
                <label for="subject" class="form-label"><?php echo Text::_('COM_TICKETSTATION_TEMPLATE_MAILSUBJECT'); ?></label>
                <input type="text" name="subject" id="subject" class="form-control" value="<?php echo htmlspecialchars($subject, ENT_QUOTES, 'UTF-8'); ?>">
            </div>

            <div class="mb-3">
                <label for="message" class="form-label"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_MESSAGE'); ?></label>
                <?php echo $editor->display('message', $message, '100%', '300', '60', '15', false); ?>
                <div class="form-text"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_MESSAGE_DESC'); ?></div>
            </div>

            <div class="mb-3">
                <span class="form-label d-block"><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_VIA'); ?></span>
                <?php // An unchecked box sends nothing; this makes "off" arrive as 0 ?>
                <input type="hidden" name="send_mail" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="send_mail" id="send_mail" value="1" checked>
                    <label class="form-check-label" for="send_mail"><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_VIA_MAIL'); ?></label>
                </div>
                <?php if ($this->walletOn) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="send_wallet" id="send_wallet" value="1" <?php echo $this->passes['total'] ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="send_wallet"><?php echo Text::plural('COM_TICKETSTATION_COMMUNICATION_VIA_WALLET', $this->passes['total']); ?></label>
                    </div>
                    <div class="form-text"><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_VIA_WALLET_DESC'); ?></div>
                <?php } ?>
            </div>

            <button type="button" class="btn btn-outline-primary me-2" onclick="Joomla.submitbutton('sendtest')">
                <span class="fa fa-vial me-1" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_SEND_TEST'); ?>
            </button>
            <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('send')">
                <span class="fa fa-paper-plane me-1" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_SEND'); ?>
            </button>

            <p class="mt-3 mb-0 small text-muted">
                <?php echo Text::sprintf('COM_TICKETSTATION_EVENTMAIL_TEMPLATE_HINT', Route::_('index.php?option=com_ticketstation&controller=templates&task=edit&cid=7')); ?>
                <?php echo Text::sprintf('COM_TICKETSTATION_EVENTMAIL_REFUND_HINT', Route::_('index.php?option=com_ticketstation&view=boxoffice')); ?>
            </p>
        </div>
    </div>

    <div class="card mb-3">
        <h3 class="card-header"><span class="fa fa-wallet me-2" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_WALLETS_TITLE'); ?></h3>
        <div class="card-body">
            <p><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_WALLETS_INTRO'); ?></p>

            <?php if (!$this->walletOn) { ?>
                <div class="alert alert-secondary mb-0">
                    <?php echo Text::sprintf('COM_TICKETSTATION_COMMUNICATION_WALLETS_OFF_HINT', Route::_('index.php?option=com_ticketstation&view=configuration')); ?>
                </div>
            <?php } else { ?>
                <?php if ($this->passes['total'] === 0) { ?>
                    <div class="alert alert-warning"><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_NO_PASSES'); ?></div>
                <?php } else { ?>
                    <div class="alert alert-info">
                        <?php echo Text::sprintf('COM_TICKETSTATION_COMMUNICATION_PASSES', $this->passes['total'], $this->passes['google'], $this->passes['apple']); ?>
                    </div>
                <?php } ?>

                <button type="button" class="btn btn-primary" onclick="Joomla.submitbutton('updatewallets')" <?php echo $this->passes['total'] ? '' : 'disabled'; ?>>
                    <span class="fa fa-sync-alt me-1" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_WALLETS_BUTTON'); ?>
                </button>
            <?php } ?>
        </div>
    </div>

    <div class="card mb-3">
        <h3 class="card-header"><span class="fa fa-history me-2" aria-hidden="true"></span><?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_STATUS_TITLE'); ?></h3>
        <div class="card-body">
            <ul class="mb-0">
                <li>
                    <?php if ($summary['messages'] > 0) { ?>
                        <?php echo Text::sprintf('COM_TICKETSTATION_COMMUNICATION_STATUS_MESSAGES', $summary['messages'], $summary['lastMessage']); ?>
                    <?php } else { ?>
                        <?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_STATUS_NO_MESSAGES'); ?>
                    <?php } ?>
                </li>
                <li>
                    <?php if ($summary['reminders'] > 0) { ?>
                        <?php echo Text::sprintf('COM_TICKETSTATION_COMMUNICATION_STATUS_REMINDERS', $summary['reminders'], $summary['lastReminder']); ?>
                    <?php } else { ?>
                        <?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_STATUS_NO_REMINDERS'); ?>
                    <?php } ?>
                </li>
                <?php if ($this->walletOn && $this->walletMessages['total'] > 0) { ?>
                    <li>
                        <?php echo Text::sprintf('COM_TICKETSTATION_COMMUNICATION_STATUS_WALLET_MESSAGES', $this->walletMessages['total'], $this->walletMessages['last']); ?>
                    </li>
                <?php } ?>
                <?php if ($this->walletOn) { ?>
                    <li>
                        <?php if ($status['pending'] === 0) { ?>
                            <?php echo Text::_('COM_TICKETSTATION_COMMUNICATION_STATUS_WALLET_DONE'); ?>
                        <?php } else { ?>
                            <?php echo Text::sprintf('COM_TICKETSTATION_COMMUNICATION_STATUS_WALLET_PENDING', $status['pending']); ?>
                            <?php if ($status['failed'] > 0) { ?>
                                <span class="text-danger"><?php echo Text::sprintf('COM_TICKETSTATION_COMMUNICATION_STATUS_WALLET_FAILED', $status['failed'], htmlspecialchars($status['error'], ENT_QUOTES, 'UTF-8')); ?></span>
                            <?php } ?>
                        <?php } ?>
                    </li>
                <?php } ?>
            </ul>
        </div>
    </div>

    <script>
        (function () {
            var presets = <?php echo json_encode($js, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;

            document.querySelectorAll('[data-preset]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var preset = presets[button.getAttribute('data-preset')];

                    document.getElementById('subject').value = preset.subject;

                    if (window.Joomla && Joomla.editors && Joomla.editors.instances.message) {
                        Joomla.editors.instances.message.setValue(preset.text);
                    } else {
                        document.getElementById('message').value = preset.text;
                    }
                });
            });
        })();
    </script>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="eventmail" />
    <input type="hidden" name="eventid" value="<?php echo (int) $this->event->eventid; ?>" />
    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>

</form>
