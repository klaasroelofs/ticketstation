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

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();

$add_edit = empty($this->item->coupon_id) ? Text::_( 'COM_TICKETSTATION_ADD' ) : Text::_( 'COM_TICKETSTATION_EDIT' );
$document->setTitle($add_edit .' '.Text::_('COM_TICKETSTATION_COUPON') . ' - ' . $app->get('sitename'));

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=coupon&layout=edit'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_name'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_code'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_limit'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_valid_to'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_type'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_discount'); ?>
            </div>
            <div>
                <?= $this->form->renderField('coupon_id'); ?>
            </div>
        </div>
    </div>


    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="coupon" />
    <input type="hidden" name="task" value="" />
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>


