<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\View\Losttickets;

defined('_JEXEC') or die;

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;


class HtmlView extends BaseHtmlView {

    /**
     * Display the view
     *
     * @param   string  $tpl  The name of the layout file to parse.
     * @return  void
     */
    public function display($tpl = null) {

        $app = Factory::getApplication();

        $this->itemid  = $app->getInput()->getInt('Itemid', 0);
        $this->captcha = '';

        ## The site's default captcha (Global Configuration), when one is set.
        $plugin = $app->get('captcha', '0');

        if (!empty($plugin) && $plugin !== '0') {
            try {
                $captcha = Captcha::getInstance($plugin, ['namespace' => 'com_ticketstation.losttickets']);

                if ($captcha !== null) {
                    $this->captcha = $captcha->display('captcha', 'ts-losttickets-captcha', 'required');
                }
            } catch (\Throwable $e) {
                $this->captcha = '';
            }
        }

        parent::display($tpl);
    }

}
