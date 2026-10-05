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
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_events'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('coupon_tickets'); ?>
            </div>
            <div>
                <?= $this->form->renderField('coupon_id'); ?>
            </div>
        </div>
    </div>


    <script>
        // "Valid for": the placeholder "All tickets (the whole order)" only applies while no
        // ticket is selected; the fancy select would otherwise keep showing it.
        customElements.whenDefined('joomla-field-fancy-select').then(function () {
            var select = document.getElementById('jform_coupon_tickets');
            var field  = select ? select.closest('joomla-field-fancy-select') : null;

            if (!field) {
                return;
            }

            var hint = field.getAttribute('placeholder') || '';

            function update() {
                var input = field.querySelector('input.choices__input');

                if (input) {
                    input.placeholder = select.selectedOptions.length ? '' : hint;
                }
            }

            select.addEventListener('change', update);
            select.addEventListener('addItem', update);
            select.addEventListener('removeItem', update);

            // The fancy select builds its input after the element is defined.
            setTimeout(update, 0);
            window.addEventListener('load', update);
        });
    </script>

    <input type="hidden" name="option" value="com_ticketstation" />
    <input type="hidden" name="controller" value="coupon" />
    <input type="hidden" name="task" value="" />
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>


