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

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/**
 * Schema.org Event data (JSON-LD) for the public ticket pages, so search engines can show date,
 * place and price of an event.
 *
 * A published parent ticket is one performance: it has its own date, time and venue. The events
 * list therefore gives one Event per parent ticket; parent tickets of the same event with the
 * same date, time and venue (for example separate adult and child tickets) are merged into one
 * Event with an offer per ticket. The page of a single ticket gives that ticket's Event.
 *
 * Only what the page itself shows is given: the venue follows the "Show venue" settings, and
 * without prices on the page (Show price in the list) there are no offers either.
 */
class JsonLd
{
    private const SCHEMA = 'https://schema.org/';

    /**
     * The Events list: one Event per performance, from the rows of UpcomingModel::getList().
     *
     * @param   object[]  $rows      Parent tickets, with eventname, eventdescription, startdate, enddate, venue fields
     *                               and variant_min_price / variant_max_price
     * @param   object    $config    Needs show_venue, show_venue_address, show_venue_website, show_price_eventlist
     * @param   string    $currency  ISO 4217 code
     */
    public static function listing(array $rows, object $config, string $currency): void
    {
        $groups = [];

        foreach ($rows as $row) {
            $groups[$row->eventid . '|' . $row->startdate . '|' . $row->venue][] = $row;
        }

        $nodes = [];

        foreach ($groups as $group) {
            $offers = [];

            if ($config->show_price_eventlist == 1) {
                foreach ($group as $row) {
                    $offers[] = self::ticketOffer($row, $row->variant_min_price, $row->variant_max_price, $currency);
                }
            }

            $nodes[] = self::eventNode($group[0], $config, $offers);
        }

        self::render($nodes);
    }

    /**
     * The page of one ticket, with or without seat numbers.
     *
     * @param   object    $ticket    The parent ticket joined with its event and venue
     * @param   object[]  $children  Its published child tickets (variants), empty for a seated ticket
     * @param   object    $config    Needs show_venue, show_venue_address, show_venue_website
     * @param   string    $currency  ISO 4217 code
     */
    public static function single(object $ticket, array $children, object $config, string $currency): void
    {
        $offers = [];

        if ($ticket->show_seatplans != 1 && $children) {
            // Each variant is sold on its own, with its own price and availability.
            foreach ($children as $child) {
                $offers[] = self::offerNode(
                    $child->ticketname,
                    $child->ticketprice,
                    $currency,
                    self::availability(Availability::forPurchase((int) $child->ticketid), (int) $child->ticketid, true),
                    self::url($ticket)
                );
            }
        } else {
            $range    = self::priceRange((int) $ticket->ticketid);
            $offers[] = self::ticketOffer($ticket, $range[0], $range[1], $currency);
        }

        self::render([self::eventNode($ticket, $config, $offers)]);
    }

