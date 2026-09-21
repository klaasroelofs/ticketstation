<?php

/**
 * @package     Joomla.Site
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2022 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Site\Service;

// no direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\Rules\RulesInterface;

/**
 * Rule to process URLs without a menu item
 *
 * @since  3.4
 */
class TicketstationNomenuRules implements RulesInterface
{
    /**
     * Router this rule belongs to
     *
     * @var RouterView
     * @since 3.4
     */
    protected $router;

    /**
     * Class constructor.
     *
     * @param   RouterView  $router  Router this rule belongs to
     *
     * @since   3.4
     */
    public function __construct(RouterView $router)
    {
        $this->router = $router;
    }

    /**
     * Dummymethod to fullfill the interface requirements
     *
     * @param   array  &$query  The query array to process
     *
     * @return  void
     *
     * @since   3.4
     * @codeCoverageIgnore
     */
    public function preprocess(&$query)
    {
        // TODO: Implement preprocess() method.
    }

    /**
     * Parse a menu-less URL
     *
     * @param   array  &$segments  The URL segments to parse
     * @param   array  &$vars      The vars that result from the segments
     *
     * @return  void
     *
     * @since   3.4
     */
    public function parse(&$segments, &$vars)
    {
        $vars = [];
        switch (true) {
            case $segments[0] === 'upcoming':
                $vars['view'] = 'upcoming';

                break;

            case strpos($segments[0],'event',0) !== false:
                $vars['view'] = substr($segments[0],0,strpos($segments[0],'-'));

                if (strpos($segments[0],'seatedevent',0) !== false) {
                    $vars['cid'] = substr($segments[0],strpos($segments[0],'-') + 1);
                } else {
                    $vars['id'] = substr($segments[0],strpos($segments[0],'-') + 1);
                }

                break;

            case $segments[0] === 'cart':
                $vars['view'] = 'cart';

                break;

            case $segments[0] === 'checkout':
                $vars['view'] = 'checkout';

                break;

            case $segments[0] === 'payment':
                $vars['view'] = 'payment';

                break;

            case $segments[0] === 'paymentresult':
                $vars['view'] = 'paymentresult';

                if (!empty($segments[1])) {
                    $vars['ordercode'] = $segments[1];
                }

                break;

            case $segments[0] === 'paymentresultcheck':
                $vars['option'] = 'com_ticketstation';
                $vars['task'] = 'paymentresult.return';

                if (!empty($segments[1])) {
                    $vars['ordercode'] = $segments[1];
                }

                break;

            case $segments[0] === 'ticketscanning':
                $vars['view'] = 'ticketscanning';

                break;

            case $segments[0] === 'ticketscanner':
                $vars['view'] = 'ticketscanner';

                if (strpos($segments[1], 't') === false) {
                    $eventid = substr($segments[1], strpos($segments[1], '-') + 1);
                    $vars['eventid'] = $eventid;
                } else {
                    $ticketid = substr($segments[1], strpos($segments[1], '-') + 1);
                    $vars['ticketid'] = $ticketid;
                }

                $vars['tmpl'] = 'component';

                break;

            case strpos($segments[0],'scanchart',0) !== false:
                $vars['view'] = 'scanchart';
                $vars['id'] = substr($segments[0],strpos($segments[0],'-') + 1);

                break;

            case $segments[0] === 'statistics':
                $vars['view'] = 'statistics';

                $id = explode(':', $segments[1]);
                $vars['id'] = (int)$id[0];

                $salesstats = explode(':', $segments[2]);
                $vars['salesstats'] = $salesstats[0];

                $salesperticket = explode(':', $segments[3]);
                $vars['salesperticket'] = (int)$salesperticket[0];

                $scanstats = explode(':', $segments[4]);
                $vars['scanstats'] = $scanstats[0];

                break;
        }

        // Empty array to prevent Router from throwing an exception
        $segments = array_diff($segments, $segments);

        return;
    }

    /**
     * Build a menu-less URL
     *
     * @param   array  &$query     The vars that should be converted
     * @param   array  &$segments  The URL segments to create
     *
     * @return  void
     *
     * @since   3.4
     */
    public function build(&$query, &$segments)
    {

        //$segments = [];
        if (isset($query['view']))
        {
            if ($query['view'] === 'cart') {

                $segments[] = $query['view'];
                unset($query['view']);
                unset($query['id']);
                unset($query['cid']);

            } elseif ($query['view'] === 'upcoming') {

                $segments[] = $query['view'];
                unset($query['view']);
                unset($query['ordercode']);

            } elseif ($query['view'] === 'event') {

                $segments[] = $query['view'] . '-' . $query['id'];
                unset($query['view']);
                unset($query['id']);

            } elseif ($query['view'] === 'seatedevent') {

                $segments[] = $query['view'] . '-' . $query['cid'];
                unset($query['view']);
                unset($query['cid']);

            } elseif ($query['view'] === 'scanchart') {

                $segments[] = $query['view'] . '-' . $query['id'];
                unset($query['view']);
                unset($query['id']);

            } elseif ($query['view'] === 'paymentresult') {

                $segments[] = $query['view'];

                if (isset($query['ordercode'])) {
                    $segments[] = $query['ordercode'];
                    unset($query['ordercode']);
                }

                unset($query['view']);

                if (isset($query['Itemid'])) {
                    unset($query['Itemid']);
                }

            } else {

                $segments[] = $query['view'];
                unset($query['view']);

            }
        }

        if (!empty($query['task']) && $query['task'] === 'paymentresult.return') {
            $segments[] = 'paymentresultcheck';
            if (!empty($query['ordercode'])) {
                $segments[] = $query['ordercode'];
                unset($query['ordercode']);
            }

            unset($query['task']);

            if (isset($query['Itemid'])) {
                unset($query['Itemid']);
            }

        }

        if (isset($query['id']))
        {
            $segments[] = $query['id'];
            unset($query['id']);
        };
        if (isset($query['cid']))
        {
            $segments[] = $query['cid'];
            unset($query['cid']);
        };
        if (isset($query['ordercode']))
        {
            $segments[] = $query['ordercode'];
            unset($query['ordercode']);
        };
        if (isset($query['ticketid']))
        {
            $segments[] = 't-' . $query['ticketid'];
            unset($query['ticketid']);
        };
        if (isset($query['eventid']))
        {
            $segments[] = 'e-' . $query['eventid'];
            unset($query['eventid']);
        };
        if (isset($query['tmpl']))
        {
            $segments[] = 'cleaninterface';
            unset($query['tmpl']);
        };
        if (isset($query['salesstats']))
        {
            $segments[] = $query['salesstats'];
            unset($query['salesstats']);
        };
        if (isset($query['salesperticket']))
        {
            $segments[] = $query['salesperticket'];
            unset($query['salesperticket']);
        };
        if (isset($query['scanstats']))
        {
            $segments[] = $query['scanstats'];
            unset($query['scanstats']);
        };

    }
}