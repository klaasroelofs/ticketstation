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

/**
 * The texts a mail template (#__ticketstation_templates) starts with on a fresh installation,
 * so the edit screen can put them back. Keep them in step with the INSERT statements of
 * sql/install.mysql.utf8.sql.
 */
class TemplateDefaults
{
    private const SIGNATURE = '<p>Please don\'t hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>';

    /**
     * The default subject and body of a template, or null for a mail without a default.
     *
     * @return  array{mailsubject: string, mailbody: string}|null
     */
    public static function get(int $mailid): ?array
    {
        $texts = [
            1 => [
                'Here are your tickets!',
                '<p>Hi {firstname},</p><p>Thank you for your purchase!<br />Your order number is <strong>{ordercode}</strong>.</p><p>Please find your tickets attached to this e-mail.<br />Please don\'t hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>',
            ],
            2 => [
                'Here are your tickets!',
                '<p>Hi {firstname},</p><p>We are hereby sending you the tickets you ordered once again.<br />Please don\'t hesitate to contact us in case of any questions.</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>',
            ],
            3 => [
                'Payment link for your tickets',
                '<p>Hi {firstname},</p><p>We are sending you the payment link for the tickets you ordered:</p><p>{paymentlink}</p><p>Click on the link (or copy and paste it into your browser) to make your payment.<br/>After successful payment, the tickets will be sent directly to this email address.</p>' . self::SIGNATURE,
            ],
            4 => [
                'Confirm your spot on the waiting list',
                '<p>Hi {firstname},</p><p>You are on the waiting list for:</p><p>{orderlist}</p><p>Please confirm your spot on this waiting list. As soon as tickets become available, you will receive a separate email with a payment link — we cannot guarantee your spot on the waiting list without confirmation.</p><p>{confirmationlink}</p>' . self::SIGNATURE,
            ],
            5 => [
                'Invoice for your ordered tickets',
                '<p>Hi {firstname},</p><p>Attached you will find the invoice for your order <strong>{ordercode}</strong>.</p><p>Invoice number: {invoice_id}<br />Amount: {price}</p>' . self::SIGNATURE,
            ],
            6 => [
                'Tickets are available: payment link',
                '<p>Hi {firstname},</p><p>Good news: tickets have become available for {eventname}, which you were waiting for:</p><p>{orderlist}</p><p>Use the link below to pay within {removal_days} days; after that the link expires and the tickets are released again:</p><p>{paymentlink}</p><p>After successful payment, the tickets will be sent directly to this email address.</p>' . self::SIGNATURE,
            ],
            7 => [
                '{subject}',
                '<p>Hi {firstname},</p><p>{message}</p><p>Your order: <strong>{ordercode}</strong></p><p>{orderlist}</p>' . self::SIGNATURE,
            ],
            8 => [
                'Reminder: {eventname}',
                '<p>Hi {firstname},</p><p>This is a reminder that you have tickets for <strong>{eventname}</strong>.</p><p>When: {eventdate}, {eventtime}<br />Doors open: {doorsopen}<br />Where: {location}</p><p>{orderlist}</p><p>The tickets were sent to you earlier. Can\'t find them? Request them again here: <a href="{ticketlink}">{ticketlink}</a>. The attached calendar file adds the event to your calendar.</p><p>We look forward to seeing you!</p><p>Kind regards,</p><p>{company_name}<br />{company_website}</p>',
            ],
        ];

        if (!isset($texts[$mailid])) {
            return null;
        }

        return ['mailsubject' => $texts[$mailid][0], 'mailbody' => $texts[$mailid][1]];
    }
}
