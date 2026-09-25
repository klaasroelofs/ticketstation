# Ticketstation for Joomla

Ticket sales for Joomla 6: events, seated and unseated tickets, a shopping basket, Mollie payments, PDF tickets and invoices, a box office and ticket scanning.

Ticketstation ships as one Joomla package, `pkg_ticketstation`, which contains:

| Extension | Folder |
|---|---|
| `com_ticketstation` (component) | [`packages/com_ticketstation`](packages/com_ticketstation) |
| `mod_ticketstation_basket` (basket module) | [`packages/mod_ticketstation_basket`](packages/mod_ticketstation_basket) |

Requirements: Joomla 6, PHP 8.3 or newer.

## Installing and updating

Download `pkg_ticketstation_<version>.zip` from the [latest release](https://github.com/klaasroelofs/ticketstation/releases/latest) and install it through System → Install → Extensions. After that, Joomla reports new releases under System → Update → Extensions.

## Building

The build script is PowerShell, so it runs on Windows (or anywhere PowerShell is installed).

```
cd packages/com_ticketstation/site
composer install --no-dev
cd ../../..
powershell -ExecutionPolicy Bypass -File build/build.ps1
```

This writes `dist/pkg_ticketstation_<version>.zip` and the update feed `dist/pkg_ticketstation_update.xml`.

For publishing a release, see [RELEASING.md](RELEASING.md).

## License

GNU General Public License v3.
