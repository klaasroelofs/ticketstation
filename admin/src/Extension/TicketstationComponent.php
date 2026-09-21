<?php

namespace Ticketstation\Component\Ticketstation\Administrator\Extension;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Dispatcher\DispatcherInterface;
use Joomla\CMS\Extension\BootableExtensionInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLRegistryAwareTrait;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Psr\Container\ContainerInterface;
use Joomla\Component\Content\Administrator\Service\HTML\AdministratorService;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ordercode;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticketcleaner;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Ticketstarter;

/**
 * Component class for com_ticketstation
 *
 * @since  4.0.0
 */
class TicketstationComponent extends MVCComponent implements
BootableExtensionInterface, RouterServiceInterface
{
    use RouterServiceTrait;
    use HTMLRegistryAwareTrait;

    private $container;

    /**
     * Booting the extension. This is the function to set up the environment of the extension like
     * registering new class loaders, etc.
     *
     * If required, some initial set up can be done from services of the container, eg.
     * registering HTML services.
     *
     * @param   ContainerInterface  $container  The container
     *
     * @return  void
     *
     * @since   4.0.0
     */
    public function boot(ContainerInterface $container)
    {
        $this->getRegistry()->register('ticketstationadministrator', new AdministratorService);

        $this->container = $container;

        $this->runStartupTasks();
    }

    /**
     * Housekeeping that used to run inline in services/provider.php::register(). Moved here so a
     * failure (eg. a DB hiccup) can never break container registration itself, and wrapped in a
     * try/catch since this is best-effort startup logic, not a hard requirement for the page to render.
     *
     * @return  void
     *
     * @since   1.7.0
     */
    private function runStartupTasks(): void
    {
        try
        {
            $session = Factory::getApplication()->getSession();

            $auto_cleanup = $session->get('auto_cleanup', '0');

            if ($auto_cleanup == 0)
            {
                $db = Factory::getContainer()->get('DatabaseDriver');

                $query = $db->getQuery(true)
                    ->select(['remove_unfinished'])
                    ->from($db->quoteName('#__ticketstation_config'))
                    ->where($db->quoteName('configid') . ' = 1');

                $db->setQuery($query);

                $data = $db->loadObject();
                $session->set('auto_cleanup', $data->remove_unfinished ?? 0);
            }
            else
            {
                $ticketcleaner = new Ticketcleaner;
                $ticketcleaner->cleanup();

                $ticketstarter = new Ticketstarter;
                $ticketstarter->publishTickets();
                $ticketstarter->unpublishTickets();
                $ticketstarter->publishEvent();
                $ticketstarter->unpublishEvent();
            }

            $ordercode = $session->get('ordercode');

            if ($ordercode == '')
            {
                $ordercode = (new Ordercode)->getTemporaryOrdercode();
                $session->set('ordercode', $ordercode);
            }
        }
        catch (\Throwable $e)
        {
            Log::add('Ticketstation startup housekeeping failed: ' . $e->getMessage(), Log::WARNING, 'com_ticketstation');
        }
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getDispatcher(CMSApplicationInterface $application): DispatcherInterface
    {
        $dispatcher = parent::getDispatcher($application);

        if (method_exists($dispatcher, 'setDatabase'))
        {
            $dispatcher->setDatabase($this->container->get(DatabaseInterface::class));
        }

        return $dispatcher;
    }
}
