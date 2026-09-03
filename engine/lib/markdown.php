<?php
declare(strict_types=1);

require_once VJ_ENGINE . '/vendor/Parsedown.php';

/**
 * Parsedown wrapper plus the engine's markdown extensions:
 *  - ::: tip / note / warning callout blocks (nested markdown rendered)
 *  - deduplicated id slugs on h2/h3
 *  - rel="noopener" on external links, class="wa-link" on wa.me links
 *  - images upgraded to <figure>/<picture> with intrinsic dimensions
 */
final class Markdown
{
    private static ?Parsedown $pd = null;
    /** @var array<string,int> */
    private static array $usedIds = [];

    private static function parser(): Parsedown
    {
        if (self::$pd === null) {
            $pd = new Parsedown();
            // Safe mode off on purpose: only the site owner writes content (spec §3).
            $pd->setBreaksEnabled(false);
            $pd->setUrlsLinked(false);
            self::$pd = $pd;
        }
        return self::$pd;
    }

    /** Render a full markdown document to HTML. */
    public static function render(string $md, bool $resetIds = true): string
    {
        if ($resetIds) {
            self::$usedIds = [];
        }
        $md = str_replace(["\r\n", "\r"], "\n", $md);

        $blocks = [];
        $md     = self::extractCallouts($md, $blocks);

        $html = self::parser()->text($md);

        foreach ($blocks as $token => $blockHtml) {
            $html = str_replace(['<p>' . $token . '</p>', $token], [$blockHtml, $blockHtml], $html);
        }

        return self::postProcess($html);
    }

    /** Render a short markdown fragment (FAQ answers, testimonials) without heading ids. */
    public static function inline(string $md): string
    {
        $html = self::parser()->text(str_replace(["\r\n", "\r"], "\n", trim($md)));
        return self::postProcess($html, false);
    }

    /** Render a single line with no surrounding <p>. */
    public static function line(string $md): string
    {
        return self::parser()->line(trim($md));
    }

    /* ------------------------------------------------------------ callouts */

    private static function extractCallouts(string $md, array &$blocks): string
    {
        $lines  = explode("\n", $md);
        $out    = [];
        $count  = 0;
        $n      = count($lines);
        for ($i = 0; $i < $n; $i++) {
            if (!preg_match('/^:::\s*(tip|note|warning)\s*(.*)$/i', trim($lines[$i]), $m)) {
                $out[] = $lines[$i];
                continue;
            }
            $kind  = strtolower($m[1]);
            $label = trim($m[2]);
            $inner = [];
            $i++;
            while ($i < $n && trim($lines[$i]) !== ':::') {
                $inner[] = $lines[$i];
                $i++;
            }
            $token = 'ENGINECALLOUT' . ($count++) . 'ENDCALLOUT';
            $body  = self::render(implode("\n", $inner), false);
            $head  = $label !== ''
                ? '<p class="callout__label">' . e($label) . '</p>'
                : '';
            $blocks[$token] = '<aside class="callout callout--' . e($kind) . '" role="note">' . $head . $body . '</aside>';
            $out[] = '';
            $out[] = $token;
            $out[] = '';
        }
        return implode("\n", $out);
    }

    /* -------------------------------------------------------- post process */

    private static function postProcess(string $html, bool $headingIds = true): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $prev = libxml_use_internal_errors(true);
        $doc  = new DOMDocument('1.0', 'UTF-8');
        $ok   = $doc->loadHTML(
            '<?xml encoding="UTF-8">' . $html,
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        if (!$ok) {
            return $html;
        }

        if ($headingIds) {
            self::addHeadingIds($doc);
        }
        self::fixLinks($doc);
        self::upgradeImages($doc);

        $out = '';
        foreach (iterator_to_array($doc->childNodes) as $node) {
            if ($node->nodeType === XML_PI_NODE) {
                continue;
            }
            $out .= $doc->saveHTML($node);
        }
        return trim($out);
    }

