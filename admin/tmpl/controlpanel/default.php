<?php

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_CPANEL_BROWSER_TITLE') . ' - ' . $app->get('sitename'));
$document->addStyleSheet( '/administrator/components/com_ticketstation/assets/css/ticketstation.css' );

## Load dark theme only for J5!
if (version_compare(JVERSION, '4.999.999', 'gt')) {
    $document->addStyleSheet('/administrator/components/com_ticketstation/assets/css/j5dark.css');
}

?>

<?php // Main area ?>
<div class="container">
    <div class="row">
        <?php // LEFT COLUMN (66% desktop width) ?>
        <div class="col col-12 col-lg-8">
            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_HEADER_TRANSACTIONMANAGEMENT') ?>
                </h3>

                <div class="card-body">
                    <div class="ticketstation-cpanel-container d-flex flex-row flex-wrap align-items-stretch">
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=boxoffice">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-money-bill-alt"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_BOXOFFICE') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=clients">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-users"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_CUSTOMERS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=transactions">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-credit-card"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_TRANSACTIONS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=invoices">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-file-invoice"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_INVOICES') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=coupons">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-percent"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_COUPONS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=reservation">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-calendar-plus"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_RESERVATION_CPANEL_BUTTON') ?></span>
                        </a>

                        <?php if ($this->config->show_waitinglist == 1) { ?>
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=waitinglist">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-hourglass-half"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_WAITINGLIST') ?></span>
                        </a>
                        <?php } ?>

                    </div>
                </div>

            </div>
            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_HEADER_TICKETMANAGEMENT') ?>
                </h3>

                <div class="card-body">
                    <div class="ticketstation-cpanel-container d-flex flex-row flex-wrap align-items-stretch">
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=tickets">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-ticket-alt"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_TICKETS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=events">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-calendar-alt"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_EVENTS') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=venues">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fas fa-hotel"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_VENUES') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=seatplans">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-chair"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_SEATPLANS') ?></span>
                        </a>

                    </div>
                </div>

            </div>

            <div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_CPANEL_HEADER_CONFIGURATION') ?>
                </h3>

                <div class="card-body">
                    <div class="ticketstation-cpanel-container d-flex flex-row flex-wrap align-items-stretch">
                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=scanners">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-qrcode"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_TICKETSCANNING') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=templates">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-envelope"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_VIEW_TEMPLATES_TITLE') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=configuration">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <span class="fa fa-cog"></span>
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_CONFIGURATION') ?></span>
                        </a>

                        <a class="ticketstation-cpanel-button text-center align-self-stretch btn btn-outline-primary border-0" style="width: 10em;" href="index.php?option=com_ticketstation&view=mollie">
                            <div class="bg-primary text-white d-block text-center p-3 h2">
                                <img width="26" src="components/com_ticketstation/assets/images/MollieMonogram23-CircleWhite.png">
                            </div>
                            <span><?= Text::_('COM_TICKETSTATION_MOLLIE_CONFIG') ?></span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
        <?php // RIGHT COLUMN (33% desktop width) ?>
        <div class="col-12 col-lg-4">
            <div class="card mb-2">
				<?php if (($this->mollie->test_mode == '1') || ($this->mollie->bypass_mode == '1') || ($this->mollie->api_key == '') || (substr($this->mollie->api_key, 0, 4) == 'test') || !(substr($this->mollie->api_key, 0, 4) == 'live')) { ?>
					<div class="card-body alert alert-danger mollie-status mollie-status-warning">
						<div style="text-align:center;">
							<img style="margin-bottom: 20px;" width="100" src="components/com_ticketstation/assets/images/MollieLogo23-Black.png"></img>
                            <span style="color:black;font-size: 30px;margin-left: 5px;">status:</span>
						</div>
						<div>
							<table class="table itemList">
								<tr>
									<td>Bypass mode</td>
									<?php if ($this->mollie->bypass_mode == '1') { ?>
										<td><span class="label badge bg-danger">ON</span></td>
									<?php } else { ?>
										<td><span class="label badge bg-success">OFF</span></td>
									<?php } ?>
								</tr>
								<tr>
									<td>Test mode</td>
									<?php if ($this->mollie->test_mode == '1') { ?>
										<td><span class="label badge bg-danger">ON</span></td>
									<?php } else { ?>
										<td><span class="label badge bg-success">OFF</span></td>
									<?php } ?>
								</tr>
								<tr>
									<td>Live API Key</td>
									<?php if ($this->mollie->api_key == '') { ?>
										<td><span class="label badge bg-danger">MISSING</span></td>
									<?php } elseif (substr($this->mollie->api_key, 0, 4) === 'test') { ?>
										<td><span class="label badge bg-danger">TEST</span></td>
									<?php } elseif (substr($this->mollie->api_key, 0, 4) === 'live') { ?>
										<td><span class="label badge bg-success">OK</span></td>
									<?php } else { ?>
                                        <td><span class="label badge bg-danger">WRONG</span></td>
                                    <?php } ?>
								</tr>							
							</table>
						</div>
						<div style="text-align:center;">
							<span class="fa fa-exclamation-circle fa-3x"></span>
						</div>
					</div>
				<?php } else { ?>
					<div class="card-body alert alert-success mollie-status mollie-status-success">
						<div style="text-align:center;">
							<img style="margin-bottom: 20px;" width="100" src="components/com_ticketstation/assets/images/MollieLogo23-Black.png"></img>
                            <span style="color:black;font-size: 30px;margin-left: 5px;">status:</span>
						</div>
						<div>
							<table class="table itemList">
								<tr>
									<td>Bypass mode</td>
									<td><span class="label badge bg-success">OFF</span></td>
								</tr>
								<tr>
									<td>Test mode</td>
									<td><span class="label badge bg-success">OFF</span></td>
								</tr>
								<tr>
									<td>Live API Key</td>
									<td><span class="label badge bg-success">OK</span></td>
								</tr>							
							</table>
						</div>
						<div style="text-align:center;">
							<span class="fa fa-check-circle fa-3x"></span>
						</div>
					</div>			
				<?php } ?>			
			</div>					
			<div class="card mb-2">
                <h3 class="card-header">
                    <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_COMPONENT_INFO') ?>
                </h3>
                <div class="card-body">
                    <table class="table itemList">
                        <tr>
                            <td>
                                <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_VERSION') ?>
                            </td>
                            <td>
                                <?php echo $this->data['version']; ?>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <?= Text::_('COM_TICKETSTATION_VIEW_CPANEL_RELEASEDATE') ?>
                            </td>
                            <td>
                                <?php echo $this->data['creationDate']; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <div class="card mb-2">
                <div class="card-body">
                    <div style="margin-bottom: 10px; text-align: center;"><?= Text::_('COM_TICKETSTATION_ENJOYING') ?></div>
                    <div style="text-align: center;">
                        <a
                                href="https://www.paypal.com/donate/?business=TSVSU67MCBM8W&no_recurring=1&item_name=Thank+you+for+appreciating+Ticketstation%21&currency_code=EUR"
                                class="btn btn-outline-success mb-2" target="blank">
                            <span class="fa fa-donate"></span>
                            <?= Text::_('COM_TICKETSTATION_DONATE') ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col">
            <div class="ticketstation-cpanel-footer small mt-3 p-3 bg-light border-top border-4 d-flex flex-column">
                <p class="text-muted">
                    <span style="color:#c53c88;"><b><em>Ticketstation for Joomla!™</em></b></span> is based on the original code of RD Ticketmaster by Robert Dam,
                    which has been massively reworked and enhanced to make it Joomla! 6.x compatible and to suit the specific needs of <a href="https://www.huibuuke.nl">Stichting De Huibuuke</a>, Overloon, The Netherlands.
                    <br/>
                    <strong>Use it to your advantage, but please do not expect close support. This extension was developed with limited programming skills, merely as a hobby project.</strong>
                </p>

                <p class="text-muted">
                    Copyright 2022-<?= date('Y') ?> <a href="mailto:<?php echo $this->data['authorEmail']; ?>"><?php echo $this->data['author']; ?></a> Overloon. All legal rights reserved.
                    <br/>
                    <span style="color:#c53c88;"><b><em>Ticketstation for Joomla!™</em></b></span> is Free Software and is distributed under the terms of the
                    <a href="http://www.gnu.org/licenses/gpl-3.0.html">GNU General Public License</a>, version 3 or any later version.
                </p>
            </div>
        </div>
    </div>
</div>
