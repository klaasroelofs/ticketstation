<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

namespace Ticketstation\Component\Ticketstation\Administrator\Payment;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Input\Input;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Config;
use Ticketstation\Component\Ticketstation\Administrator\Helper\History;
use Ticketstation\Component\Ticketstation\Administrator\Helper\OrderTotals;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentCurrencies;
use Ticketstation\Component\Ticketstation\Administrator\Helper\PaymentAPI;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Refund;
use Ticketstation\Component\Ticketstation\Administrator\Helper\Shop;

defined('_JEXEC') or die;

/**
 * What the core does around a payment provider: starts a payment for an order and decides what a
 * report from the provider means for the order. The provider only talks to its payment service;
 * everything about the order, its tickets and its mails stays here.
 */
final class PaymentService
{
    /**
     * Where a provider reports the outcome of a payment. Payments that were started before the
     * provider-neutral tasks existed carry task=IPNProcessPayment, which still works.
     */
    public static function webhookUrl(PaymentProviderInterface $provider): string
    {
        return Uri::root() . 'index.php?option=com_ticketstation&controller=payment&task=webhook&provider=' . rawurlencode($provider->getId());
    }

    /**
     * Where the provider sends the customer back to. The token finds the payment attempt.
     */
    public static function returnUrl(PaymentProviderInterface $provider, string $returnToken): string
    {
        return Uri::root() . 'index.php?option=com_ticketstation&controller=payment&task=return&provider=' . rawurlencode($provider->getId())
            . '&order=' . $returnToken;
    }

    /**
     * "Send tickets directly" in the Configuration: email the tickets as soon as an order is paid
     * online or a free order is completed.
     */
    public static function sendTicketsDirectly(): bool
    {
        return (int) ((new Config)->get(['send_tickets_directly'])->send_tickets_directly ?? 1) === 1;
    }

