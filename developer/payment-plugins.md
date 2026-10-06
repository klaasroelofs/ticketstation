# Writing a payment plugin for Ticketstation

Ticketstation takes online payments through **payment providers**. A provider is offered by a Joomla
plugin of the group `ticketstationpayment`, so a payment service (a regional bank, PayPal, Stripe, ...)
can be added without changing Ticketstation. Mollie is the plugin that ships with Ticketstation
(`packages/plg_ticketstationpayment_mollie`); a small, working skeleton is in
[`examples/plg_ticketstationpayment_example`](examples/plg_ticketstationpayment_example).

Requires Ticketstation 2.25 or later, Joomla 5.4 / 6 and PHP 8.3. The provider API version is
`PaymentProviderInterface::API_VERSION` (1).

## What belongs where

Ticketstation owns the order: what a payment means for an order, its tickets and mails, the amount
check, refunds' effect on tickets, ghost orders, seats and capacity. A provider only talks to the
payment service and reports back in small value objects. That is deliberate: a plugin cannot break the
order logic, and cannot skip it.

| Ticketstation does | The provider does |
|---|---|
| Creates the order and a payment attempt, builds the return and webhook addresses | Asks the payment service for a payment, gives back where the customer goes |
| Redirects the customer, shows the "checking your payment" page, handles retries and time-outs | Nothing |
| Checks the paid amount against the order, marks it paid, saves the transaction, creates and mails the tickets and invoice | Reads a report from the service, proves it is genuine, says what it is about |
| Decides what happens to the tickets of a refund | Makes refunds and reports refunds and chargebacks (optional) |

## The plugin

A normal Joomla 5/6 plugin with a service provider and a `SubscriberInterface` class, in the group
`ticketstationpayment`. Its **element is the id of the provider**. It subscribes to one event:

```php
use Ticketstation\Component\Ticketstation\Administrator\Payment\CollectProvidersEvent;

public static function getSubscribedEvents(): array
{
    return [CollectProvidersEvent::NAME => 'collect'];   // 'onTicketstationPaymentCollect'
}

public function collect(CollectProvidersEvent $event): void
{
    $event->addProvider(new MyProvider($this->params));
}
```

Ticketstation dispatches the event to the **enabled** plugins of the group when it needs a provider
(checkout, webhook, refunds, the Payments screen). A plugin that is switched off or uninstalled offers
nothing. Settings are the parameters of your plugin: define them in the manifest's `<config>` and read
them from `$this->params`. Load your own language files (`$autoloadLanguage = true`); the keys you
return from `getHealthWarnings()` are keys of your plugin. See the manifest, service provider and
classes of the example for the exact layout; none of it is specific to Ticketstation except the event.

Install it like any plugin. It then shows up on Ticketstation's **Payments** screen, where the admin
switches it on or off (its published state), opens its settings and chooses it under *Online payments*.

## The interface

`PaymentProviderInterface` (all in `Ticketstation\Component\Ticketstation\Administrator\Payment`):

