# Third-party software

Ticketstation bundles the libraries below in `vendor/` (those of the Mollie plugin in its own `vendor/`), unmodified apart from one added comment line that
names the license (see "Source files" below). Ticketstation itself is licensed under the GNU General
Public License version 3 or later; every library below is released under a license that is compatible
with the GPL.

| Library | Version | License | Source |
|---|---|---|---|
| bacon/bacon-qr-code | 3.1.1 | BSD-2-Clause | https://github.com/Bacon/BaconQrCode |
| composer/ca-bundle | 1.5.14 | MIT | https://github.com/composer/ca-bundle |
| composer (autoloader) | - | MIT | https://github.com/composer/composer |
| dasprid/enum | 1.0.7 | BSD-2-Clause | https://github.com/DASPRiD/Enum |
| endroid/qr-code | 6.0.9 | MIT | https://github.com/endroid/qr-code |
| mollie/mollie-api-php | 4.0.0 | BSD-2-Clause | https://github.com/mollie/mollie-api-php |
| nyholm/psr7 | 1.8.2 | MIT | https://github.com/Nyholm/psr7 |
| setasign/fpdf | 1.9.0 | MIT | https://github.com/Setasign/FPDF |
| setasign/fpdi | 2.6.8 | MIT | https://github.com/Setasign/FPDI |

The full license text of each library is in its own folder under `vendor/`.

## Source files

Most of these libraries do not carry a license notice in every PHP file. In the installable package, a
single comment line, `// @license <license> (third-party library, see THIRD-PARTY-NOTICES.md)`, is added
below the opening `<?php` tag of those files so that the license of each file is visible to anyone, and
to license scanners, reading it on its own. The code itself and the existing copyright notices are
left as they are.
