<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

## Getting the global DB session
$session = Factory::getApplication()->getSession();
## Gettig the orderid if there is one.
$ordercode = $session->get('ordercode');

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_ORDER_DETAILS') . ' - ' . $app->get('sitename') );
$document->addStyleSheet( 'components/com_ticketstation/assets/css/component.css' );

## Redirection link in JRoute:
$itemid = TicketstationFunctions::getSiteItemid();
$gotocart = Route::_('index.php?option=com_ticketstation&view=cart' . ($itemid ? '&Itemid=' . $itemid : ''));

## Fields in form order: name => [shown, label, input type, autocomplete, required]
## (the same rules as CheckoutController::validateForm(); only the third address line is optional)
$fields = [
    'firstname'    => [true, 'COM_TICKETSTATION_YOUR_FIRSTNAME', 'text', 'given-name', true],
    'lastname'     => [true, 'COM_TICKETSTATION_YOUR_LASTNAME', 'text', 'family-name', true],
    'address'      => [$this->config->show_address != 0, 'COM_TICKETSTATION_YOUR_ADDRESS', 'text', 'address-line1', true],
    'address2'     => [$this->config->show_secondaddress != 0, 'COM_TICKETSTATION_ADDRESS_2', 'text', 'address-line2', true],
    'address3'     => [$this->config->show_thirdaddress != 0, 'COM_TICKETSTATION_ADDRESS_3', 'text', 'address-line3', false],
    'zipcode'      => [$this->config->show_zipcode != 0, 'COM_TICKETSTATION_YOUR_ZIPCODE', 'text', 'postal-code', true],
    'city'         => [$this->config->show_city != 0, 'COM_TICKETSTATION_YOUR_CITY', 'text', 'address-level2', true],
    'country_id'   => [$this->config->show_country != 0, 'COM_TICKETSTATION_YOUR_COUNTRY', 'select', '', true],
    'phonenumber'  => [$this->config->show_phone != 0, 'COM_TICKETSTATION_YOUR_PHONE', 'tel', 'tel', true],
    'emailaddress' => [true, 'COM_TICKETSTATION_YOUR_EMAIL', 'email', 'email', true],
    'email2'       => [true, 'COM_TICKETSTATION_RETYPE_EMAIL', 'email', 'email', true],
];

$requiredMark = '<span class="ts-required" aria-hidden="true">*</span>';

?>

<div class="ticketstation ticketstation--checkout">

    <?php echo LayoutHelper::render('steps', ['current' => 3], null, ['component' => 'com_ticketstation', 'client' => 0]); ?>

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_ORDER_DETAILS'); ?></h1>
    </div>

    <p class="ts-intro"><?php echo Text::_('COM_TICKETSTATION_CREATEACCOUNT_NOW2'); ?></p>

    <?php if ($this->errors) { ?>
        <div class="ts-alert ts-alert--danger" role="alert"><?php echo Text::_('COM_TICKETSTATION_CHECKOUT_CHECK_FIELDS'); ?></div>
    <?php } ?>

    <form id="general" class="ts-form" action="<?php echo Route::_('index.php?option=com_ticketstation&controller=checkout' . ($itemid ? '&Itemid=' . $itemid : '')); ?>" method="post" name="general">

        <div class="ts-form__fields">

            <?php if($this->config->show_salutation != 0 ): ?>
                <div class="ts-field ts-field--gender">
                    <label class="ts-label" for="gender"><?php echo Text::_( 'COM_TICKETSTATION_YOUR_GENDER' ); ?><?php echo $requiredMark; ?></label>
                    <?php echo $this->lists['gender']; ?>
                </div>
            <?php endif; ?>

            <?php foreach ($fields as $name => [$shown, $label, $type, $autocomplete, $isRequired]) {

                if (!$shown) {
                    continue;
                }

                $error = $this->errors[$name] ?? null; ?>

                <div class="ts-field ts-field--<?php echo $name; ?><?php echo $error ? ' ts-field--invalid' : ''; ?>">
                    <label class="ts-label" for="<?php echo $name; ?>"><?php echo Text::_($label); ?><?php echo $isRequired ? $requiredMark : ''; ?></label>

                    <?php if ($type === 'select') { ?>
                        <?php echo $this->lists['country']; ?>
                    <?php } else { ?>
                        <input class="ts-input" type="<?php echo $type; ?>" name="<?php echo $name; ?>" id="<?php echo $name; ?>"
                               value="<?php echo htmlspecialchars((string) ($this->values[$name] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               <?php echo $autocomplete ? 'autocomplete="' . $autocomplete . '"' : ''; ?>
                               <?php echo $isRequired ? 'required' : ''; ?>
                               <?php echo $error ? 'aria-invalid="true" aria-describedby="' . $name . '-error"' : ''; ?> />
                    <?php } ?>

                    <?php if ($error) { ?>
                        <p class="ts-field__error" id="<?php echo $name; ?>-error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php } ?>
                </div>

            <?php } ?>

        </div>

        <div class="ts-actions">
            <a class="ts-btn ts-btn--secondary ts-btn--back" href="<?php echo $gotocart; ?>">
                <?php echo Text::_('COM_TICKETSTATION_BACK'); ?>
            </a>

            <button type="submit" class="ts-btn ts-btn--primary ts-btn--next"><?php echo Text::_('COM_TICKETSTATION_CONTINUE'); ?></button>
        </div>

        <input type="hidden" name="option" value="com_ticketstation" />
        <input type="hidden" name="controller" value="checkout" />
        <input type="hidden" name="task" value="save" />
        <?php echo HTMLHelper::_( 'form.token' ); ?>

    </form>

</div>
