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
 * The payment methods the customer chooses from, when the payment provider offers more than one,
 * so the provider's own pages don't ask again.
 *
 * Rendered with LayoutHelper::render('payment_methods', ['methods' => [...]], null, ['component' => 'com_ticketstation', 'client' => 0]).
 *
 * @var  array  $displayData  'methods'  => PaymentMethodOption[]
 *                            'selected' => the id of the method to preselect (optional)
 */

$escape = fn ($text) => htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');

## The method chosen before the form came back with errors; the first one when there was none
$ids      = array_map(fn ($method) => $method->id, $displayData['methods']);
$selected = in_array($displayData['selected'] ?? '', $ids, true) ? $displayData['selected'] : ($ids[0] ?? '');

?>
<fieldset class="ts-methods">
    <legend class="ts-methods__title"><?php echo Text::_('COM_TICKETSTATION_CHOOSE_PAYMENT_METHOD'); ?></legend>
    <?php foreach ($displayData['methods'] as $i => $method) { ?>
        <label class="ts-method">
            <input class="ts-method__input" type="radio" name="method" value="<?php echo $escape($method->id); ?>"<?php echo $method->id === $selected ? ' checked' : ''; ?> required />
            <?php if ($method->iconUrl !== '') { ?>
                <img class="ts-method__icon" src="<?php echo $escape($method->iconUrl); ?>" alt="" width="32" height="24" loading="lazy" onerror="this.style.display='none'" />
            <?php } ?>
            <span class="ts-method__label"><?php echo $escape($method->label); ?></span>
        </label>
    <?php } ?>
</fieldset>
