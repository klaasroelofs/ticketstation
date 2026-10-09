<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;

defined('_JEXEC') or die('Restricted access');

/**
 * A No/Yes switch in Joomla's own markup (joomla.form.field.radio.switcher), for screens that
 * build their fields by hand instead of from a Form. The name can be anything, such as
 * event[12], so one list of switches posts as an array of 0/1 values.
 */
class YesNoSwitch
{
    /**
     * @param   string  $id     DOM id of the switch; its two radio inputs get a 0 and a 1 appended.
     * @param   string  $name   Name the value is posted under.
     * @param   string  $label  Accessible name (the switch itself has no visible label).
     * @param   mixed   $value  Current value, anything that is truthy for Yes.
     */
    public static function render(string $id, string $name, string $label, $value): string
    {
        return LayoutHelper::render('joomla.form.field.radio.switcher', [
            'id'            => $id,
            'name'          => $name,
            'label'         => $label,
            'value'         => $value ? '1' : '0',
            'options'       => [
                (object) ['value' => '0', 'text' => Text::_('COM_TICKETSTATION_NO')],
                (object) ['value' => '1', 'text' => Text::_('COM_TICKETSTATION_YES')],
            ],
            'onchange'      => '',
            'dataAttribute' => '',
            'class'         => '',
            'readonly'      => false,
            'disabled'      => false,
        ]);
    }
}
