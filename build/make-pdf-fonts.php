<?php
/**
 * @package     Ticketstation
 * @subpackage  com_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

/**
 * Developer tool, not part of the package: makes the TCPDF font definitions of the ticket fonts
 * (admin/assets/fonts/pdf: <name>.php, <name>.z and <name>.ctg.z) from TrueType files.
 *
 *   php build/make-pdf-fonts.php <folder with Raleway-Regular.ttf, Raleway-Bold.ttf,
 *                                 OpenSans-Regular.ttf and OpenSans-Bold.ttf>
 *
 * Needs `composer install` in packages/com_ticketstation/site. The definitions are committed, so
 * this only has to run when a font file changes. The family names (raleway, ralewayb, opensans,
 * opensansb) are the names TicketFont and Pdf use.
 */

define('TCPDF_SILENCE_DEPRECATION', true);

$repo   = dirname(__DIR__);
$source = rtrim($argv[1] ?? '', '/\\');
$target = $repo . '/packages/com_ticketstation/admin/assets/fonts/pdf';

if ($source === '' || !is_dir($source))
{
    fwrite(STDERR, "Usage: php build/make-pdf-fonts.php <folder with the .ttf files>\n");
    exit(1);
}

require $repo . '/packages/com_ticketstation/site/vendor/tecnickcom/tcpdf/tcpdf.php';

foreach (['Raleway-Regular', 'Raleway-Bold', 'OpenSans-Regular', 'OpenSans-Bold'] as $file)
{
    $path = $source . '/' . $file . '.ttf';

    if (!is_file($path))
    {
        fwrite(STDERR, "Missing $path\n");
        exit(1);
    }

    $name = TCPDF_FONTS::addTTFfont($path, 'TrueTypeUnicode', '', 96, $target . '/');

    echo $file, ' -> ', $name, "\n";
}
