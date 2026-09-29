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
use Joomla\CMS\Router\Route;
use Ticketstation\Component\Ticketstation\Administrator\Helper\TicketstationFunctions;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

## Get document type and add it.
$app        = Factory::getApplication();
$document   = $app->getDocument();
$document->setTitle( Text::_('COM_TICKETSTATION_LOSTTICKETS_TITLE') . ' - ' . $app->get('sitename') );
TicketstationFunctions::addSiteStylesheet();

$action = Route::_('index.php?option=com_ticketstation&task=losttickets.send' . ($this->itemid ? '&Itemid=' . $this->itemid : ''));

?>

<div class="ticketstation ticketstation--losttickets">

    <div class="page-header">
        <h1 class="ts-page-title"><?php echo Text::_('COM_TICKETSTATION_LOSTTICKETS_TITLE'); ?></h1>
    </div>

    <p class="ts-lead"><?php echo Text::_('COM_TICKETSTATION_LOSTTICKETS_INTRO'); ?></p>

    <section class="ts-card">
        <form class="ts-form" action="<?php echo $action; ?>" method="post">

            <div class="ts-form__fields">
                <div class="ts-field ts-field--email">
                    <label class="ts-label" for="ts-losttickets-email"><?php echo Text::_('COM_TICKETSTATION_LOSTTICKETS_EMAIL'); ?></label>
                    <input class="ts-input" type="email" name="email" id="ts-losttickets-email" autocomplete="email" required />
                </div>

                <?php if ($this->captcha) { ?>
                    <div class="ts-field ts-field--captcha">
                        <?php echo $this->captcha; ?>
                    </div>
                <?php } ?>
            </div>

            <div class="ts-actions">
                <button type="submit" class="ts-btn ts-btn--primary"><?php echo Text::_('COM_TICKETSTATION_LOSTTICKETS_BUTTON'); ?></button>
            </div>

            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    </section>

</div>
