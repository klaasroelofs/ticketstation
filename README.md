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

```
cd packages/com_ticketstation/site
composer install --no-dev
cd ../../..
powershell -ExecutionPolicy Bypass -File build/build.ps1
```

This writes `dist/pkg_ticketstation_<version>.zip` and the update feed `dist/pkg_ticketstation_update.xml`.

## Releasing

1. Raise `<version>` in `pkg_ticketstation.xml` (and in the manifest of every extension that changed), commit and push.
2. Tag the commit with the package version and push the tag:

   ```
   git tag v2.3.9
   git push origin v2.3.9
   ```

The [release workflow](.github/workflows/release.yml) then builds the package and publishes a GitHub release with the zip and the update feed. Joomla sites read the feed from the latest release. A tag with a suffix, such as `v2.4.0-rc1`, becomes a pre-release, which is not offered to sites as an update.

## License

GNU General Public License v3.
