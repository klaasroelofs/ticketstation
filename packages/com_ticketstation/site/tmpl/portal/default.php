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
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

$app      = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_PORTAL_TITLE') . ' - ' . $app->get('sitename'));
TicketstationFunctions::addSiteStylesheet();

$itemid  = $this->itemid ? '&Itemid=' . $this->itemid : '';
$task    = static fn (string $name) => Route::_('index.php?option=com_ticketstation&task=portal.' . $name . $itemid);
$request = $task('request');
$esc     = static fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

$upcoming = $this->orders ? $this->orders->upcoming : [];
$past     = $this->orders ? $this->orders->past : [];
$hidden   = $this->orders ? $this->orders->hidden : 0;

// The form that mails the link: for visitors without access, for those who only have an order
// link and want all their orders, and for an account that wants another address.
$form = function (string $intro, string $button = 'COM_TICKETSTATION_PORTAL_BUTTON', string $prefill = '') use ($request, $esc) {
    static $count = 0;

    $count++;
    ?>
    <section class="ts-card">
        <p class="ts-lead"><?php echo $intro; ?></p>

        <form class="ts-form" action="<?php echo $request; ?>" method="post">
            <div class="ts-form__fields">
                <div class="ts-field ts-field--email">
                    <label class="ts-label" for="ts-portal-email-<?php echo $count; ?>"><?php echo Text::_('COM_TICKETSTATION_PORTAL_EMAIL'); ?></label>
                    <input class="ts-input" type="email" name="email" id="ts-portal-email-<?php echo $count; ?>" autocomplete="email" value="<?php echo $esc($prefill); ?>" required />
                </div>

                <?php if ($this->captcha) { ?>
                    <div class="ts-field ts-field--captcha">
                        <?php echo $this->captcha; ?>
                    </div>
                <?php } ?>
            </div>

            <div class="ts-actions">
                <button type="submit" class="ts-btn ts-btn--primary"><?php echo Text::_($button); ?></button>
            </div>

            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </section>
    <?php
};

?>

<div class="ticketstation ticketstation--portal">

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_PORTAL_TITLE'); ?></h1>
    </div>

    <?php if ($this->offer !== '') { ?>
        <section class="ts-card ts-portal__offer">
            <p class="ts-lead"><?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_LINK_OFFER', '<strong>' . $esc($this->offer) . '</strong>', '<strong>' . $esc($this->username) . '</strong>'); ?></p>
            <p class="ts-text-muted"><?php echo Text::_('COM_TICKETSTATION_PORTAL_LINK_OFFER_HINT'); ?></p>

            <form action="<?php echo $task('link'); ?>" method="post">
                <div class="ts-actions">
                    <button type="submit" class="ts-btn ts-btn--primary"><?php echo Text::_('COM_TICKETSTATION_PORTAL_LINK_BUTTON'); ?></button>
                </div>
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </section>
    <?php } ?>

    <?php if (!$this->access) { ?>

        <?php $form(Text::_($this->accounts ? 'COM_TICKETSTATION_PORTAL_INTRO_ACCOUNT' : 'COM_TICKETSTATION_PORTAL_INTRO'), 'COM_TICKETSTATION_PORTAL_BUTTON', $this->accountEmail); ?>

    <?php } else { ?>

        <div class="ts-portal__bar">
            <p class="ts-text-muted">
                <?php if ($this->emails) { ?>
                    <?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_SHOWING_FOR', '<strong>' . implode('</strong>, <strong>', array_map($esc, $this->emails)) . '</strong>'); ?>
                <?php } else { ?>
                    <?php echo Text::_('COM_TICKETSTATION_PORTAL_SHOWING_ORDER'); ?>
                <?php } ?>
            </p>

            <?php if ($this->canLeave) { ?>
                <form action="<?php echo $task('leave'); ?>" method="post">
                    <button type="submit" class="ts-btn ts-btn--secondary ts-btn--sm"><?php echo Text::_('COM_TICKETSTATION_PORTAL_LEAVE'); ?></button>
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>
            <?php } ?>
        </div>

        <section class="ts-portal__group">
            <h2 class="ts-portal__heading"><?php echo Text::_('COM_TICKETSTATION_PORTAL_UPCOMING'); ?></h2>

            <?php if (!$upcoming) { ?>
                <p class="ts-text-muted"><?php echo Text::_('COM_TICKETSTATION_PORTAL_NO_UPCOMING'); ?></p>
            <?php } ?>

            <?php foreach ($upcoming as $order) {
                $this->order = $order;
                echo $this->loadTemplate('order');
            } ?>
        </section>

        <?php if ($past || $hidden) { ?>
            <section class="ts-portal__group">
                <details class="ts-portal__past">
                    <summary class="ts-portal__heading"><?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_PAST', count($past)); ?></summary>

                    <p class="ts-text-muted"><?php echo Text::_('COM_TICKETSTATION_PORTAL_PAST_INTRO'); ?></p>

                    <?php foreach ($past as $order) {
                        $this->order = $order;
                        echo $this->loadTemplate('order');
                    } ?>

                    <?php if ($hidden) { ?>
                        <p class="ts-text-muted"><?php echo Text::plural('COM_TICKETSTATION_PORTAL_PAST_HIDDEN', $hidden); ?></p>
                    <?php } ?>
                </details>
            </section>
        <?php } ?>

        <?php if (!$this->emails) { ?>
            <?php $form(Text::_('COM_TICKETSTATION_PORTAL_SEE_ALL'), 'COM_TICKETSTATION_PORTAL_BUTTON', $this->accountEmail); ?>
        <?php } ?>

    <?php } ?>

    <?php if ($this->accounts) { ?>
        <section class="ts-portal__group ts-portal__accounts">
            <h2 class="ts-portal__heading"><?php echo Text::_('COM_TICKETSTATION_PORTAL_ACCOUNT_TITLE'); ?></h2>

            <?php if ($this->links) { ?>
                <p class="ts-text-muted"><?php echo Text::sprintf('COM_TICKETSTATION_PORTAL_ACCOUNT_LINKED', '<strong>' . $esc($this->username) . '</strong>'); ?></p>

                <ul class="ts-portal__links">
                    <?php foreach ($this->links as $link) { ?>
                        <li>
                            <span><?php echo $esc($link->emailaddress); ?></span>
                            <form action="<?php echo $task('unlink'); ?>" method="post">
                                <input type="hidden" name="link" value="<?php echo (int) $link->id; ?>" />
                                <button type="submit" class="ts-btn ts-btn--secondary ts-btn--sm"><?php echo Text::_('COM_TICKETSTATION_PORTAL_UNLINK'); ?></button>
                                <?php echo HTMLHelper::_('form.token'); ?>
                            </form>
                        </li>
                    <?php } ?>
                </ul>
            <?php } else { ?>
                <p class="ts-text-muted"><?php echo Text::_('COM_TICKETSTATION_PORTAL_ACCOUNT_NONE'); ?></p>
            <?php } ?>

            <?php if ($this->access) { ?>
                <details class="ts-portal__past">
                    <summary><?php echo Text::_('COM_TICKETSTATION_PORTAL_ACCOUNT_ADD'); ?></summary>
                    <?php $form(Text::_('COM_TICKETSTATION_PORTAL_ACCOUNT_ADD_INTRO'), 'COM_TICKETSTATION_PORTAL_BUTTON'); ?>
                </details>
            <?php } ?>
        </section>
    <?php } ?>

</div>
