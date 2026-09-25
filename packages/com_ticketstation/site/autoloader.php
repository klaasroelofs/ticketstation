<?php
/**
* @package     Joomla.Administrator
* @subpackage  com_ticketstation
*
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
*/

defined('_JEXEC') or die;

spl_autoload_register(function ($class)
{
    $path = JPATH_ADMINISTRATOR . '/components/com_ticketstation/src/Helper/' . str_replace("\\", "/", $class) . '.php';

	if(file_exists($path))
	{
		include_once ($path);
	}

});