| Method | Purpose |
|---|---|
| `getId()` | Machine name stored with every payment. Use the plugin's element. |
| `getTitle()` | Name for the admin. |
| `isConfigured()` | Has what it needs for real payments (live credentials). Drives the setup step on the control panel. |
| `isTestMode()` | Test mode is for staff: the shop is closed to the public while it is on, tickets get a test mark. |
| `marksOrderPendingOnStart()` | Whether the order gets the status "waiting for payment" when the customer is sent away. |
| `getHealthWarnings()` | `[['key' => language key, 'level' => 'danger'\|'warning']]`, shown under *Needs attention* while this provider takes payments. |
| `createPayment(PaymentRequest)` | Start a payment. Returns a `PaymentRedirect` (where the customer goes, and the service's id of the payment). |
| `handleWebhook(Input, string $rawBody)` | Read a report from the service and return a `PaymentUpdate`. |

Throw `PaymentException` (a message for the admin) when the service refuses or can't be reached, and
`ProviderNotConfiguredException` when there are no credentials.

### Starting a payment

`PaymentRequest` has `ordercode`, `amount` (a string with two decimals and a point, `"12.50"`),
`currency` (ISO 4217, set on the Payments screen), `returnUrl`, `webhookUrl` and `method` (the method
the customer chose, or null). Pass `returnUrl` to the service as the address to send the customer back
to, and `webhookUrl` as the address to report the result to. Keep your own id of the payment in the
`PaymentRedirect`; Ticketstation stores it and gives it back with refunds.

### The webhook

`webhookUrl` is `index.php?option=com_ticketstation&controller=payment&task=webhook&provider=<your id>`.
Ticketstation exempts exactly this address (and the return address) from Joomla's form token check: it
is called by the service's server, without a session. **That makes proving the report is genuine your
job.** Typical ways: ask the service for the payment with your own API key (what Mollie does; a
made-up id returns nothing), or verify a signature over `$rawBody`. Never trust the status, amount or
order in a report that you haven't verified.

Return a `PaymentUpdate`: `ordercode`, `providerPaymentId`, `state` (`PAID`, `OPEN`, `PENDING`,
`FAILED`, `CANCELLED`, `EXPIRED` or `UNKNOWN`), `amount` (as sent: it is compared with the order),
`currency`, `method` (stored with the transaction), `details` (your raw data about the payment, shown
under *Transactions*) and `hasRefunds`.

Reports can arrive more than once, out of order, and long after the payment (refunds, chargebacks).
Ticketstation handles that: a second report for a paid order never creates tickets twice, and a
second payment for an order that was already paid is logged as a duplicate. The webhook answers 200
with an empty body when everything went well.

### Optional: refunds and chargebacks

Implement `RefundCapableInterface` to refund from the Box Office and to report refunds and chargebacks
made in the service's dashboard: `canRefund()`, `createRefund()`, `getRefunds()` and `pollRecent()`
(for a service that doesn't report every change by webhook; return `[]` when it does). Statuses are
normalised: `queued`, `pending`, `processing`, `refunded`, `failed`, `cancelled`, `charged_back`,
`reversed`. Without this interface only refunds that the admin pays back by hand are possible for your
payments. The admin chooses what happens to the tickets; you only move the money.

### Optional: payment methods

Implement `MethodAwareInterface` when the service has several methods. `getCheckoutMethods($currency)`
returns `PaymentMethodOption`s (`id`, `label`, optional `iconUrl`); with more than one, the customer
chooses on Ticketstation's payment page and the id comes back in `PaymentRequest::$method`, so the
service's own page doesn't ask again (and a cancelled payment returns to the site).
`methodLabel($stored)` names a stored method for invoices and the Box Office.

### Optional: currencies

The payment currency is one setting of Ticketstation (prices, invoices and the structured data use it
too), set on the Payments screen, and passed to you as `PaymentRequest::$currency`. By default your
provider can be chosen for every currency Ticketstation offers (`Helper\PaymentCurrencies::CURRENCIES`:
currencies with two decimals, such as EUR, USD, GBP, SAR, TRY and AED; JPY and KWD are not offered
because amounts are always formatted with two decimals). Implement `CurrencyAwareInterface` and return
the ISO codes you can collect from `getSupportedCurrencies()`: the Payments screen then only offers
those currencies for your provider, refuses to save any other, and the control panel warns when the
saved currency isn't one of them.

## Things to know

- A payment records the provider it went through, so refunds and webhooks of earlier orders keep
  working after the admin chooses another provider, as long as your plugin stays switched on.
- Don't write to Ticketstation's tables and don't send mails or create tickets yourself.
- Ticketstation's frontend is template independent; your plugin doesn't render pages. The customer
  only ever sees your service's page and Ticketstation's own pages.
- Your plugin runs with the full rights of Joomla, like any plugin. Treat keys as secrets.
- Log what you need in your own Joomla log category; Ticketstation's payment log is
  `administrator/logs/com_ticketstation_mollie.php` (historical name).

## Trying it without a payment service

The example plugin accepts a signed report, so the whole flow can be tried by hand:

1. Install the plugin (zip the contents of `examples/plg_ticketstationpayment_example`), switch it on,
   set a secret, and choose it under *Online payments* on the Payments screen.
2. Order a ticket on the website and press *Place order*. You land on the "checking your payment" page.
3. Report the payment:

```bash
BODY='{"id":"ex_26001","order":26001,"status":"paid","amount":"12.50","currency":"EUR","method":"example"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac 'your-secret' | sed 's/^.* //')
curl -X POST -H "X-Signature: $SIG" -d "$BODY" \
  'https://your-site/index.php?option=com_ticketstation&controller=payment&task=webhook&provider=example'
```

The order is paid, the tickets are created and mailed, and the checking page moves on.
