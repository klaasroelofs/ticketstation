<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Helper;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Factory;

/**
 * The site's default captcha (Global Configuration > Default Captcha) on the public forms:
 * Lost tickets and the checkout. Without a default captcha nothing is shown or checked.
 *
 * Logged-in site users are the organisation's own staff, so they never get a captcha.
 */
class SiteCaptcha
{
    /** The name of the captcha's form field */
    public const FIELD = 'captcha';

    /**
     * The captcha field for a form, or '' when no captcha applies.
     *
     * @param   string  $form  Short name of the form, e.g. 'checkout'; the field id and the
     *                         captcha's namespace are derived from it.
     */
    public static function display(string $form): string
    {
        $captcha = self::instance($form);

        if (!$captcha)
        {
            return '';
        }

        try
        {
            return (string) $captcha->display(self::FIELD, 'ts-' . $form . '-captcha', 'required');
        }
        catch (\Throwable $e)
        {
            return '';
        }
    }

    /**
     * Whether the answer to the captcha of a form is right; always true when no captcha applies.
     */
    public static function check(string $form): bool
    {
        $captcha = self::instance($form);

        if (!$captcha)
        {
            return true;
        }

        try
        {
            return $captcha->checkAnswer(Factory::getApplication()->getInput()->post->getString(self::FIELD, ''));
        }
        catch (\Throwable $e)
        {
            return false;
        }
    }

    private static function instance(string $form): ?Captcha
    {
        $app    = Factory::getApplication();
        $plugin = (string) $app->get('captcha', '0');

        if ($plugin === '' || $plugin === '0' || !$app->getIdentity()->guest)
        {
            return null;
        }

        try
        {
            return Captcha::getInstance($plugin, ['namespace' => 'com_ticketstation.' . $form]);
        }
        catch (\Throwable $e)
        {
            return null;
        }
    }
}
