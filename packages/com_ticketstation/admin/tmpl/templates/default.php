<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE') . ' - ' . $app->get('sitename'));
$wa = $document->getWebAssetManager();
$wa->registerAndUseStyle('ticketstation', Uri::base() . 'components\com_ticketstation\assets\css\ticketstation.css');

$language = $app->getLanguage();

// A mail the code knows gets its own name and a line about when it is sent; anything else
// (a mail added by hand) falls back on its alias.
$text = function (string $key, string $fallback) use ($language) {
    return $language->hasKey($key) ? Text::_($key) : $fallback;
};

// The body is HTML written in the editor: show a plain-text excerpt, never the markup itself.
$excerpt = function (string $html) {
    $plain = html_entity_decode(strip_tags(preg_replace('#<(br|/p|/li|/div|/h\d)[^>]*>#i', ' ', $html)), ENT_QUOTES, 'UTF-8');
    $plain = trim(preg_replace('/\s+/u', ' ', $plain));

    return mb_strimwidth($plain, 0, 180, '…', 'UTF-8');
};

?>

<form action="<?= Route::_('index.php?option=com_ticketstation&view=templates'); ?>" method="POST" name="adminForm" id="adminForm">
    <p class="text-muted"><?= Text::_('COM_TICKETSTATION_TEMPLATES_LIST_INTRO') ?></p>

    <table class="table table-striped align-middle ts-templates">
        <caption class="visually-hidden"><?= Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE') ?></caption>
        <thead>
            <tr>
                <th scope="col" class="w-25"><?= Text::_('COM_TICKETSTATION_TEMPLATE') ?></th>
                <th scope="col" class="w-25"><?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILSUBJECT') ?></th>
                <th scope="col"><?= Text::_('COM_TICKETSTATION_TEMPLATE_MAILBODY') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($this->items as $row) {
            $link = Route::_('index.php?option=com_ticketstation&controller=templates&task=edit&cid=' . (int) $row->mailid);
            $name = $text('COM_TICKETSTATION_TEMPLATE_NAME_' . (int) $row->mailid, (string) $row->alias);
            $when = $text('COM_TICKETSTATION_TEMPLATE_WHEN_' . (int) $row->mailid, '');
            ?>
            <tr>
                <th scope="row">
                    <a href="<?= $link ?>" class="fw-bold"><span class="icon-edit me-1" aria-hidden="true"></span><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?></a>
                    <?php if ($when !== '') { ?>
                        <div class="small text-muted fw-normal mt-1"><?= $when ?></div>
                    <?php } ?>
                </th>
                <td><code class="text-break"><?= htmlspecialchars((string) $row->mailsubject, ENT_QUOTES, 'UTF-8') ?></code></td>
                <td class="small text-muted"><?= htmlspecialchars($excerpt((string) $row->mailbody), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php } ?>
        </tbody>
    </table>

    <input name="option" type="hidden" value="com_ticketstation" />
    <input name="controller" type="hidden" value="templates"/>
    <input name="task" type="hidden" value="" />
    <?= HTMLHelper::_('form.token'); ?>
</form>
