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
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;

/**
 * The line that points to the terms and conditions and the privacy statement (Configuration >
 * Company); a link that is not set is left out, and with neither set nothing is shown.
 *
 * Rendered with LayoutHelper::render('payment_terms', ['config' => $config], null, ['component' => 'com_ticketstation', 'client' => 0]).
 *
 * @var  array  $displayData  'config' => the Configuration
 */

$links = [];

foreach (['terms_url' => 'COM_TICKETSTATION_TERMS_AND_CONDITIONS', 'privacy_url' => 'COM_TICKETSTATION_PRIVACY_STATEMENT'] as $field => $label) {
    $url = Config::toAbsoluteLink($displayData['config']->$field ?? '');

    if ($url !== '') {
        $links[] = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">' . Text::_($label) . '</a>';
    }
}

if (count($links) == 2) { ?>
    <p class="ts-terms"><?php echo Text::sprintf('COM_TICKETSTATION_AGREE_TO_TERMS', $links[0], $links[1]); ?></p>
<?php } elseif (count($links) == 1) { ?>
    <p class="ts-terms"><?php echo Text::sprintf('COM_TICKETSTATION_AGREE_TO_TERMS_SINGLE', $links[0]); ?></p>
<?php }
