<?php
/**
 * @package     Ticketstation
 * @subpackage  plg_ticketstationpayment_stripe
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Plugin\TicketstationPayment\Stripe\Helper;

defined('_JEXEC') or die;

/**
 * Names of the payment methods Stripe reports (the type of the charge's payment method details),
 * for the Box Office, the Transactions screen and invoices. Stripe has many more methods than
 * are listed; one that isn't gets its type with capitals ("us_bank_account" becomes "Us bank account").
 */
class StripeMethods
{
    private const LABELS = [
        'card'             => 'Card',
        'ideal'            => 'iDEAL | Wero',
        'bancontact'       => 'Bancontact',
        'sepa_debit'       => 'SEPA Direct Debit',
        'sofort'           => 'Sofort',
        'eps'              => 'EPS',
        'giropay'          => 'giropay',
        'p24'              => 'Przelewy24',
        'blik'             => 'BLIK',
        'klarna'           => 'Klarna',
        'paypal'           => 'PayPal',
        'link'             => 'Link',
        'cashapp'          => 'Cash App Pay',
        'amazon_pay'       => 'Amazon Pay',
        'revolut_pay'      => 'Revolut Pay',
        'twint'            => 'TWINT',
        'wechat_pay'       => 'WeChat Pay',
        'alipay'           => 'Alipay',
        'afterpay_clearpay' => 'Afterpay / Clearpay',
        'affirm'           => 'Affirm',
        'mobilepay'        => 'MobilePay',
        'swish'            => 'Swish',
        'multibanco'       => 'Multibanco',
        'bacs_debit'       => 'Bacs Direct Debit',
        'us_bank_account'  => 'US bank account',
    ];

    /**
     * The name of a method from the type Stripe reports ("sepa_debit"). Also accepts it with a
     * capital or with spaces instead of underscores, as an older transaction may hold it.
     */
    public static function label(string $method): string
    {
        $key = str_replace(' ', '_', strtolower(trim($method)));

        if (isset(self::LABELS[$key])) {
            return self::LABELS[$key];
        }

        return ucfirst(str_replace('_', ' ', $key));
    }
}
