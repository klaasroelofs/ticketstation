<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Service;

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Ticketstation\Component\Ticketstation\Site\Service\TicketstationNomenuRules as NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;

/**
 * Routing class of com_ticketstation
 *
 * @since  3.3
 */
class Router extends RouterView
{
    /**
     * The category factory
     *
     * @var CategoryFactoryInterface
     *
     * @since  4.0.0
     */
    private $categoryFactory;

    /**
     * The db
     *
     * @var DatabaseInterface
     *
     * @since  4.0.0
     */
    private $db;

    /**
     * Ticketstation Component router constructor
     *
     * @param   SiteApplication           $app              The application object
     * @param   AbstractMenu              $menu             The menu object to work with
     * @param   CategoryFactoryInterface  $categoryFactory  The category object
     * @param   DatabaseInterface         $db               The database object
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu,
                                CategoryFactoryInterface $categoryFactory, DatabaseInterface $db)
    {
        $this->categoryFactory = $categoryFactory;
        $this->db              = $db;

        $upcoming = new RouterViewConfiguration('upcoming');
        $this->registerView($upcoming);

        $event = new RouterViewConfiguration('event');
        $this->registerView($event);

        $seatedevent = new RouterViewConfiguration('seatedevent');
        $this->registerView($seatedevent);

        $scanchart = new RouterViewConfiguration('scanchart');
        $this->registerView($scanchart);

        $cart = new RouterViewConfiguration('cart');
        $this->registerView($cart);

        $checkout = new RouterViewConfiguration('checkout');
        $this->registerView($checkout);

        $payment = new RouterViewConfiguration('payment');
        $this->registerView($payment);

        $paymentresult = new RouterViewConfiguration('paymentresult');
        $this->registerView($paymentresult);

        $ticketscanning = new RouterViewConfiguration('ticketscanning');
        $this->registerView($ticketscanning);

        $ticketscanner = new RouterViewConfiguration('ticketscanner');
        $this->registerView($ticketscanner);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }
}