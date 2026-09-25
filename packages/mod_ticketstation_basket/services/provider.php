<?php

/**
 * @package     Joomla.Site
 * @subpackage  mod_ticketstation_basket
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die;

use Joomla\CMS\Extension\Service\Provider\HelperFactory;
use Joomla\CMS\Extension\Service\Provider\Module;
use Joomla\CMS\Extension\Service\Provider\ModuleDispatcherFactory;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new ModuleDispatcherFactory('\\Ticketstation\\Module\\TicketstationBasket'));
        $container->registerServiceProvider(new HelperFactory('\\Ticketstation\\Module\\TicketstationBasket\\Site\\Helper'));
        $container->registerServiceProvider(new Module());
    }
};
