<?php

use Joomla\CMS\Factory;
use \Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Editor\Editor;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 *
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();
$document->setTitle(Text::_('COM_TICKETSTATION_VIEW_EDIT_TEMPLATES_TITLE') . ' - ' . $app->get('sitename'));

$user = $this->getCurrentUser();
$editor = Editor::getInstance($user->getParam('editor', Factory::getConfig()->get('editor')));

?>

<form action = "<?php echo Route::_('index.php?option=com_ticketstation&view=templates'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <h3 class="card-header bg-primary text-white">
                        Mail Template
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
                               value="<?= isset($this->data->mailsubject)?$this->data->mailsubject:null; ?>"/>
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
                        Dynamic Fields
                    </h3>

                    <div class="row mb-3">
                        <div class="row mb-3">
                            <div style="margin-bottom:15px;">
                                You can use the following codes in your subject and body. Don't forget to include the brackets!
                            </div>
                            <h4>
                                Client Info
                            </h4>
                            <div>
                                {firstname}<br/>
                                {name}<br/>
                                {emailaddress}<br/>
                                {phonenumber}<br/>
                                {ipaddress}<br/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <h4>
                                Order Info
                            </h4>
                            <div>
                                {ordercode}<br/>
                                {orderdate}<br/>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <h4>
                                Company Info
                            </h4>
                            <div>
                                {company_name}<br/>
                                {company_website}<br/>
                            </div>
                        </div>
                        <?php if ($this->data->mailid == 3) { ?>
                            <div class="row mb-3">
                                <h4>
                                    Payment
                                </h4>
                                <div>
                                    {paymentlink}<br/>
                                </div>
                            </div>
                        <?php } ?>

                    </div>
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
