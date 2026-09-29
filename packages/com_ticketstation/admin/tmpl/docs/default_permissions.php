<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Permissions topic on the central documentation page: the Joomla ACL actions of
 * admin/access.xml, what each allows (see AclGate) and the defaults set by script.php.
 */

use Joomla\CMS\Language\Text;

// No direct access to this file
defined('_JEXEC') or die('Restricted Access');

// Action title, what it allows, and the default for Manager and Administrator.
$actions = [
    ['JACTION_ADMIN', 'ADMIN', false, true],
    ['COM_TICKETSTATION_ACTION_OPTIONS', 'OPTIONS', false, true],
    ['JACTION_MANAGE', 'MANAGE', true, true],
    ['JACTION_CREATE', 'CREATE', true, true],
    ['JACTION_EDIT', 'EDIT', true, true],
    ['JACTION_EDITSTATE', 'EDITSTATE', true, true],
    ['JACTION_DELETE', 'DELETE', true, true],
    ['COM_TICKETSTATION_ACTION_BOXOFFICE', 'BOXOFFICE', true, true],
    ['COM_TICKETSTATION_ACTION_RESERVE', 'RESERVE', true, true],
    ['COM_TICKETSTATION_ACTION_SCANNERS', 'SCANNERS', true, true],
    ['COM_TICKETSTATION_ACTION_PAYMENT', 'PAYMENT', false, true],
    ['COM_TICKETSTATION_ACTION_FINANCE', 'FINANCE', false, true],
    ['COM_TICKETSTATION_ACTION_ORDER_DELETE', 'ORDER_DELETE', false, true],
];

$mark = static fn (bool $allowed) => $allowed
    ? '<span class="fa fa-check text-success" aria-hidden="true"></span><span class="visually-hidden">' . Text::_('JLIB_RULES_ALLOWED') . '</span>'
    : '<span class="fa fa-minus text-muted" aria-hidden="true"></span><span class="visually-hidden">' . Text::_('JLIB_RULES_DENIED') . '</span>';

?>

<div class="card">
    <div class="card-body">
        <h2 class="h4"><span class="fa fa-user-lock me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_TITLE') ?></h2>
        <p><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_INTRO') ?></p>

        <details id="docs-permissions-actions" class="mb-3 border rounded p-3" open>
            <summary class="h5 mb-0"><span class="fa fa-list-check me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_ACTIONS_TITLE') ?></summary>
            <div class="mt-3 table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th scope="col"><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_COL_ACTION') ?></th>
                            <th scope="col"><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_COL_ALLOWS') ?></th>
                            <th scope="col" class="text-center">Manager</th>
                            <th scope="col" class="text-center">Administrator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($actions as [$title, $key, $manager, $administrator]) { ?>
                            <tr>
                                <th scope="row" class="fw-normal text-nowrap"><?= Text::_($title) ?></th>
                                <td><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_ALLOWS_' . $key) ?></td>
                                <td class="text-center"><?= $mark($manager) ?></td>
                                <td class="text-center"><?= $mark($administrator) ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                <p class="small text-muted mb-0"><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_DEFAULTS') ?></p>
            </div>
        </details>

        <details id="docs-permissions-change" class="mb-3 border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-sliders-h me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_CHANGE_TITLE') ?></summary>
            <div class="mt-3">
                <p><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_CHANGE_1') ?></p>
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_CHANGE_2') ?></p>
            </div>
        </details>

        <details id="docs-permissions-boxoffice-group" class="border rounded p-3">
            <summary class="h5 mb-0"><span class="fa fa-users me-2" aria-hidden="true"></span><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_GROUP_TITLE') ?></summary>
            <div class="mt-3">
                <p class="mb-0"><?= Text::_('COM_TICKETSTATION_PERMISSIONS_DOCS_GROUP_1') ?></p>
            </div>
        </details>
    </div>
</div>
