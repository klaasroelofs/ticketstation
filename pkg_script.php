<?php
/**
 * @package     Ticketstation
 * @subpackage  pkg_ticketstation
 *
 * @copyright   Copyright (C) 2026 Klaas Roelofs. All rights reserved.
 * @license     GNU General Public License version 3; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Installer\InstallerScript;

/**
 * Installer script for the Ticketstation package.
 *
 * After a successful install or update it shows the release notes of the installed version on
 * Joomla's installer result page. build\build.ps1 puts them in the package as release-notes.md,
 * taken from release-notes\<version>.md in the repository; the same file is the text of the
 * GitHub release. A package without that file (e.g. a release candidate) shows nothing extra.
 */
class pkg_ticketstationInstallerScript extends InstallerScript
{
    public function postflight($type, $parent)
    {
        if ($type !== 'install' && $type !== 'update') {
            return true;
        }

        $file = $parent->getParent()->getPath('source') . '/release-notes.md';

        if (is_file($file)) {
            echo '<div class="ticketstation-release-notes text-start">' . $this->markdownToHtml(file_get_contents($file)) . '</div>';
        }

        return true;
    }

    /**
     * Converts the small Markdown subset the release notes use: ## and ### headings, "- " list
     * items, paragraphs, **bold**, *italic*, `code` and [links](https://...).
     */
    private function markdownToHtml($markdown)
    {
        $html  = '';
        $list  = false;
        $para  = [];
        $lines = preg_split('/\R/', trim($markdown));

        $flushPara = function () use (&$html, &$para) {
            if ($para) {
                $html .= '<p>' . $this->inline(implode(' ', $para)) . '</p>';
                $para  = [];
            }
        };

        foreach ($lines as $line) {
            $line = rtrim($line);

            if (preg_match('/^- (.*)$/', $line, $m)) {
                $flushPara();

                if (!$list) {
                    $html .= '<ul>';
                    $list  = true;
                }

                $html .= '<li>' . $this->inline($m[1]) . '</li>';
                continue;
            }

            if ($list) {
                $html .= '</ul>';
                $list  = false;
            }

            if (preg_match('/^(#{2,3}) (.*)$/', $line, $m)) {
                $flushPara();
                $tag   = strlen($m[1]) === 2 ? 'h3' : 'h4';
                $html .= "<$tag>" . $this->inline($m[2]) . "</$tag>";
            } elseif ($line === '') {
                $flushPara();
            } else {
                $para[] = $line;
            }
        }

        $flushPara();

        if ($list) {
            $html .= '</ul>';
        }

        return $html;
    }

    private function inline($text)
    {
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);

        return preg_replace('/\[([^\]]+)\]\((https?:\/\/[^)\s]+)\)/', '<a href="$2" target="blank" rel="noopener">$1</a>', $text);
    }
}