    /**
     * The currency prices are charged in, as an ISO 4217 code.
     */
    public static function currency(): string
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('currency'))
                ->from($db->quoteName('#__ticketstation_mollie'))
                ->where($db->quoteName('configid') . ' = 1')
        );

        return MollieCurrencies::fromConfig((string) $db->loadResult());
    }

    private static function eventNode(object $row, object $config, array $offers): array
    {
        $node = [
            '@type'               => 'Event',
            'name'                => $row->eventname,
            'startDate'           => self::isoDate($row->startdate),
            'eventStatus'         => self::SCHEMA . 'EventScheduled',
            'eventAttendanceMode' => self::SCHEMA . 'OfflineEventAttendanceMode',
            'url'                 => self::url($row),
        ];

        $end = self::isoDate($row->enddate ?? null);

        if ($end && $end > $node['startDate']) {
            $node['endDate'] = $end;
        }

        $description = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $row->eventdescription), ENT_QUOTES, 'UTF-8')));

        if ($description !== '') {
            $node['description'] = mb_strlen($description) > 300 ? rtrim(mb_substr($description, 0, 299)) . '…' : $description;
        }

        $image = self::image($row);

        if ($image) {
            $node['image'] = $image;
        }

        // Like the pages: without "Show venue" there is no venue information at all.
        if ($config->show_venue == 1 && trim((string) $row->venue) !== '') {
            $node['location'] = self::placeNode($row, $config);
        }

        $offers = array_values(array_filter($offers));

        if ($offers) {
            $node['offers'] = count($offers) === 1 ? $offers[0] : $offers;
        }

        return $node;
    }

    private static function placeNode(object $row, object $config): array
    {
        $address = ['@type' => 'PostalAddress'];

        if ($config->show_venue_address == 1) {
            if (trim((string) $row->street) !== '') {
                $address['streetAddress'] = $row->street;
            }

            if (trim((string) $row->zipcode) !== '') {
                $address['postalCode'] = $row->zipcode;
            }
        }

        if (trim((string) $row->city) !== '') {
            $address['addressLocality'] = $row->city;
        }

        $place = ['@type' => 'Place', 'name' => $row->venue];

        if (count($address) > 1) {
            $place['address'] = $address;
        }

        if ($config->show_venue_website == 1 && trim((string) ($row->website ?? '')) !== '') {
            $place['url'] = $row->website;
        }

        return $place;
    }

    /**
     * The offer of a parent ticket: one price, or the range of its variants.
     */
    private static function ticketOffer(object $row, $low, $high, string $currency): array
    {
        $low  = $low === null ? (float) $row->ticketprice : (float) $low;
        $high = $high === null ? $low : (float) $high;

        $availability = self::availability(
            Availability::summary((int) $row->ticketid)->available,
            (int) $row->ticketid,
            $row->show_seatplans != 1
        );

        if ($low == $high) {
            return self::offerNode($row->ticketname, $low, $currency, $availability, self::url($row));
        }

        return [
            '@type'         => 'AggregateOffer',
            'name'          => $row->ticketname,
            'lowPrice'      => self::price($low),
            'highPrice'     => self::price($high),
            'priceCurrency' => $currency,
            'availability'  => $availability,
            'url'           => self::url($row),
        ];
    }

    private static function offerNode(string $name, $price, string $currency, string $availability, string $url): array
    {
        return [
            '@type'         => 'Offer',
            'name'          => $name,
            'price'         => self::price((float) $price),
            'priceCurrency' => $currency,
            'availability'  => $availability,
            'url'           => $url,
        ];
    }

    /**
     * Sold out, only for sale at the box office (online payments are off), or in stock.
     */
    private static function availability(int $available, int $ticketid, bool $all): string
    {
        if ($available < 1) {
            return self::SCHEMA . 'SoldOut';
        }

        return Shop::boxOfficeOnly($ticketid, $all) ? self::SCHEMA . 'InStoreOnly' : self::SCHEMA . 'InStock';
    }

    /**
     * Lowest and highest price of the published variants of a ticket, or of the ticket itself
     * when it has none.
     */
    private static function priceRange(int $ticketid): array
    {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery(
            $db->getQuery(true)
                ->select(['MIN(' . $db->quoteName('ticketprice') . ') AS low', 'MAX(' . $db->quoteName('ticketprice') . ') AS high'])
                ->from($db->quoteName('#__ticketstation_tickets'))
                ->where($db->quoteName('parent') . ' = ' . $ticketid)
                ->where($db->quoteName('published') . ' = 1')
        );
        $range = $db->loadObject();

        return $range && $range->low !== null ? [$range->low, $range->high] : [null, null];
    }

    /**
     * The page that sells this ticket, as an absolute URL.
     */
    private static function url(object $row): string
    {
        $itemid = TicketstationFunctions::getSiteItemid();
        $url    = $row->show_seatplans == 1
            ? 'index.php?option=com_ticketstation&view=seatedevent&cid=' . (int) $row->ticketid
            : 'index.php?option=com_ticketstation&view=event&id=' . (int) $row->ticketid;

        return Route::_($url . ($itemid ? '&Itemid=' . $itemid : ''), false, Route::TLS_IGNORE, true);
    }

    /**
     * The background image of the ticket, or else of the event.
     */
    private static function image(object $row): ?string
    {
        foreach (['ticket' . (int) $row->ticketid, 'event' . (int) $row->eventid] as $name) {
            $path = 'administrator/components/com_ticketstation/assets/images/ticketbackgrounds/' . $name . '.jpg';

            if (file_exists(JPATH_ROOT . '/' . $path)) {
                return Uri::root() . $path;
            }
        }

        return null;
    }

    /**
     * A date as entered in the forms (site-local time) with the site's offset, as ISO 8601.
     */
    private static function isoDate(?string $local): ?string
    {
        if (!$local || str_starts_with($local, '0000-00-00')) {
            return null;
        }

        try {
            $zone = new \DateTimeZone(Factory::getApplication()->get('offset') ?: 'UTC');

            return (new \DateTime($local, $zone))->format('c');
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function price(float $price): string
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * Puts the nodes in the page head as one JSON-LD block.
     */
    private static function render(array $nodes): void
    {
        $nodes = array_values(array_filter($nodes, fn ($node) => !empty($node['startDate'])));

        if (!$nodes) {
            return;
        }

        $json = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $nodes],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );

        Factory::getApplication()->getDocument()->getWebAssetManager()
            ->addInlineScript($json, [], ['type' => 'application/ld+json']);
    }
}
