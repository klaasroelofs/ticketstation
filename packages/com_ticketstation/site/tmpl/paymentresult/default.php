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
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PersonName;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
TicketstationFunctions::addSiteStylesheet();

if (!$this->authorized) {
    $document->setTitle( Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER') . ' - ' . $app->get('sitename') );
    $result = 'unknown';
} elseif ($this->unpaid->total > 0) {
    $document->setTitle( Text::_('COM_TICKETSTATION_PAYMENTRESULT_FAILED_PAGE_TITLE') . ' - ' . $app->get('sitename') );
    $result = 'failed';
} else {
    $document->setTitle( Text::_('COM_TICKETSTATION_PAYMENTRESULT_SUCCESS_PAGE_TITLE') . ' - ' . $app->get('sitename') );
    $result = 'success';
}

## Contact address shown to the customer when something needs checking (Configuration > Company)
$contactEmail = htmlspecialchars($this->contactEmail, ENT_QUOTES, 'UTF-8');
$contactLink  = '<a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a>';

?>

<div class="ticketstation ticketstation--paymentresult ticketstation--paymentresult-<?php echo $result; ?>">

    <?php if ($result === 'unknown') { ?>

        <div class="page-header">
            <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER'); ?></h1>
        </div>

        <section class="ts-card ts-result ts-result--unknown">
            <p><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_STATUS_IN_MAIL'); ?></p>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_NOTHING_RECEIVED', $contactLink); ?></p>
        </section>

    <?php } elseif ($result === 'failed') { ?>

        <div class="page-header">
            <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_FAILED'); ?></h1>
        </div>

        <?php if ($this->canRetry) {
            $itemid = TicketstationFunctions::getSiteItemid(); ?>

            <section class="ts-card ts-result ts-result--failed">
                <?php if ($this->notFinished) { ?>
                    <p class="ts-lead"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_NOT_FINISHED'); ?></p>
                    <p><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_NOT_FINISHED_DONT_PAY_TWICE'); ?></p>
                <?php } else { ?>
                    <p class="ts-lead"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_NOT_COMPLETED'); ?></p>
                <?php } ?>
                <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_ORDER_NUMBER', '<strong class="ts-order-code">' . $this->ordercode . '</strong>'); ?></p>

                <form class="ts-actions" method="post" action="<?php echo Route::_('index.php?option=com_ticketstation' . ($itemid ? '&Itemid=' . $itemid : '')); ?>">
                    <button type="submit" class="ts-btn ts-btn--primary"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PAY_AGAIN'); ?></button>

                    <input type="hidden" name="option" value="com_ticketstation" />
                    <input type="hidden" name="controller" value="payment" />
                    <input type="hidden" name="task" value="makepayment" />
                    <input type="hidden" name="ordercode" value="<?php echo (int) $this->ordercode; ?>" />
                    <?php echo HTMLHelper::_('form.token'); ?>
                </form>

                <p><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY_QUESTION'); ?></strong><br /><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY', $contactLink); ?></p>
                <p><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_NOT_ORDERING'); ?></p>
            </section>

        <?php } else { ?>

        <p class="ts-lead"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_OOPS'); ?></p>

        <section class="ts-card ts-result ts-result--failed">
            <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER_RECEIVED'); ?></h2>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_ORDER_NUMBER', '<strong class="ts-order-code">' . $this->ordercode . '</strong>'); ?></p>
            <p class="ts-text-danger"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PROCESSING_FAILED'); ?></p>
            <p><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY_QUESTION'); ?></strong><br /><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY', $contactLink); ?></p>
            <p><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_FAILED_QUESTION'); ?></strong><br /><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_UNPAID_REMOVED'); ?></p>
        </section>

        <?php } ?>

    <?php } else { ?>

        <div class="page-header">
            <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_SUCCESS'); ?></h1>
        </div>

        <p class="ts-lead"><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_THANK_YOU', htmlspecialchars(PersonName::first($this->data[0]->name), ENT_QUOTES, 'UTF-8')); ?></p>

        <section class="ts-card ts-result ts-result--success">
            <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER_PROCESSED'); ?></h2>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_ORDER_NUMBER', '<strong class="ts-order-code">' . $this->ordercode . '</strong>'); ?></p>
            <?php if ($this->ticketsSent) { ?>
                <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_MAIL_SOON', '<strong>' . htmlspecialchars($this->data[0]->emailaddress, ENT_QUOTES, 'UTF-8') . '</strong>'); ?></p>
                <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_CHECK_SPAM', $contactLink); ?></p>
            <?php } else { ?>
                <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_MAIL_LATER', '<strong>' . htmlspecialchars($this->data[0]->emailaddress, ENT_QUOTES, 'UTF-8') . '</strong>'); ?></p>
                <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_QUESTIONS', $contactLink); ?></p>
            <?php } ?>

            <?php if ($this->hasCalendar) {
                ## Unrouted, like the other task links: the SEF router would drop the order parameter
                $calendar_link = Uri::root(true) . '/index.php?option=com_ticketstation&controller=paymentresult&task=calendar&order=' . (int) $this->ordercode;
                ?>
                <div class="ts-actions">
                    <a class="ts-btn ts-btn--secondary" href="<?php echo $calendar_link; ?>" download>
                        <svg class="ts-icon" viewBox="0 0 16 16" aria-hidden="true"><path d="M5 1a1 1 0 0 1 1 1v1h4V2a1 1 0 1 1 2 0v1h1a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h1V2a1 1 0 0 1 1-1zM3 7v6h10V7H3z"/></svg>
                        <?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ADD_TO_CALENDAR'); ?>
                    </a>
                </div>
            <?php } ?>
        </section>

        <?php
        $ticketcount = count($this->data);
        $downloaded  = (int) $this->data[0]->downloaded === 1;

        ## Once downloaded, the tickets can't be downloaded here again (the download itself
        ## checks this too); point to the Lost tickets page, which emails them again.
        $itemid        = TicketstationFunctions::getSiteItemid();
        $lost_link     = Route::_('index.php?option=com_ticketstation&view=losttickets' . ($itemid ? '&Itemid=' . $itemid : ''));
        $lost_notice   = Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOADED', '<a href="' . $lost_link . '">' . Text::_('COM_TICKETSTATION_PAYMENTRESULT_EMAIL_AGAIN') . '</a>');
        ?>

        <?php if ($this->ticketsSent) { ?>
        <section class="ts-card ts-download" id="ts-download">
            <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD'); ?></h2>

            <?php if (!$downloaded) {
                $download_link = Uri::root(true) . "/index.php?option=com_ticketstation&controller=paymentresult&task=downloadTicketAfterPurchase&order=" . $this->ordercode . "&" . \Joomla\CMS\Session\Session::getFormToken() . "=1";
                ?>
                <div class="ts-download__offer">
                    <p><?php echo Text::plural('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD_DESC', $ticketcount); ?></p>

                    <div class="ts-actions">
                        <a id="download_button" class="ts-btn ts-btn--primary" href="<?php echo $download_link; ?>"><?php echo Text::plural('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD_BUTTON', $ticketcount); ?></a>
                    </div>
                </div>
            <?php } ?>

            <p class="ts-download__done"<?php echo $downloaded ? '' : ' hidden'; ?>><?php echo $lost_notice; ?></p>
        </section>
        <?php } ?>

        <?php if ($this->walletButtons !== '') { ?>
            <section class="ts-card ts-wallet" id="ts-wallet">
                <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_WALLET_PAGE_TITLE'); ?></h2>
                <?php echo $this->walletButtons; ?>
            </section>
        <?php } ?>

        <?php if ($this->ticketsSent && !$downloaded) { ?>
            <script>
                // The tickets can be downloaded once: after the click, say where to get them again.
                document.getElementById('download_button').addEventListener('click', function () {
                    setTimeout(function () {
                        document.querySelector('#ts-download .ts-download__offer').hidden = true;
                        document.querySelector('#ts-download .ts-download__done').hidden = false;
                    }, 500);
                });
            </script>
        <?php } ?>

    <?php } ?>

</div>
