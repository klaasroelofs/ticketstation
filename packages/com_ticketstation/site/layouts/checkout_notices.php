<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

use Joomla\CMS\Language\Text;

/**
 * What goes above the checkout form: the notice for details filled in from the account, and the
 * warning for a form that came back with errors.
 *
 * Rendered with LayoutHelper::render('checkout_notices', ['view' => $this], null, ['component' => 'com_ticketstation', 'client' => 0]).
 *
 * @var  array  $displayData  'view' => the checkout view, with prefilled, clearFields and errors
 */

$view = $displayData['view'];

?>
<?php if ($view->prefilled) { ?>
    <p class="ts-intro ts-prefilled">
        <?php echo Text::_('COM_TICKETSTATION_CHECKOUT_PREFILLED'); ?>
        <button type="button" class="ts-btn ts-btn--secondary ts-btn--sm" id="ts-clear-details"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_PREFILLED_OTHER'); ?></button>
    </p>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var button = document.getElementById('ts-clear-details');

            button.addEventListener('click', function () {
                var ids = <?php echo json_encode(array_values($view->clearFields)); ?>;

                ids.forEach(function (id) {
                    var field = document.getElementById(id);

                    if (!field) { return; }

                    if (field.tagName === 'SELECT') { field.selectedIndex = 0; } else { field.value = ''; }
                });

                var first = document.getElementById(ids[0]);

                if (first) { first.focus(); }
                button.hidden = true;
            });
        });
    </script>
<?php } ?>

<?php if ($view->errors) { ?>
    <div class="ts-alert ts-alert--danger" role="alert"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_CHECK_FIELDS'); ?></div>
<?php } ?>
