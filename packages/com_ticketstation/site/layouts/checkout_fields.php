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
 * The customer detail fields of the checkout (not the <form> tag, and not the notices above it:
 * see checkout_notices).
 *
 * Rendered with LayoutHelper::render('checkout_fields', ['view' => $this], null, ['component' => 'com_ticketstation', 'client' => 0]),
 * so a template can override it in templates/<template>/html/layouts/com_ticketstation/checkout_fields.php.
 *
 * @var  array  $displayData  'view' => the checkout view, with config, values, errors, lists and captcha
 */

$view = $displayData['view'];

## Fields in form order: name => [shown, label, input type, autocomplete, required]
## (the same rules as CheckoutController::validateForm(); only the third address line is optional)
$fields = [
    'name'         => [true, 'COM_TICKETSTATION_YOUR_NAME', 'text', 'name', true],
    'address'      => [$view->config->show_address != 0, 'COM_TICKETSTATION_YOUR_ADDRESS', 'text', 'address-line1', true],
    'address2'     => [$view->config->show_secondaddress != 0, 'COM_TICKETSTATION_ADDRESS_2', 'text', 'address-line2', true],
    'address3'     => [$view->config->show_thirdaddress != 0, 'COM_TICKETSTATION_ADDRESS_3', 'text', 'address-line3', false],
    'zipcode'      => [$view->config->show_zipcode != 0, 'COM_TICKETSTATION_YOUR_ZIPCODE', 'text', 'postal-code', true],
    'city'         => [$view->config->show_city != 0, 'COM_TICKETSTATION_YOUR_CITY', 'text', 'address-level2', true],
    'country_id'   => [$view->config->show_country != 0, 'COM_TICKETSTATION_YOUR_COUNTRY', 'select', '', true],
    'phonenumber'  => [$view->config->show_phone != 0, 'COM_TICKETSTATION_YOUR_PHONE', 'tel', 'tel', true],
    'emailaddress' => [true, 'COM_TICKETSTATION_YOUR_EMAIL', 'email', 'email', true],
];

$requiredMark = '<span class="ts-required" aria-hidden="true">*</span>';

?>
<div class="ts-form__fields">

    <?php if($view->config->show_salutation != 0 ): ?>
        <div class="ts-field ts-field--gender">
            <label class="ts-label" for="gender"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_GENDER' ); ?><?php echo $requiredMark; ?></label>
            <?php echo $view->lists['gender']; ?>
        </div>
    <?php endif; ?>

    <?php foreach ($fields as $name => [$shown, $label, $type, $autocomplete, $isRequired]) {

        if (!$shown) {
            continue;
        }

        $error = $view->errors[$name] ?? null; ?>

        <div class="ts-field ts-field--<?php echo $name; ?><?php echo $error ? ' ts-field--invalid' : ''; ?>">
            <label class="ts-label" for="<?php echo $name; ?>"><?php echo Text::_($label); ?><?php echo $isRequired ? $requiredMark : ''; ?></label>

            <?php if ($type === 'select') { ?>
                <?php echo $view->lists['country']; ?>
            <?php } else { ?>
                <input class="ts-input" type="<?php echo $type; ?>" name="<?php echo $name; ?>" id="<?php echo $name; ?>"
                       value="<?php echo htmlspecialchars((string) ($view->values[$name] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                       <?php echo $autocomplete ? 'autocomplete="' . $autocomplete . '"' : ''; ?>
                       <?php echo $isRequired ? 'required' : ''; ?>
                       <?php echo $error ? 'aria-invalid="true" aria-describedby="' . $name . '-error"' : ''; ?> />
            <?php } ?>

            <?php if ($name === 'emailaddress') { ?>
                <p class="ts-field__hint" id="emailaddress-suggestion" aria-live="polite"
                   data-text="<?php echo htmlspecialchars(Text::_('COM_TICKETSTATION_EMAIL_DID_YOU_MEAN'), ENT_QUOTES, 'UTF-8'); ?>"
                   data-accept="<?php echo htmlspecialchars(Text::_('COM_TICKETSTATION_EMAIL_USE_SUGGESTION'), ENT_QUOTES, 'UTF-8'); ?>"></p>
            <?php } ?>

            <?php if ($error) { ?>
                <p class="ts-field__error" id="<?php echo $name; ?>-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>
        </div>

    <?php } ?>

    <?php if ($view->captcha) {
        $error = $view->errors['captcha'] ?? null; ?>
        <div class="ts-field ts-field--captcha<?php echo $error ? ' ts-field--invalid' : ''; ?>">
            <?php echo $view->captcha; ?>

            <?php if ($error) { ?>
                <p class="ts-field__error" id="captcha-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
            <?php } ?>
        </div>
    <?php } ?>

</div>
