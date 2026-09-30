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

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ticketstation\Component\Ticketstation\Administrator\Helper\SiteCaptcha;


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

        ## The site's default captcha (Global Configuration), when one is set; not for staff.
        $this->captcha = SiteCaptcha::display('losttickets');

        parent::display($tpl);
    }

}
