<?php

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\getAmount;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );

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

        <p class="ts-lead"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_OOPS'); ?></p>

        <section class="ts-card ts-result ts-result--failed">
            <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER_RECEIVED'); ?></h2>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_ORDER_NUMBER', '<strong class="ts-order-code">' . $this->ordercode . '</strong>'); ?></p>
            <p class="ts-text-danger"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PROCESSING_FAILED'); ?></p>
            <p><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY_QUESTION'); ?></strong><br /><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY', $contactLink); ?></p>
            <p><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_FAILED_QUESTION'); ?></strong><br /><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_UNPAID_REMOVED'); ?></p>
        </section>

    <?php } else { ?>

        <div class="page-header">
            <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_SUCCESS'); ?></h1>
        </div>

        <p class="ts-lead"><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_THANK_YOU', htmlspecialchars($this->data[0]->firstname, ENT_QUOTES, 'UTF-8')); ?></p>

        <section class="ts-card ts-result ts-result--success">
            <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER_PROCESSED'); ?></h2>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_ORDER_NUMBER', '<strong class="ts-order-code">' . $this->ordercode . '</strong>'); ?></p>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_MAIL_SOON', '<strong>' . htmlspecialchars($this->data[0]->emailaddress, ENT_QUOTES, 'UTF-8') . '</strong>'); ?></p>
            <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_CHECK_SPAM', $contactLink); ?></p>
        </section>

        <?php if($this->data[0]->downloadbuttonshown != 1) {

            $download_link = Uri::root(true) . "/index.php?option=com_ticketstation&controller=paymentresult&task=downloadTicketAfterPurchase&order=" . $this->ordercode . "&" . \Joomla\CMS\Session\Session::getFormToken() . "=1";

            $ticketcount = count($this->data);
        ?>

            <section class="ts-card ts-download" id="ts-download">
                <h2 class="ts-card__title"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD'); ?></h2>
                <p><?php echo Text::plural('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD_DESC', $ticketcount); ?></p>

                <div class="ts-actions">
                    <a id="download_button" class="ts-btn ts-btn--primary" href="<?php echo $download_link; ?>"><?php echo Text::plural('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD_BUTTON', $ticketcount); ?></a>

                    <?php if ($this->mollieconfig->bypass_mode == '1') {

                        $itemid = TicketstationFunctions::getSiteItemid();
                        $link_to_start = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));
                        ?>

                        <a class="ts-btn ts-btn--secondary ts-btn--next" href="<?php echo $link_to_start; ?>"><?php echo Text::_('COM_TICKETSTATION_NEW_ORDER'); ?></a>

                    <?php } ?>
                </div>
            </section>

            <script>
                // The tickets can be downloaded once: hide the section after the click.
                document.getElementById('download_button').addEventListener('click', function () {
                    setTimeout(function () {
                        document.getElementById('ts-download').hidden = true;
                    }, 500);
                });
            </script>

            <?php MarkDownloadbuttonshown($this->ordercode); ?>

        <?php } ?>

    <?php } ?>

</div>

<?php function MarkDownloadbuttonshown($ordercode)
{

    $db = Factory::getContainer()->get('DatabaseDriver');

    $query = $db->getQuery(true);

    $fields = [
        $db->quoteName('downloadbuttonshown') . ' = 1',
    ];

    $conditions = [$db->quoteName('ordercode') . ' = ' . $ordercode];

    $query->update($db->quoteName('#__ticketstation_orders'))->set($fields)->where($conditions);

    $db->setQuery($query);

    $db->execute();

}
?>
