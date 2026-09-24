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
HTMLHelper::_('jquery.framework');

if (!$this->authorized) {
    $document->setTitle( Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER') . ' - ' . $app->get('sitename') );
} elseif ($this->unpaid->total > 0) {
    $document->setTitle( Text::_('COM_TICKETSTATION_PAYMENTRESULT_FAILED_PAGE_TITLE') . ' - ' . $app->get('sitename') );
} else {
    $document->setTitle( Text::_('COM_TICKETSTATION_PAYMENTRESULT_SUCCESS_PAGE_TITLE') . ' - ' . $app->get('sitename') );
}

## Contact address shown to the customer when something needs checking (Configuration > Company)
$contactEmail = htmlspecialchars($this->contactEmail, ENT_QUOTES, 'UTF-8');
$contactLink  = '<a href="mailto:' . $contactEmail . '">' . $contactEmail . '</a>';

$document->addScriptDeclaration('
    jQuery(\'document\').ready( function() { 
        jQuery(\'#download_button\').click(function() {
                jQuery(\'.download_section\').delay(500).fadeOut();
        });
    });
');

?>

    <script language="javascript">

        jQuery(document).ready(function() {

            jQuery('head').append("<style>ul.checkout-bar li.previous:after {width:100%;} ul.checkout-bar li.complete:before {background: #BB2721;} ul.checkout-bar li.active {color: #BB2721;}</style>");

            jQuery('head').delay(1500).queue(function() {
                jQuery('head').append("<style>ul.checkout-bar li.complete:after { width:61%; }</style>");
                jQuery('head').dequeue();
            });

        });

    </script>

<div class="row ticketstation">
    <div class="col-12">

        <div class="page-header">
            <h1><?php echo Text::_('COM_TICKETSTATION_PAGE_HEADING_TICKETS'); ?></h1>
        </div>

        <?php if (!$this->authorized) { ?>

            <h2 class="ticketmaster-header"><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER'); ?></strong></h2>

            <div class="ticketmaster_event_info">
                <div class="row-fluid">
                    <p><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_STATUS_IN_MAIL'); ?></p>
                    <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_NOTHING_RECEIVED', $contactLink); ?></p>
                </div>
            </div>

        <?php } elseif ($this->unpaid->total > 0) { ?>

            <h2 class="ticketmaster-header"><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_FAILED'); ?></strong></h2>

            <div>
                <h3><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_OOPS'); ?></strong></h3>
            </div>
            <div class="ticketmaster_event_info">
                <div class="row-fluid">
                    <h4><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER_RECEIVED'); ?></strong></h4>
                    <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_ORDER_NUMBER', '<strong>' . $this->ordercode . '</strong>'); ?></p>
                    <p><strong><span style="color: #ff0000;"><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PROCESSING_FAILED'); ?></span></strong></p>
                    <p><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY_QUESTION'); ?><br /></strong><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_PAID_ANYWAY', $contactLink); ?></p>
                    <p><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_FAILED_QUESTION'); ?></strong><br /><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_UNPAID_REMOVED'); ?></p>
                </div>
            </div>

        <?php } else { ?>

            <h2 class="ticketmaster-header"><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_SUCCESS'); ?></strong></h2>

            <div>
                <h3><strong><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_THANK_YOU', $this->data[0]->firstname); ?></strong></h3>
            </div>
            <div class="ticketmaster_event_info">
                <div class="row-fluid">
                    <h4><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_ORDER_PROCESSED'); ?></strong></h4>
                    <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_ORDER_NUMBER', '<span style="color: #008c39;"><strong>' . $this->ordercode . '</strong></span>'); ?></p>
                    <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_MAIL_SOON', '<strong>' . $this->data[0]->emailaddress . '</strong>'); ?></p>
                    <p><?php echo Text::sprintf('COM_TICKETSTATION_PAYMENTRESULT_CHECK_SPAM', $contactLink); ?></p>
                </div>
            </div>

            <?php if($this->data[0]->downloadbuttonshown != 1) {

                $download_link = "location.href='/index.php?option=com_ticketstation&controller=paymentresult&task=downloadTicketAfterPurchase&order=" . $this->ordercode . "&" . \Joomla\CMS\Session\Session::getFormToken() . "=1'";

                $ticketcount = count($this->data);
            ?>


                <div class="row-fluid download_section">
                    <div class="span12">
                        <hr />
                        <h4><strong><?php echo Text::_('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD'); ?></strong></h4>
                        <p><?php echo Text::plural('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD_DESC', $ticketcount); ?></p>
                        <a id="download_button" class="btn btn-primary pull-left" type="button" onclick="<?php echo $download_link; ?>"><?php echo Text::plural('COM_TICKETSTATION_PAYMENTRESULT_DOWNLOAD_BUTTON', $ticketcount); ?></a>

                        <?php if ($this->mollieconfig->bypass_mode == '1') {

                            $itemid = TicketstationFunctions::getSiteItemid();
                            $link_to_start = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));
                            ?>

                            <a class="btn btn-primary pull-right" type="button" onClick="location.href='<?php echo $link_to_start; ?>'"><?php echo Text::_('COM_TICKETSTATION_NEW_ORDER'); ?></a>

                        <?php } ?>

                    </div>
                </div>

                <?php MarkDownloadbuttonshown($this->ordercode); ?>

            <?php } ?>

        <?php } ?>



    </div>
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