    /**
     * Starts a payment for an order and gives the address the customer goes on to. A new payment
     * attempt gets its own temporary transaction, unless an earlier attempt for this order is still
     * open.
     *
     * @throws  \RuntimeException  (PaymentException or ProviderNotConfiguredException) when it can't be started.
     */
    public static function start(PaymentProviderInterface $provider, int $ordercode, float $orderamount, ?string $method = null): string
    {
        ## Never real money in test mode (PaymentStarter tells the customer why before it gets here).
        if (Shop::testPaymentsBlocked()) {
            throw new PaymentException(Text::sprintf('COM_TICKETSTATION_TESTMODE_NO_TEST_ENVIRONMENT', $provider->getTitle()));
        }

        $db = \Joomla\CMS\Factory::getContainer()->get('DatabaseDriver');

        ## Force total of the order in this format: the decimals of the currency, a point.
        $ordertotal = PaymentCurrencies::format($orderamount, ProviderRegistry::currency());

        ## Start the API to process everything.
        $newPayment = new PaymentAPI($ordercode);
        $existing   = $newPayment->getTempTransactionByOrdercode($ordercode);

        ## A new payment attempt gets its own temporary transaction, unless an earlier attempt
        ## for this order is still open. A paid one (processed = 1) belongs to an earlier,
        ## removed order that had the same ordercode (ordercodes get reused): reusing it would
        ## make the webhook take this payment for a second payment of a paid order.
        if (!$existing || (int) $existing->processed === 1) {

            $query = $db->getQuery(true)
                ->select($db->quoteName('userid'))
                ->from($db->quoteName('#__ticketstation_orders'))
                ->where($db->quoteName('ordercode') . ' = ' . $db->quote($ordercode));

            $db->setQuery($query);

            $userid = $db->loadResult();

            ## Let the API insert a new payment to the temp transaction table.
            ## insertTempTransaction generates and returns a random token instead of using md5(ordercode).
            $return_token = $newPayment->insertTempTransaction($userid, md5($ordercode), $provider->getId());

            ## If there is no new created temporary transaction, quit here.
            if (!$return_token) {
                throw new PaymentException(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
            }
        } else {
            ## An open attempt for this order exists: reuse its token.
            $return_token = $existing->return_token;

            ## The attempt belongs to the provider that takes this payment (it may have changed).
            $newPayment->setTempProvider((int) $existing->id, $provider->getId());

            ## A new try after a failed one ("Pay again" on the result page): back to "no
            ## state yet", or the wait page would report the old failure before the provider's
            ## webhook reports this attempt.
            if ($return_token && (int) $existing->processed === 5) {
                $newPayment->updateTempTransaction($return_token, 0, '');
            }

            ## Existing rows created before the return_token column existed (or otherwise
            ## missing a token) would otherwise send the customer to the provider with an empty
            ## 'order=' redirect parameter. Backfill a fresh token onto that row instead.
            if (!$return_token) {
                $return_token = $newPayment->refreshReturnToken($existing->id);

                if (!$return_token) {
                    throw new PaymentException(Text::_('COM_TICKETSTATION_MOLLIE_ERROR_1000'));
                }
            }
        }

        $redirect = $provider->createPayment(new PaymentRequest(
            $ordercode,
            $ordertotal,
            ProviderRegistry::currency(),
            self::returnUrl($provider, $return_token),
            self::webhookUrl($provider),
            $method
        ));

        History::log($ordercode, 'payment_initiated', 'Payment initiated at ' . $provider->getTitle() . ' (transaction ' . $redirect->providerPaymentId . ')',
            [self::idKey($provider) => $redirect->providerPaymentId]);

        if ($provider->marksOrderPendingOnStart()) {
            $newPayment->paymentPendingState();
        }

        return $redirect->checkoutUrl;
    }

    /**
     * Handles a call from a provider about a payment: gets what the provider says, and does what
     * that means for the order. Called without a Joomla session, by the provider's server.
     *
     * @return  array  [HTTP status, text to answer with].
     */
    public static function handleWebhook(PaymentProviderInterface $provider, Input $input, string $rawBody): array
    {
        PaymentLog::add('IPN script called by ' . $provider->getTitle());

        try {
            $update = $provider->handleWebhook($input, $rawBody);
        } catch (WebhookRejectedException $e) {
            // Not genuine or incomplete: repeating it can't help.
            PaymentLog::add($provider->getTitle() . ' webhook rejected: ' . $e->getMessage());

            return [400, 'The report was not accepted.'];
        } catch (\RuntimeException $e) {
            // The service can't be reached, or has an error: answering with an error makes it call again.
            PaymentLog::add($provider->getTitle() . ' error while fetching payment: ' . $e->getMessage());

            return [503, 'Unable to retrieve payment from ' . $provider->getTitle() . '.'];
        }

        if ($update->state === PaymentUpdate::IGNORE) {
            PaymentLog::add($provider->getTitle() . ' report ignored: not about a payment of an order.');

            return [200, ''];
        }

        $order_id = $update->ordercode;
        PaymentLog::add('Sent ordercode: ' . $order_id);

        ## Start the API to process everything.
        $newPayment = new PaymentAPI($order_id);

        ## Look up temp transaction by ordercode (returns the newest one, with return_token).
        ## Webhooks only have the ordercode, not the token.
        $tmpTransaction = $newPayment->getTempTransactionByOrdercode($order_id);

        if (!$tmpTransaction) {
            PaymentLog::add('No temporary transaction in the database, script has been stopped.');

            return [200, 'No temporary transaction in the database.'];
        }

        ## Extract the return_token for use in updateTempTransaction calls.
        $return_token = $tmpTransaction->return_token;
        $idKey        = self::idKey($provider);

        ## The provider also calls the webhook after a payment was paid (a refund or a chargeback),
        ## and a customer may pay a second attempt of the same order. The order is complete
        ## by then, so don't run the paid branch again: it would add a transaction and create
        ## and send the tickets and the invoice once more. Another attempt that fails or
        ## expires must not overwrite the paid state either. Refunds and chargebacks of the
        ## payment are stored; one made in the provider's dashboard waits under "Needs attention"
        ## for a decision about the tickets.
        if ((int) $tmpTransaction->processed === 1) {
            if ($tmpTransaction->provider_payment_id !== $update->providerPaymentId) {
                if ($update->state === PaymentUpdate::PAID) {
                    History::log($order_id, 'payment_duplicate', 'Second payment ' . $update->providerPaymentId . ' received for an order that was already paid (transaction ' . $tmpTransaction->provider_payment_id . '); refund one of them in ' . $provider->getTitle(), [$idKey => $update->providerPaymentId, 'method' => $update->method]);
                }
            } elseif ($update->hasRefunds && $provider instanceof RefundCapableInterface) {
                try {
                    $new = Refund::syncPayment($order_id, $provider, $update->providerPaymentId);
                    PaymentLog::add('Refunds and chargebacks of ' . $update->providerPaymentId . ' stored, ' . $new . ' new.');
                } catch (\Throwable $e) {
                    PaymentLog::add('Could not store the refunds of ' . $update->providerPaymentId . ': ' . $e->getMessage());

                    return [500, ''];
                }
            }

            PaymentLog::add('Payment ' . $update->providerPaymentId . ' for an already paid order, not processed again.');

            return [200, ''];
        }

        switch ($update->state) {
            ## PAYMENT SUCCESFULL
            case PaymentUpdate::PAID:
                ## Getting the amounts for this order.
                $amount = OrderTotals::get($order_id, true)->total;

                $currency    = ProviderRegistry::currency();
                $digits      = PaymentCurrencies::digits($currency);
                $netto_price = PaymentCurrencies::format((float) $amount, $currency);
                $paid_price  = $update->amount;

                PaymentLog::add('Amount to be paid:' . $netto_price . ' ' . $currency);
                PaymentLog::add('Amount paid by customer:' . $paid_price . ' ' . $update->currency);

                ## The same amount, in the currency of the shop. A provider that doesn't say which
                ## currency was paid (empty) isn't held to it.
                $sameAmount   = abs(round((float) $paid_price, $digits) - round((float) $netto_price, $digits)) < 0.0001;
                $sameCurrency = $update->currency === '' || strtoupper($update->currency) === $currency;

                if ($sameAmount && $sameCurrency) {
                    ## Update the order state in the order table:
                    $payment_state = $newPayment->updateOrder();

                    ## Insert transaction details:
                    $newPayment->saveTransaction($order_id, $tmpTransaction->userid, $update->details, $paid_price, ucfirst($update->method), $provider->getId());

                    ## if state is true, create the tickets:
                    $ticket_creator = false;

                    if ($payment_state == true) {
                        $ticket_creator = $newPayment->createTickets();
                        PaymentLog::add('Tickets created');
                    }

                    ## set temporary payment to 1 (paid order)
                    $newPayment->updateTempTransaction($return_token, '1', $update->providerPaymentId, $update->providerPaymentId);

                    ## if tickets has been created:
                    if ($ticket_creator == true && self::sendTicketsDirectly()) {
                        $newPayment->sendTickets();
                        PaymentLog::add('Tickets sent');
                    }

                    ## Clearing the session:
                    $newPayment->clearSession();
                } else {
                    ## set temporary payment to 2 (wrong amount)
                    $newPayment->updateTempTransaction($return_token, '2');
                }

                break;

            case PaymentUpdate::OPEN:
                $newPayment->updateTempTransaction($return_token, '3');
                break;

            case PaymentUpdate::PENDING:
                ## The customer has done their part but the money isn't confirmed yet (a delayed payment
                ## method). The order stays pending until the provider reports it; the automatic cleanup
                ## removes it when that takes longer than "Remove Unpaid/Pending Orders after". Logged
                ## once: a provider may repeat the report.
                if ((int) $tmpTransaction->processed !== 4) {
                    History::log($order_id, 'payment_pending', 'Payment at ' . $provider->getTitle() . ' is not confirmed yet' . ($update->method !== '' ? ' (payment method: ' . $update->method . ')' : '') . '; the tickets are created once it is. If it takes longer than the removal days in the Configuration, the order is removed by the automatic cleanup (transaction ' . $update->providerPaymentId . ')', [$idKey => $update->providerPaymentId, 'method' => $update->method]);
                }

                $newPayment->updateTempTransaction($return_token, '4');
                break;

            case PaymentUpdate::FAILED:
                $newPayment->updateTempTransaction($return_token, '5');
                History::log($order_id, 'payment_failed', 'Payment failed at ' . $provider->getTitle() . ' (transaction ' . $update->providerPaymentId . ')', [$idKey => $update->providerPaymentId, 'method' => $update->method]);
                break;

            case PaymentUpdate::CANCELLED:
                $newPayment->updateTempTransaction($return_token, '5');
                History::log($order_id, 'payment_cancelled', 'Payment cancelled by customer (transaction ' . $update->providerPaymentId . ')', [$idKey => $update->providerPaymentId, 'method' => $update->method]);
                break;

            case PaymentUpdate::EXPIRED:
                $newPayment->updateTempTransaction($return_token, '5');
                History::log($order_id, 'payment_expired', 'Payment expired before completion (transaction ' . $update->providerPaymentId . ')', [$idKey => $update->providerPaymentId, 'method' => $update->method]);
                break;

            default:
                $newPayment->updateTempTransaction($return_token, '5');
        }

        return [200, ''];
    }

    /**
     * The key a provider's payment id gets in the order history data: "mollie_id" for Mollie, as
     * it always was.
     */
    private static function idKey(PaymentProviderInterface $provider): string
    {
        return $provider->getId() . '_id';
    }
}
