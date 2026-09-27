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
                    <label for="mailsubject" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILSUBJECT') ?>">
                        <?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILSUBJECT') ?>
                    </label>
                    <div class="col-sm-9">
                        <input type="text" name="mailsubject" id="mailsubject"
                               class="form-control"
                               value="<?= htmlspecialchars($this->data->mailsubject ?? '', ENT_QUOTES, 'UTF-8'); ?>"/>
                    </div>
                    <label for="mailbody" class="col-sm-3 col-form-label"
                           rel="popover"
                           title="<?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILBODY') ?>">
                        <?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILBODY') ?>
                    </label>
                    <div class="col-sm-9">
                        <?= $editor->display('mailbody', $this->data->mailbody, '500', '500', '', '', false); ?>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h3 class="card-header bg-primary text-white">
                        <?= Text::_('COM_TICKETSTATION_TEMPLATE_DYNAMIC_FIELDS'); ?>
                    </h3>

                    <p class="mt-3"><?= Text::_('COM_TICKETSTATION_TEMPLATE_DYNAMIC_FIELDS_DESC'); ?></p>

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
                                        <td class="w-50"><code>{<?= $tag ?>}</code></td>
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
