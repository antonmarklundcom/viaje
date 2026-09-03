<?php
declare(strict_types=1);

require_once VJ_ENGINE . '/vendor/Parsedown.php';

/**
 * Parsedown wrapper: `:::tip` callouts, heading ids, external-link rel,
 * figure/picture image rendering. Safe mode is OFF by design — only the
 * authenticated admin writes content (spec §3).
 */
final class Markdown
{
    private static ?EngineParsedown $pd = null;

    private static function parser(): EngineParsedown
    {
        if (self::$pd === null) {
            self::$pd = new EngineParsedown();
            self::$pd->setBreaksEnabled(false);
            self::$pd->setSafeMode(false);
            self::$pd->setUrlsLinked(false);
        }
        return self::$pd;
    }

    /** Full block-level render of a markdown body. */
    public static function render(string $md): string
    {
        $p = self::parser();
        $p->resetHeadingIds();
        return self::upgradeImages($p->text($md));
    }

    /** Inline render (no wrapping <p>), for short strings such as FAQ answers. */
    public static function inline(string $md): string
    {
        return self::parser()->line($md);
    }

    /** Render a short markdown snippet that may contain paragraphs (FAQ answers). */
    public static function small(string $md): string
    {
        return self::upgradeImages(self::parser()->text($md));
    }

    /** @return list<array{level:int,id:string,text:string}> */
    public static function headings(string $html): array
    {
        if (!preg_match_all('#<h([23])[^>]*\bid="([^"]+)"[^>]*>(.*?)</h\1>#si', $html, $m, PREG_SET_ORDER)) {
            return [];
        }
        $out = [];
        foreach ($m as $h) {
            $out[] = ['level' => (int)$h[1], 'id' => $h[2], 'text' => trim(strip_tags($h[3]))];
        }
        return $out;
    }

    /**
     * Post-process generated HTML: give images dimensions and webp sources,
     * and promote an image that is alone in a paragraph to a <figure>.
     */
    private static function upgradeImages(string $html): string
    {
        // Standalone image paragraphs → <figure>.
        $html = preg_replace_callback(
            '#<p>(<img\b[^>]*>)</p>#i',
            static function (array $m): string {
                $caption = '';
                if (preg_match('/\btitle="([^"]*)"/i', $m[1], $t) && $t[1] !== '') {
                    $caption = '<figcaption>' . $t[1] . '</figcaption>';
                }
                return '<figure>' . self::img($m[1]) . $caption . '</figure>';
            },
            $html
        ) ?? $html;

        $html = preg_replace_callback('#<img\b[^>]*>#i', static fn(array $m): string => self::img($m[0]), $html) ?? $html;
        return str_replace(' data-done="1"', '', $html);
    }

    /** Add loading/decoding/dimensions, and wrap in <picture> when webp variants exist. */
    private static function img(string $tag): string
    {
        if (str_contains($tag, 'data-done="1"')) {
            return $tag;
        }
        if (!preg_match('/\bsrc="([^"]*)"/i', $tag, $s)) {
            return $tag;
        }
        $src   = html_entity_decode($s[1], ENT_QUOTES, 'UTF-8');
        $extra = ' data-done="1"';
        if (!preg_match('/\bloading=/i', $tag)) {
            $extra .= ' loading="lazy" decoding="async"';
        }
        if (!preg_match('/\bwidth=/i', $tag)) {
            $dim = Images::dimensions($src);
            if ($dim !== null) {
                $extra .= ' width="' . $dim[0] . '" height="' . $dim[1] . '"';
            }
        }
        $tag = preg_replace('~\s*/?>$~', $extra . '>', $tag, 1) ?? $tag;

        $srcset = Images::webpSrcset($src);
        if ($srcset === null) {
            return $tag;
        }
        return '<picture><source type="image/webp" srcset="' . e($srcset)
            . '" sizes="(max-width: 900px) 100vw, 900px">' . $tag . '</picture>';
    }
}

/**
 * Parsedown subclass. Kept minimal: heading ids, link rel, `:::` callouts.
 */
final class EngineParsedown extends Parsedown
{
    /** @var array<string,int> */
    private array $headingIds = [];

    public function __construct()
    {
        $this->BlockTypes[':'][] = 'Callout';
    }

    public function resetHeadingIds(): void
    {
        $this->headingIds = [];
    }

    private function headingId(string $text): string
    {
        $base = Util::slugify(trim(strip_tags($text)));
        if ($base === '') {
            $base = 'seccion';
        }
        $n  = $this->headingIds[$base] ?? 0;
        $this->headingIds[$base] = $n + 1;
        return $n === 0 ? $base : $base . '-' . ($n + 1);
    }

    protected function blockHeader($Line)
    {
        $Block = parent::blockHeader($Line);
        return $this->withHeadingId($Block);
    }

    protected function blockSetextHeader($Line, ?array $Block = null)
    {
        $Block = parent::blockSetextHeader($Line, $Block);
        return $this->withHeadingId($Block);
    }

    private function withHeadingId(?array $Block): ?array
    {
        if ($Block === null || !isset($Block['element']['name'])) {
            return $Block;
        }
        $name = $Block['element']['name'];
        if ($name !== 'h2' && $name !== 'h3') {
            return $Block;
        }
        $Block['element']['attributes']['id'] = $this->headingId((string)($Block['element']['text'] ?? ''));
        return $Block;
    }

    protected function inlineLink($Excerpt)
    {
        $Inline = parent::inlineLink($Excerpt);
        if ($Inline === null || !isset($Inline['element']['attributes']['href'])) {
            return $Inline;
        }
        $href = (string)$Inline['element']['attributes']['href'];
        if (str_contains($href, 'wa.me') || str_starts_with($href, 'https://api.whatsapp.com')) {
            $Inline['element']['attributes']['class'] = 'wa-link';
        }
        if (preg_match('#^https?://#i', $href)) {
            $host = parse_url($href, PHP_URL_HOST);
            $self = parse_url((string)Config::v('base_url', ''), PHP_URL_HOST);
            if ($host !== null && $host !== $self) {
                $Inline['element']['attributes']['rel']    = 'noopener';
                $Inline['element']['attributes']['target'] = '_blank';
            }
        }
        return $Inline;
    }

    /* ------------------------------------------------------- ::: callouts */

    protected function blockCallout($Line)
    {
        if (!preg_match('/^:::[ ]*(tip|note|warning)[ ]*(.*)$/i', $Line['text'], $m)) {
            return null;
        }
        return [
            'kind'  => strtolower($m[1]),
            'label' => trim($m[2]),
            'lines' => [],
        ];
    }

    protected function blockCalloutContinue($Line, $Block)
    {
        if (isset($Block['complete'])) {
            return null;
        }
        if (isset($Block['interrupted'])) {
            $Block['lines'][] = '';
            unset($Block['interrupted']);
        }
        if (preg_match('/^:::\s*$/', $Line['text'])) {
            $Block['complete'] = true;
            return $Block;
        }
        $Block['lines'][] = $Line['body'];
        return $Block;
    }

    protected function blockCalloutComplete($Block)
    {
        $inner = $this->text(implode("\n", $Block['lines']));
        $html  = '';
        if ($Block['label'] !== '') {
            $html .= '<p class="tip__label">' . self::escape($Block['label']) . '</p>';
        }
        $Block['element'] = [
            'name'       => 'aside',
            'attributes' => ['class' => 'tip tip--' . $Block['kind']],
            'rawHtml'    => $html . $inner,
        ];
        return $Block;
    }
}
