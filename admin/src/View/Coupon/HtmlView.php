<?php

namespace Ticketstation\Component\Ticketstation\Administrator\View\Coupon;

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

/**
 * @package     Joomla.Administrator
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Ticketstation Coupon Admin View
 */
class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;

    function display($tpl = null)
    {

        $model       = $this->getModel();
        $this->form  = $model->getForm();
        $this->item  = $model->getItem();

        // Setup the toolbars.
        $add_edit = empty($this->item->coupon_id) ? Text::_( 'COM_TICKETSTATION_ADD' ) : Text::_( 'COM_TICKETSTATION_EDIT' );
        ToolBarHelper::title( $add_edit.' '.Text::_( 'COM_TICKETSTATION_COUPON' ), 'fa fa-percent');
        ToolBarHelper::apply();
        ToolBarHelper::save();
        if (empty($this->item->coupon_id)) {
            ToolbarHelper::cancel();
        }
        else {
            ToolbarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');
        }

        parent::display($tpl);

    }
}