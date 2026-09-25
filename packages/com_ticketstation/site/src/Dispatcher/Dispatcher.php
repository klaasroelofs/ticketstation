<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Dispatcher;

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Ticketstation\Component\Ticketstation\Administrator\Helper\CsrfGate;

/**
 * Adds a single, centralised anti-CSRF token check in front of every site-side
 * controller task, replacing the individual `$this->checkToken(...) or jexit(...)`
 * calls scattered across `site/src/Controller/*.php`. Everything else (view/task
 * resolution, default controller, media) is left to Joomla core's own
 * ComponentDispatcher — this class only adds the token gate.
 */
class Dispatcher extends ComponentDispatcher
{
    public function dispatch()
    {
        $this->checkCsrfToken();

        parent::dispatch();
    }

    /**
     * Resolves the controller/task the same way Joomla core's own
     * ComponentDispatcher does (view/controller/task input, with the
     * "controller.task" dot-notation shortcut), purely to decide whether
     * this request needs a CSRF token — it does not write anything back into
     * the request input, so Joomla core's own resolution afterwards is
     * completely unaffected by this check.
     */
    private function checkCsrfToken(): void
    {
        $rawTask = $this->input->getCmd('task', '');

        if ($rawTask === '') {
            // No task explicitly requested: this always resolves to whichever task the
            // controller registered as its own default, never one of this component's
            // mutating tasks, all of which are always invoked with an explicit task=.
            return;
        }

        $view       = $this->input->getCmd('view', '');
        $controller = $this->input->getCmd('controller', $view);
        $task       = $rawTask;

        if (strpos($task, '.') !== false) {
            [$controller, $task] = explode('.', $task, 2);
        }

        if ($controller === '') {
            // No explicit controller= param: Joomla core falls back to the
            // literal "Display" controller regardless of the view= value.
            $controller = 'display';
        }

        if (CsrfGate::isExempt('site', $controller, $task)) {
            return;
        }

        if (!Session::checkToken('request')) {
            jexit(Text::_('JINVALID_TOKEN'));
        }
    }
}
