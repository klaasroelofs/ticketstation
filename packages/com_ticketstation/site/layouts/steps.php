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
 * Order progress shown above the ticket, cart, checkout and payment views.
 *
 * Rendered with LayoutHelper::render('steps', ['current' => n], null, ['component' => 'com_ticketstation', 'client' => 0]),
 * so a template can override it in templates/<template>/html/layouts/com_ticketstation/steps.php.
 *
 * @var  array  $displayData  'current' => number of the active step (1-4)
 */

$current = (int) ($displayData['current'] ?? 1);

$steps = [
    1 => Text::_('COM_TICKETSTATION_STEP_CHOOSE_TICKETS'),
    2 => Text::_('COM_TICKETSTATION_CART'),
    3 => Text::_('COM_TICKETSTATION_ORDER_DETAILS'),
    4 => Text::_('COM_TICKETSTATION_STEP_PAYMENT'),
];
?>
<nav class="ts-steps-nav" aria-label="<?php echo Text::_('COM_TICKETSTATION_ORDER_STEPS'); ?>">
    <ol class="ts-steps">
        <?php foreach ($steps as $number => $label) :
            $state = $number < $current ? ' is-complete' : ($number === $current ? ' is-current' : ''); ?>
            <li class="ts-steps__step<?php echo $state; ?>"<?php echo $number === $current ? ' aria-current="step"' : ''; ?>>
                <span class="ts-steps__marker" aria-hidden="true"><?php echo $number; ?></span>
                <span class="ts-steps__label"><?php echo $label; ?></span>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
