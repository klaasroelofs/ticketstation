<?php

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Dispatcher;

defined('_JEXEC') || die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;


class Dispatcher extends ComponentDispatcher
{
    use DatabaseAwareTrait;

    /**
     * The application instance
     *
     * @var    CMSApplication
     * @since  4.0.0
     */
    protected $app;

    /**
     * The URL option for the component.
     *
     * @var    string
     * @since  9.0.0
     */
    protected $option = 'com_ticketstation';

    protected $defaultController = 'controlpanel';

    public function dispatch()
    {
        // Check the minimum supported PHP version
        $minPHPVersion = '8.3.0';

        if (version_compare(PHP_VERSION, $minPHPVersion, 'lt')) {
            throw new \RuntimeException(
                sprintf(
                    'Ticketstation requires at least PHP version %s. Your server currently uses PHP version %s. Please upgrade your PHP version.',
                    $minPHPVersion, PHP_VERSION
                )
            );
        }

        $this->onBeforeDispatch();

        parent::dispatch();
    }

    protected function onBeforeDispatch()
    {
        $this->checkAccess();

        // Apply the view and controller from the request, falling back to the default view/controller if necessary
        $this->applyViewAndController();

        $this->loadCommonStaticMedia();
    }

    protected function loadCommonStaticMedia()
    {
        // Make sure we run under a CMS application
        if (!($this->app instanceof CMSApplication))
        {
            return;
        }

        // Make sure the document is HTML
        $document = $this->app->getDocument();

        if (!($document instanceof HtmlDocument))
        {
            return;
        }

        /*
        $webAssetManager = $document->getWebAssetManager();

        $webAssetManager
            ->addInlineStyle(Uri::base() . 'components/com_ticketstation/assets/css/ticketstation.css');

        if (version_compare(JVERSION, '4.999.999', 'gt'))
        {
            $webAssetManager
                ->addInlineStyle(Uri::base() . 'components/com_ticketstation/assets/css/j5dark.css');
        }
        */
    }

    private function checkAccess(): void
    {
        $user = $this->app->getIdentity();

        if ($user === null || !$user->authorise('core.manage', $this->option))
        {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }

    private function applyViewAndController(): void
    {
        // Handle a custom default controller name
        $view       = $this->input->getCmd('view', $this->defaultController);
        $controller = $this->input->getCmd('controller', $view);
        $task       = $this->input->getCmd('task', 'main');

        // Check for a controller.task command.
        if (strpos($task, '.') !== false)
        {
            // Explode the controller.task command.
            [$controller, $task] = explode('.', $task);
        }

        $this->input->set('view', $controller);
        $this->input->set('controller', $controller);
        $this->input->set('task', $task);
    }
}
