<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Editor\Editor;
use Ticketstation\Component\Ticketstation\Administrator\Helper\eTicketsMessage;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::sprintf('COM_TICKETSTATION_VIEW_EDIT_TEMPLATES_TITLE', $this->data->alias ?? '') . ' - ' . $app->get('sitename'));

$user = $this->getCurrentUser();
$editor = Editor::getInstance($user->getParam('editor', Factory::getConfig()->get('editor')));

$language     = $app->getLanguage();
$mailid       = (int) ($this->data->mailid ?? 0);
$placeholders = eTicketsMessage::placeholders($mailid);
$required     = eTicketsMessage::requiredPlaceholders($mailid);

?>

<form action = "<?php echo Route::_('index.php?option=com_ticketstation&view=templates'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <?php // Filled by assets/js/templates.js when "Preview" is clicked ?>
    <div class="card mb-3" id="ts-template-preview-card" hidden>
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><?= Text::_('COM_TICKETSTATION_TEMPLATE_PREVIEW_TITLE'); ?></strong>
            <button type="button" class="btn-close" id="ts-template-preview-close" aria-label="<?= Text::_('JCLOSE'); ?>"></button>
        </div>
        <div class="card-body">
            <p class="mb-2"><span class="text-muted"><?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILSUBJECT'); ?>:</span> <strong id="ts-template-preview-subject"></strong></p>
            <iframe id="ts-template-preview-frame" class="ts-template-preview-frame" sandbox="" title="<?= Text::_('COM_TICKETSTATION_TEMPLATE_PREVIEW_TITLE'); ?>"></iframe>
            <p class="small text-muted mb-0 mt-2"><?= Text::_('COM_TICKETSTATION_TEMPLATE_PREVIEW_NOTE'); ?></p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h3 class="card-header bg-primary text-white">
                        <?= Text::_('COM_TICKETSTATION_TEMPLATE_MAIL_TEMPLATE'); ?>
                    </h3>
                    <div class="alert alert-info mt-3">
                        <?= Text::sprintf(
                            'COM_TICKETSTATION_TEMPLATE_SENDER_MOVED',
                            Route::_('index.php?option=com_ticketstation&view=configuration')
                        ) ?>
                    </div>
                    <div class="mb-3">
                        <label for="mailsubject" class="form-label"><?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILSUBJECT') ?></label>
                        <input type="text" name="mailsubject" id="mailsubject"
                               class="form-control" maxlength="255"
                               value="<?= htmlspecialchars($this->data->mailsubject ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <div class="mb-3">
                        <label for="mailbody" class="form-label"><?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILBODY') ?></label>
                        <?= $editor->display('mailbody', $this->data->mailbody, '100%', '450', '', '', false); ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h3 class="card-header bg-primary text-white">
                        <?= Text::_('COM_TICKETSTATION_TEMPLATE_DYNAMIC_FIELDS'); ?>
                    </h3>

                    <p class="mt-3 mb-1"><?= Text::_('COM_TICKETSTATION_TEMPLATE_DYNAMIC_FIELDS_DESC'); ?></p>
                    <p class="small text-muted"><?= Text::_('COM_TICKETSTATION_TEMPLATE_CLICK_TO_INSERT'); ?></p>

                    <?php // Exactly the placeholders the code that sends this mail fills in (eTicketsMessage::placeholders()) ?>
                    <?php foreach ($placeholders as $group => $tags) { ?>
                        <h4 class="h5 mt-3"><?= Text::_($group); ?></h4>
                        <table class="table table-sm mb-3">
                            <tbody>
                                <?php foreach ($tags as $tag) {
                                    // A template may describe a placeholder in its own words (e.g. {price} on the invoice mail).
                                    $key = 'COM_TICKETSTATION_TEMPLATE_FIELD_' . strtoupper($tag);
                                    $key = $language->hasKey($key . '_' . $mailid) ? $key . '_' . $mailid : $key;
                                    ?>
                                    <tr>
                                        <td class="w-50">
                                            <button type="button" class="btn btn-link p-0 ts-template-insert" data-tag="{<?= $tag ?>}"
                                                    title="<?= Text::_('COM_TICKETSTATION_TEMPLATE_INSERT'); ?>"><code>{<?= $tag ?>}</code></button>
                                        </td>
                                        <td>
                                            <?= Text::_($key); ?>
                                            <?php if (in_array($tag, $required, true)) { ?>
                                                <span class="badge bg-danger ms-1"><?= Text::_('COM_TICKETSTATION_TEMPLATE_FIELD_REQUIRED'); ?></span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="templates" />
    <input type="hidden" name="task" value="" />
    <input type="hidden" name="cid" value="<?= $this->data->mailid; ?>" />
    <input type="hidden" name="mailid" value="<?= $this->data->mailid; ?>" />
    <?= HTMLHelper::_( 'form.token' ); ?>
</form>
