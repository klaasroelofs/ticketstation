<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Checks that AclGate gives every backend controller task a permission, so no task is
 * refused by accident, and reports task names used in backend templates, views and scripts
 * that the map doesn't know.
 *
 * Run from the repository root: php tests/check_acl_map.php
 */

define('_JEXEC', 1);

$admin = __DIR__ . '/../packages/com_ticketstation/admin';

require $admin . '/src/Helper/AclGate.php';

$map = (new ReflectionClass(Ticketstation\Component\Ticketstation\Administrator\Helper\AclGate::class))
    ->getConstant('ADMIN');

$errors = [];

// 1. Every public controller method, plus its registerTask() aliases.
foreach (glob($admin . '/src/Controller/*Controller.php') as $file) {
    $controller = strtolower(basename($file, 'Controller.php'));
    $source     = file_get_contents($file);

    preg_match_all('/^\s*(?:public\s+)?function\s+(\w+)\s*\(/m', $source, $methods);
    preg_match_all('/^\s*(?:private|protected)\s+(?:static\s+)?function\s+(\w+)/m', $source, $hidden);

    $tasks = array_diff(array_map('strtolower', $methods[1]), array_map('strtolower', $hidden[1]), ['__construct']);

    preg_match_all('/^\s*\$this->registerTask\(\s*\'(\w+)\'\s*,\s*\'(\w+)\'/m', $source, $aliases, PREG_SET_ORDER);

    foreach ($aliases as [, $alias, $target]) {
        if (in_array(strtolower($target), $tasks, true)) {
            $tasks[] = strtolower($alias);
        }
    }

    if (!isset($map[$controller])) {
        $errors[] = "Controller '$controller' has no entry in AclGate";
        continue;
    }

    foreach ($tasks as $task) {
        $key = in_array($task, ['main', 'display'], true) ? '' : $task;

        if (!array_key_exists($key, $map[$controller])) {
            $errors[] = "Task '$controller.$task' has no permission in AclGate";
        }
    }
}

// 2. Task names used in the backend's own pages and scripts.
$files = array_merge(
    glob($admin . '/tmpl/*/*.php'),
    glob($admin . '/tmpl/*/*/*.php'),
    glob($admin . '/src/View/*/*.php'),
    glob($admin . '/src/Controller/*.php'),
    glob($admin . '/assets/js/*.js')
);

foreach ($files as $file) {
    $source = file_get_contents($file);
    $name   = substr($file, strlen($admin) + 1);

    // "controller.task" strings as used by toolbar buttons and Joomla.submitbutton().
    preg_match_all('/[\'"]([a-z]+)\.([a-z_]+)[\'"]/i', $source, $dotted, PREG_SET_ORDER);

    foreach ($dotted as [, $controller, $task]) {
        $controller = strtolower($controller);

        if (isset($map[$controller]) && !array_key_exists(strtolower($task), $map[$controller])) {
            $errors[] = "$name uses '$controller.$task', unknown to AclGate";
        }
    }

    // controller=x&task=y in URLs.
    preg_match_all('/controller=([a-z]+)(?:&amp;|&)task=([a-z_]+)/i', $source, $urls, PREG_SET_ORDER);

    foreach ($urls as [, $controller, $task]) {
        $controller = strtolower($controller);
        $task       = strtolower($task);
        $key        = in_array($task, ['main', 'display'], true) ? '' : $task;

        // A frontend URL shown in the backend (e.g. in the documentation).
        if (!isset($map[$controller]) && is_file($admin . '/../site/src/Controller/' . ucfirst($controller) . 'Controller.php')) {
            continue;
        }

        if (!isset($map[$controller]) || !array_key_exists($key, $map[$controller])) {
            $errors[] = "$name links to '$controller.$task', unknown to AclGate";
        }
    }
}

if ($errors) {
    echo implode(PHP_EOL, array_unique($errors)), PHP_EOL;
    exit(1);
}

echo 'AclGate covers every controller task.', PHP_EOL;