    private static function addHeadingIds(DOMDocument $doc): void
    {
        foreach (['h2', 'h3'] as $tag) {
            foreach (iterator_to_array($doc->getElementsByTagName($tag)) as $h) {
                /** @var DOMElement $h */
                if ($h->hasAttribute('id')) {
                    continue;
                }
                $base = Util::slugify($h->textContent);
                if ($base === '') {
                    continue;
                }
                $id = $base;
                if (isset(self::$usedIds[$base])) {
                    $id = $base . '-' . (++self::$usedIds[$base]);
                } else {
                    self::$usedIds[$base] = 1;
                }
                $h->setAttribute('id', $id);
            }
        }
    }

    private static function fixLinks(DOMDocument $doc): void
    {
        $host = parse_url((string)Config::v('base_url', ''), PHP_URL_HOST) ?: '';
        foreach (iterator_to_array($doc->getElementsByTagName('a')) as $a) {
            /** @var DOMElement $a */
            $href = $a->getAttribute('href');
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, '/')) {
                continue;
            }
            if (preg_match('#^(mailto|tel):#i', $href)) {
                continue;
            }
            $linkHost = parse_url($href, PHP_URL_HOST) ?: '';
            if ($linkHost !== '' && $linkHost !== $host) {
                $rel = trim($a->getAttribute('rel') . ' noopener');
                $a->setAttribute('rel', trim(implode(' ', array_unique(explode(' ', $rel)))));
            }
            if (str_contains($linkHost, 'wa.me') || str_contains($linkHost, 'api.whatsapp.com')) {
                $cls = trim($a->getAttribute('class') . ' wa-link');
                $a->setAttribute('class', trim($cls));
                $a->setAttribute('target', '_blank');
            }
        }
    }

    private static function upgradeImages(DOMDocument $doc): void
    {
        foreach (iterator_to_array($doc->getElementsByTagName('img')) as $img) {
            /** @var DOMElement $img */
            $src   = $img->getAttribute('src');
            $alt   = $img->getAttribute('alt');
            $title = $img->getAttribute('title');

            if ($alt === '-') {
                $img->setAttribute('alt', '');
                $alt = '';
            }
            $img->setAttribute('loading', 'lazy');
            $img->setAttribute('decoding', 'async');

            $dims = Images::dimensions($src);
            if ($dims !== null) {
                $img->setAttribute('width', (string)$dims[0]);
                $img->setAttribute('height', (string)$dims[1]);
            }

            $replacement = $img;
            $srcset = Images::webpSrcset($src);
            if ($srcset !== null) {
                $picture = $doc->createElement('picture');
                $source  = $doc->createElement('source');
                $source->setAttribute('type', 'image/webp');
                $source->setAttribute('srcset', $srcset);
                $source->setAttribute('sizes', '(min-width: 800px) 760px, 100vw');
                $picture->appendChild($source);
                $img->parentNode?->replaceChild($picture, $img);
                $picture->appendChild($img);
                $replacement = $picture;
            }

            $parent = $replacement->parentNode;
            if (!$parent instanceof DOMElement || strtolower($parent->nodeName) !== 'p') {
                continue;
            }
            if (trim($parent->textContent) !== '') {
                continue; // image sits inside a sentence: leave it inline
            }
            $figure = $doc->createElement('figure');
            $figure->setAttribute('class', 'figure');
            $parent->parentNode?->replaceChild($figure, $parent);
            $figure->appendChild($replacement);
            if ($title !== '') {
                $img->removeAttribute('title');
                $cap = $doc->createElement('figcaption');
                $cap->appendChild($doc->createTextNode($title));
                $figure->appendChild($cap);
            }
        }
    }

    /** Words per minute reading estimate over the raw markdown body. */
    public static function readingTime(string $md): int
    {
        $words = str_word_count(Util::stripMarkdown($md), 0, 'áéíóúüñÁÉÍÓÚÜÑ0123456789');
        return max(1, (int)ceil($words / 200));
    }
}
