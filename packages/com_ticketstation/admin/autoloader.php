<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

spl_autoload_register(function ($class)
{
	$path = __DIR__ . '/src/Helper/' . str_replace("\\", "/", $class) . '.php';

	if(file_exists($path))
	{
		include_once ($path);
	}

});
