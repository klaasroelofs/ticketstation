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
    $document->setTitle( 'Bestelling - ' . $app->get('sitename') );
} elseif ($this->unpaid->total > 0) {
    $document->setTitle( 'Betaling mislukt! - ' . $app->get('sitename') );
} else {
    $document->setTitle( 'Betaling geslaagd! - ' . $app->get('sitename') );
}

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
            <h1>Tickets</h1>
        </div>

        <?php if (!$this->authorized) { ?>

            <h2 class="ticketmaster-header"><strong>Bestelling</strong></h2>

            <div class="ticketmaster_event_info">
                <div class="row-fluid">
                    <p>De status van je bestelling en je tickets vind je terug in de bevestigingsmail die je na aankoop hebt ontvangen.</p>
                    <p>Niets ontvangen? Neem dan contact met ons op via <a href="mailto:tickets@huibuuke.nl">tickets@huibuuke.nl</a>.</p>
                </div>
            </div>

        <?php } elseif ($this->unpaid->total > 0) { ?>

            <h2 class="ticketmaster-header"><strong>Betaling mislukt</strong></h2>

            <div>
                <h3><strong>Oeps! Er ging iets fout...</strong></h3>
            </div>
            <div class="ticketmaster_event_info">
                <div class="row-fluid">
                    <h4><strong>Je bestelling is ontvangen.</strong></h4>
                    <p>Je bestelnummer is&nbsp;<strong><?php echo $this->ordercode; ?></strong>.</p>
                    <p><strong><span style="color: #ff0000;">De verwerking is echter mislukt!</span></strong></p>
                    <p><strong>Is de betaling volgens jou wel gelukt?<br /></strong>Controleer of er daadwerkelijk een afschrijving van je bankrekening heeft plaatsgevonden. Neem daarna even contact met ons op via <a href="mailto:tickets@huibuuke.nl">tickets@huibuuke.nl</a>. We controleren het dan voor je en sturen je de door jou bestelde tickets toe.</p>
                    <p><strong>Is de betaling mislukt?</strong><br />Niet betaalde bestellingen worden na ongeveer een uur automatisch verwijderd, waarna de tickets weer vrijgegeven worden.</p>
                </div>
            </div>

        <?php } else { ?>

            <h2 class="ticketmaster-header"><strong>Betaling geslaagd</strong></h2>

            <div>
                <h3><strong>Hartelijk dank, <?php echo $this->data[0]->firstname; ?>!</strong></h3>
            </div>
            <div class="ticketmaster_event_info">
                <div class="row-fluid">
                    <h4><strong>Je bestelling is ontvangen en verwerkt</strong></h4>
                    <p>Je bestelnummer is <span style="color: #008c39;"><strong><?php echo $this->ordercode; ?></strong></span>.</p>
                    <p>Binnen enkele ogenblikken ontvang je op <strong><?php echo $this->data[0]->emailaddress; ?></strong> een e-mail met daarin de door jou bestelde tickets.</p>
                    <p>Controleer je spamfolder wanneer je niets hebt ontvangen.<br/>Nog steeds niks? Neem dan contact met ons op via <a href="mailto:tickets@huibuuke.nl">tickets@huibuuke.nl</a>.</p>
                </div>
            </div>

            <?php if($this->data[0]->downloadbuttonshown != 1) {

                $download_link = "location.href='/index.php?option=com_ticketstation&controller=paymentresult&task=downloadTicketAfterPurchase&order=" . $this->ordercode . "&" . \Joomla\CMS\Session\Session::getFormToken() . "=1'";

                if (count($this->data) > 1) {
                    $buttontext = 'tickets';
                } else {
                    $buttontext = 'ticket';
                }
            ?>


                <div class="row-fluid download_section">
                    <div class="span12">
                        <hr />
                        <h4><strong>Download</strong></h4>
                        <p>Je kunt je <?php echo $buttontext; ?> ook eenmalig downloaden via onderstaande knop:</p>
                        <a id="download_button" class="btn btn-primary pull-left" type="button" onclick="<?php echo $download_link; ?>">Download <?php echo $buttontext; ?></a>

                        <?php if ($this->mollieconfig->bypass_mode == '1') {

                            $itemid = TicketstationFunctions::getSiteItemid();
                            $link_to_start = Route::_('index.php?option=com_ticketstation&view=upcoming' . ($itemid ? '&Itemid=' . $itemid : ''));
                            ?>

                            <a class="btn btn-primary pull-right" type="button" onClick="location.href='<?php echo $link_to_start; ?>'">Nieuwe bestelling</a>

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


