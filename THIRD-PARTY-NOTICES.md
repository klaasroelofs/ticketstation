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
| setasign/fpdi | 2.6.9 | MIT | https://github.com/Setasign/FPDI |
| tecnickcom/tcpdf | 6.11.4 | LGPL-3.0-or-later | https://github.com/tecnickcom/TCPDF |

The full license text of each library is in its own folder under `vendor/`. TCPDF is distributed
under the GNU Lesser General Public License; its code is used unmodified, so Ticketstation as a
whole remains GPL-3.0-or-later.

## Fonts

The fonts that tickets and invoices are printed in are embedded in the PDF files:

| Font | License | Source |
|---|---|---|
| Raleway | SIL Open Font License 1.1 | https://fonts.google.com/specimen/Raleway |
| Open Sans | SIL Open Font License 1.1 | https://fonts.google.com/specimen/Open+Sans |
| DejaVu Sans (in TCPDF's `fonts/`) | Bitstream Vera License (free, with DejaVu's public domain additions) | https://dejavu-fonts.github.io |

Raleway and Open Sans are used in the subset of the Windows-1252 characters
(`admin/assets/fonts/pdf`); DejaVu Sans prints everything else, such as Arabic, Persian, Cyrillic and
Greek.

## JavaScript

| Library | Version | License | Where |
|---|---|---|---|
| pdf.js (`pdfjs-dist`, legacy build) | 4.10.38 | Apache-2.0 | `admin/assets/pdfjs`, only the ticket layout editor |
| qr-scanner (Nimiq) | 1.4.2 | MIT | `site/assets/javascripts/qr-scanner`, the ticket scanner |

Each folder holds the library's own LICENSE file. The same Raleway and Open Sans fonts are also shipped
as woff2 files (`admin/assets/fonts`, Latin subset) for the layout editor.

## Source files

Most of these libraries do not carry a license notice in every PHP file. In the installable package, a
single comment line, `// @license <license> (third-party library, see THIRD-PARTY-NOTICES.md)`, is added
below the opening `<?php` tag of those files so that the license of each file is visible to anyone, and
to license scanners, reading it on its own. The code itself and the existing copyright notices are
left as they are.
