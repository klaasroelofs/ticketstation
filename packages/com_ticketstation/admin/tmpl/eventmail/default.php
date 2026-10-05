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

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&controller=eventmail'); ?>" method="post" name="adminForm" id="adminForm">

    <div class="card">
        <div class="card-body">
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
                <span class="form-label d-block"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_SEND_WHAT'); ?></span>
                <?php // An unchecked box sends nothing; this makes "off" arrive as 0 ?>
                <input type="hidden" name="send_mail" value="0">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="send_mail" id="send_mail" value="1" checked>
                    <label class="form-check-label" for="send_mail"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_SEND_MAIL'); ?></label>
                </div>
                <?php if ($this->walletOn) { ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="wallet_update" id="wallet_update" value="1" <?php echo $this->walletPasses ? 'checked' : 'disabled'; ?>>
                        <label class="form-check-label" for="wallet_update"><?php echo Text::plural('COM_TICKETSTATION_EVENTMAIL_WALLET_UPDATE', $this->walletPasses); ?></label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="wallet_message" id="wallet_message" value="1" <?php echo $this->walletPasses ? '' : 'disabled'; ?>>
                        <label class="form-check-label" for="wallet_message"><?php echo Text::plural('COM_TICKETSTATION_EVENTMAIL_WALLET_MESSAGE', $this->walletPasses); ?></label>
                    </div>
                    <div class="form-text"><?php echo Text::_('COM_TICKETSTATION_EVENTMAIL_WALLET_DESC'); ?></div>
                <?php } ?>
            </div>

            <p class="mb-0 small text-muted">
                <?php echo Text::sprintf('COM_TICKETSTATION_EVENTMAIL_TEMPLATE_HINT', Route::_('index.php?option=com_ticketstation&controller=templates&task=edit&cid=7')); ?>
                <?php echo Text::sprintf('COM_TICKETSTATION_EVENTMAIL_REFUND_HINT', Route::_('index.php?option=com_ticketstation&view=boxoffice')); ?>
            </p>
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
