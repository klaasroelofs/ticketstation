<?php

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\CategoryFactory;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Ticketstation\Component\Ticketstation\Administrator\Extension\TicketstationComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;


return new class implements ServiceProviderInterface {

    public function register(Container $container) {
		$container->registerServiceProvider(new CategoryFactory('\\Ticketstation\\Component\\Ticketstation'));
		$container->registerServiceProvider(new MVCFactory('\\Ticketstation\\Component\\Ticketstation'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Ticketstation\\Component\\Ticketstation'));
		$container->registerServiceProvider(new RouterFactory('\\Ticketstation\\Component\\Ticketstation'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {

				$component = new TicketstationComponent($container->get(ComponentDispatcherFactoryInterface::class));

				$component->setRegistry($container->get(Registry::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));
                $component->setRouterFactory($container->get(RouterFactoryInterface::class));

                return $component;
            }
        );
    }

};
