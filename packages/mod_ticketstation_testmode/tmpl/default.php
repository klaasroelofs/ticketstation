<?php
/**
 * @package     Ticketstation
 * @subpackage  mod_ticketstation_testmode
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/**
 * @var  \Joomla\Registry\Registry  $params  Module parameters
 */
?>
<div class="alert alert-warning d-flex flex-wrap align-items-center gap-2 mb-3<?php echo $params->get('moduleclass_sfx') ? ' ' . htmlspecialchars((string) $params->get('moduleclass_sfx'), ENT_QUOTES, 'UTF-8') : ''; ?>" role="status">
    <span class="fa fa-flask" aria-hidden="true"></span>
    <strong><?php echo Text::_('MOD_TICKETSTATION_TESTMODE_TITLE'); ?>.</strong>
    <span><?php echo Text::_('MOD_TICKETSTATION_TESTMODE_TEXT'); ?></span>
    <a class="alert-link ms-auto" href="index.php?option=com_ticketstation&amp;view=controlpanel"><?php echo Text::_('MOD_TICKETSTATION_TESTMODE_LINK'); ?></a>
</div>
