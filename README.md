# Ticketstation for Joomla

Ticket sales for Joomla 6: events, seated and unseated tickets, a shopping basket, Mollie payments, PDF tickets and invoices, a box office and ticket scanning.

## Features

- **Events and tickets**: events with venues, seated events with a seat map editor, unseated events with ticket types and capacities, coupons, and a waiting list for sold-out unseated events.
- **Ordering**: a shopping basket and checkout on your own site, with a basket module for any template position.
- **Payments**: Mollie (payment methods chosen by the customer at checkout, refunds from the box office) and Stripe Checkout out of the box, and other payment providers as plugins: see [developer/payment-plugins.md](developer/payment-plugins.md).
- **Tickets and invoices**: PDF tickets with a QR code on a design you set up yourself, invoices, a reminder mail before the event, and Apple Wallet and Google Wallet passes with live updates.
- **Box office**: orders, payments, refunds, reservations, resending tickets, and a lost-tickets page for customers.
- **At the door**: ticket scanning in the browser, with a scan overview per event.
- **Backend**: a control panel, built-in documentation, and Dutch and English translations.

Ticketstation ships as one Joomla package, `pkg_ticketstation`, which contains:

| Extension | Folder |
|---|---|
| `com_ticketstation` (component) | [`packages/com_ticketstation`](packages/com_ticketstation) |
| `mod_ticketstation_basket` (basket module): shows the visitor's cart as a compact icon or a full cart | [`packages/mod_ticketstation_basket`](packages/mod_ticketstation_basket) |
| `plg_task_ticketstation` (task plugin): scheduled tasks for the reminder mail and the wallet updates | [`packages/plg_task_ticketstation`](packages/plg_task_ticketstation) |
| `plg_system_ticketstation` (system plugin): answers the Apple Wallet web service for live updates | [`packages/plg_system_ticketstation`](packages/plg_system_ticketstation) |
| `plg_ticketstationpayment_mollie` (payment plugin): takes payments, refunds and chargebacks through Mollie | [`packages/plg_ticketstationpayment_mollie`](packages/plg_ticketstationpayment_mollie) |
| `plg_ticketstationpayment_stripe` (payment plugin): takes payments, refunds and disputes through Stripe Checkout | [`packages/plg_ticketstationpayment_stripe`](packages/plg_ticketstationpayment_stripe) |

The package installs all six together. Keep the system plugin enabled when you use live updates for Apple Wallet. For the reminder mail and the wallet updates, create the Ticketstation tasks under System → Scheduled Tasks (reminders hourly, wallet updates every 15 minutes).

Requirements: Joomla 6, PHP 8.3 or newer with the GD extension.

## Installing and updating

Download `pkg_ticketstation_<version>.zip` from the [latest release](https://github.com/klaasroelofs/ticketstation/releases/latest) and install it through System → Install → Extensions. After that, Joomla reports new releases under System → Update → Extensions.

## Building

The build script is PowerShell, so it runs on Windows (or anywhere PowerShell is installed).

```
cd packages/com_ticketstation/site
composer install --no-dev
cd ../../plg_ticketstationpayment_mollie
composer install --no-dev
cd ../..
powershell -ExecutionPolicy Bypass -File build/build.ps1
```

This writes `dist/pkg_ticketstation_<version>.zip` and the update feed `dist/pkg_ticketstation_update.xml`.

For publishing a release, see [RELEASING.md](RELEASING.md). Bundled third-party libraries are listed in [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).

## License

GNU General Public License v3.
