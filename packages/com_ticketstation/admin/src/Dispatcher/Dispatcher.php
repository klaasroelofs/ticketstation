<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Dispatcher;

defined('_JEXEC') || die;

use Joomla\CMS\Application\CMSApplication;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseAwareTrait;
use Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate;
use Ticketstation\Component\Ticketstation\Administrator\Helper\CsrfGate;


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
        // Must run before applyViewAndController(), which overwrites the raw 'task'
        // input with its own mechanical default ('main') — see checkCsrfToken().
        $this->checkCsrfToken();

        // Apply the view and controller from the request, falling back to the default view/controller if necessary
        $this->applyViewAndController();

        $this->checkPermission();

        $this->loadCommonStaticMedia();
    }

    /**
     * Central ACL check, run once per request after the controller and task are resolved.
     * See AclGate for the permission each task needs.
     */
    private function checkPermission(): void
    {
        $controller = $this->input->getCmd('controller', '');
        $task       = $this->input->getCmd('task', '');

        if (AclGate::allows($controller, $task, $this->input)) {
            return;
        }

        // Without access to the component there is nowhere to send the user back to.
        if (!AclGate::can('core.manage')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $ajax = strtolower($this->input->server->getString('HTTP_X_REQUESTED_WITH', '')) === 'xmlhttprequest'
            || \in_array($this->input->getCmd('format', 'html'), ['json', 'raw'], true);

        if ($ajax) {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $this->app->setHeader('status', 403, true);
            $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
            $this->app->sendHeaders();
            echo json_encode(['success' => false, 'message' => Text::_('JERROR_ALERTNOAUTHOR')]);
            $this->app->close();
        }

        $this->app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
        $this->app->redirect(Uri::base() . 'index.php?option=com_ticketstation&view=controlpanel');
    }

    /**
     * Central anti-CSRF token check, run once per request before any controller task
     * executes, instead of a `$this->checkToken(...) or jexit(...)` call pasted into
     * every mutating controller method. See CsrfGate for the exempt task list.
     */
    private function checkCsrfToken(): void
    {
        $rawTask = $this->input->getCmd('task', '');

        if ($rawTask === '') {
            // No task explicitly requested. Joomla resolves this to whichever task each
            // controller registered as its own default (via RegisterControllerTasks, or
            // Joomla core's own 'display' baseline) — never one of this component's
            // mutating tasks, all of which are always invoked with an explicit task=.
            return;
        }

        $view       = $this->input->getCmd('view', $this->defaultController);
        $controller = $this->input->getCmd('controller', $view);
        $task       = $rawTask;

        if (strpos($task, '.') !== false)
        {
            [$controller, $task] = explode('.', $task);
        }

        if (CsrfGate::isExempt('administrator', $controller, $task)) {
            return;
        }

        if (!Session::checkToken('request')) {
            jexit(Text::_('JINVALID_TOKEN'));
        }
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
        */
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
