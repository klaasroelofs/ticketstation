<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

defined('_JEXEC') or die('Restricted access');

/**
 * Links a checkout field to a custom field of the Joomla user (Users > Fields), so the checkout
 * starts with what the logged-in visitor stored there. The links are kept as JSON in
 * config.checkout_field_map: checkout field name => id of the user field.
 */
class CheckoutFieldMap
{
    /**
     * The checkout fields that can be linked: field name => the "Show ..." setting that switches
     * it on in the Configuration.
     */
    public const FIELDS = [
        'gender'      => 'show_salutation',
        'address'     => 'show_address',
        'address2'    => 'show_secondaddress',
        'address3'    => 'show_thirdaddress',
        'zipcode'     => 'show_zipcode',
        'city'        => 'show_city',
        'country_id'  => 'show_country',
        'phonenumber' => 'show_phone',
    ];

    /**
     * The saved links, without the ones for a field that is not linkable.
     *
     * @param   string|null  $json  config.checkout_field_map
     *
     * @return  int[]  Checkout field name => user field id
     */
    public static function decode(?string $json): array
    {
        $map = json_decode((string) $json, true);

        if (!is_array($map)) {
            return [];
        }

        $clean = [];

        foreach (self::FIELDS as $name => $setting) {
            if (!empty($map[$name]) && (int) $map[$name] > 0) {
                $clean[$name] = (int) $map[$name];
            }
        }

        return $clean;
    }

    /**
     * The posted links as the JSON to save; only ids of existing user fields are kept.
     *
     * @param   mixed  $posted  checkout_map[<field name>] = user field id
     */
    public static function encode($posted): string
    {
        $valid = array_column(self::userFields(), 'id');
        $map   = [];

        foreach (self::FIELDS as $name => $setting) {
            $id = is_array($posted) ? (int) ($posted[$name] ?? 0) : 0;

            if ($id > 0 && in_array($id, $valid, true)) {
                $map[$name] = $id;
            }
        }

        return $map ? json_encode($map) : '';
    }

    /**
     * The custom fields of users, for the list in the Configuration.
     *
     * @return  array[]  Each with id (int), title and type
     */
    public static function userFields(): array
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName(['id', 'title', 'type']))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = ' . $db->quote('com_users.user'))
            ->where($db->quoteName('state') . ' = 1')
            ->order($db->quoteName('title'));

        $db->setQuery($query);

        $fields = [];

        foreach ($db->loadObjectList() as $row) {
            $fields[] = ['id' => (int) $row->id, 'title' => $row->title, 'type' => $row->type];
        }

        return $fields;
    }

    /**
     * What the user has stored in the linked custom fields, as values for the checkout form.
     * A field the user left empty is not in the result.
     *
     * @param   object  $user  The logged-in Joomla user
     * @param   array   $map   See decode()
     *
     * @return  array  Checkout field name => value
     */
    public static function valuesFor(object $user, array $map): array
    {
        if (!$map || empty($user->id)) {
            return [];
        }

        try {
            $fields = FieldsHelper::getFields('com_users.user', $user, false);
        } catch (\Throwable $e) {
            return [];
        }

        $raw = [];

        foreach ($fields as $field) {
            $value = $field->rawvalue ?? '';
            $value = is_array($value) ? reset($value) : $value;

            if (trim((string) $value) !== '') {
                $raw[(int) $field->id] = trim((string) $value);
            }
        }

        $values = [];

        foreach ($map as $name => $id) {
            if (!isset($raw[$id])) {
                continue;
            }

            $value = $name === 'gender' ? self::gender($raw[$id]) : ($name === 'country_id' ? self::countryId($raw[$id]) : $raw[$id]);

            if ($value !== '' && $value !== 0) {
                $values[$name] = $value;
            }
        }

        return $values;
    }

    /**
     * The salutation option of the checkout (1 = Mr, 2 = Mrs) for a stored value, or ''.
     */
    private static function gender(string $value): string
    {
        $value = mb_strtolower(trim($value, " .\t"));

        if (in_array($value, ['1', 'm', 'mr', 'man', 'male', 'heer', 'dhr', 'de heer', 'hr'], true)) {
            return '1';
        }

        if (in_array($value, ['2', 'f', 'v', 'mrs', 'ms', 'mw', 'mevr', 'mevrouw', 'vrouw', 'female', 'frau'], true)) {
            return '2';
        }

        return '';
    }

    /**
     * The id of a country of Ticketstation for its id, name or 2 or 3 letter code, or 0.
     */
    private static function countryId(string $value): int
    {
        $db    = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true)
            ->select($db->quoteName('country_id'))
            ->from($db->quoteName('#__ticketstation_country'))
            ->where($db->quoteName('published') . ' = 1')
            ->where($db->quoteName('country_id') . ' != 1')
            ->where('(' . $db->quoteName('country') . ' = :value1 OR ' . $db->quoteName('country_2_code') . ' = :value2 OR '
                . $db->quoteName('country_3_code') . ' = :value3' . (ctype_digit($value) ? ' OR ' . $db->quoteName('country_id') . ' = ' . (int) $value : '') . ')')
            ->bind(':value1', $value)
            ->bind(':value2', $value)
            ->bind(':value3', $value);

        $db->setQuery($query, 0, 1);

        return (int) $db->loadResult();
    }
}
