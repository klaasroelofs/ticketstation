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
use Joomla\CMS\Uri\Uri;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Date;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');
$app = Factory::getApplication();
$document = $app->getDocument();

$add_edit = empty($this->item->eventid) ? Text::_('COM_TICKETSTATION_ADD') : Text::_('COM_TICKETSTATION_EDIT');
$event_name = empty($this->item->eventid) ? Text::_('COM_TICKETSTATION_VIEW_EVENT_TITLE') : $this->item->eventname . ' (' . $this->item->eventcode . ')';
$document->setTitle($add_edit . ' ' . $event_name);

if(isset($this->item->eventid))
{

    ## Background Image (Upcoming Events frontend)
    $background_event   = JPATH_ADMINISTRATOR . '/components/com_ticketstation/assets/images/ticketbackgrounds/event' . $this->item->eventid . '.jpg';
    $background_img_event = false;

    if (file_exists($background_event)) {
        $background_img = Uri::root() . 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/event'.$this->item->eventid.'.jpg';
        $background_img_event = true;
    }

}

?>

<form action="<?php echo Route::_('index.php?option=com_ticketstation&view=event&layout=edit'); ?>" method="post" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <div class="card">
        <div class="card-body">
            <div class="row mb-3">
                <?= $this->form->renderField('eventname'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('eventcode'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('eventdate'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('automatic_change_state'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('startdate'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('closingdate'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('presale_display'); ?>
            </div>
            <div class="row mb-3">
                <?php if ((isset($this->item->eventid)?$this->item->eventid:0 > 0) && ($background_img_event)) { ?>

                    <?php $remove_link_bg = 'index.php?option=com_ticketstation&controller=event&task=removeBackground&eventid='.$this->item->eventid.'&'.\Joomla\CMS\Session\Session::getFormToken().'=1'; ?>

                    <div class="control-group">
                        <div class="control-label">
                            <label><?= Text::_( 'COM_TICKETSTATION_TICKET_CURRENT_BACKGROUND_UPCOMING' ); ?></label>
                        </div>
                        <div class="controls">
                            <a href="<?= $background_img; ?>" target="blank" class="btn btn-secondary">
                                <i class="icon-search"></i>  <?= Text::_( 'COM_TICKETSTATION_VIEW_BACKGROUND_UPCOMING' ); ?></a>
                            <a href="<?= $remove_link_bg; ?>" class="btn btn-secondary">
                                <i class="icon-trash"></i>  <?= Text::_( 'COM_TICKETSTATION_REMOVE_BACKGROUND_UPCOMING' ); ?></a>
                        </div>
                    </div>

                <?php } ?>
            </div>
            <div>
                <?= $this->form->renderField('backgroundupcomingfile'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('waitinglist'); ?>
            </div>
            <div class="row mb-3">
                <?= $this->form->renderField('eventdescription'); ?>
            </div>
            <div>
                <?= $this->form->renderField('eventid'); ?>
            </div>
        </div>
    </div>

    <input type="hidden" name="option" value="com_ticketstation"/>
    <input type="hidden" name="controller" value="event"/>
    <input type="hidden" name="task" value=""/>
    <input type="hidden" name="jform[shift_tickets]" id="ts-shift-tickets" value="0"/>
    <?= HTMLHelper::_( 'form.token' ); ?>

</form>

<?php if (!empty($this->item->eventid) && $this->ticketCount > 0) {
    // Changing the date of an event with tickets: ask whether the tickets move along by the same number of days
    $document->getWebAssetManager()->useScript('joomla.dialog');
    Text::script('WARNING');
    Text::script('JYES');
    Text::script('JNO');
    Text::script('COM_TICKETSTATION_EVENT_SHIFT_CONFIRM');
    Text::script('COM_TICKETSTATION_EVENT_SHIFT_DAYS_LATER_1');
    Text::script('COM_TICKETSTATION_EVENT_SHIFT_DAYS_LATER_MORE');
    Text::script('COM_TICKETSTATION_EVENT_SHIFT_DAYS_EARLIER_1');
    Text::script('COM_TICKETSTATION_EVENT_SHIFT_DAYS_EARLIER_MORE');
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var input = document.getElementById('jform_eventdate');
            var flag = document.getElementById('ts-shift-tickets');
            var original = <?= json_encode(substr((string) $this->item->eventdate, 0, 10)) ?>;
            var oldText = <?= json_encode(Date::display($this->item->eventdate, 'd-m-Y'),JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
            var count = <?= (int) $this->ticketCount ?>;

            if (!input || !flag || !Joomla.submitbutton) {
                return;
            }

            var submitbutton = Joomla.submitbutton;

            // Y-m-d (as stored) or d-m-Y (as the form field's filter format gives it), with or without a time
            function dayNumber(value) {
                var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(value || '');

                if (m) {
                    return Date.UTC(+m[1], +m[2] - 1, +m[3]) / 86400000;
                }

                m = /^(\d{2})-(\d{2})-(\d{4})/.exec(value || '');

                return m ? Date.UTC(+m[3], +m[2] - 1, +m[1]) / 86400000 : null;
            }

            // The date picker keeps the date in the Gregorian calendar in data-alt-value, whatever calendar it shows
            function daysChanged() {
                var from = dayNumber(original);
                var to = dayNumber(input.getAttribute('data-alt-value') || input.value);

                return from === null || to === null ? 0 : Math.round(to - from);
            }

            function confirmDialog(message) {
                return import('joomla.dialog')
                    .then(function (m) { return m.default.confirm(message, Joomla.Text._('WARNING', 'Warning')); })
                    .catch(function () { return window.confirm(message); });
            }

            Joomla.submitbutton = function (task) {
                var args = arguments;
                var self = this;
                var days = /(^|\.)(apply|save)$/.test(task) ? daysChanged() : 0;

                if (days === 0) {
                    flag.value = '0';

                    return submitbutton.apply(self, args);
                }

                var words = Joomla.Text._('COM_TICKETSTATION_EVENT_SHIFT_DAYS_' + (days > 0 ? 'LATER' : 'EARLIER') + (Math.abs(days) === 1 ? '_1' : '_MORE'));
                var message = Joomla.Text._('COM_TICKETSTATION_EVENT_SHIFT_CONFIRM')
                    .replace('{old}', oldText)
                    .replace('{new}', input.value)
                    .replace('{days}', words.replace('%d', Math.abs(days)))
                    .replace('{count}', count);

                confirmDialog(message).then(function (shift) {
                    flag.value = shift ? '1' : '0';
                    submitbutton.apply(self, args);
                });
            };
        });
    </script>
<?php } ?>
