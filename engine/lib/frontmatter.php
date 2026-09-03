<?php
declare(strict_types=1);

/**
 * Parser and serialiser for the deliberately small YAML subset used in front matter.
 *
 * Supported (and nothing else):
 *   key: value                      scalar (quoted strings, true/false, ints, null/empty)
 *   key: [a, b]                     inline list of scalars
 *   key:                            block list of scalars
 *     - item
 *   key:                            one level of nested map
 *     sub: value
 *   key:                            list of maps (one level of keys inside each item)
 *     - k: v
 *       k2: v2
 *   key: >                          folded block scalar (joined with spaces)
 *   key: |                          literal block scalar
 *   # comment on its own line
 */
final class Frontmatter
{
    /**
     * Split a raw file into [front matter array, body string].
     * A file with no front matter yields an empty array and the whole file as body.
     *
     * @return array{0: array<string,mixed>, 1: string}
     */
    public static function parseFile(string $raw): array
    {
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw) ?? $raw;
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);
        if (!str_starts_with($raw, "---\n")) {
            return [[], $raw];
        }
        $end = strpos($raw, "\n---", 3);
        while ($end !== false) {
            $after = substr($raw, $end + 4, 1);
            if ($after === "\n" || $after === '' || $after === false) {
                break;
            }
            $end = strpos($raw, "\n---", $end + 1);
        }
        if ($end === false) {
            return [[], $raw];
        }
        $fm   = substr($raw, 4, $end - 3);
        $body = substr($raw, $end + 4);
        $body = ltrim($body, "\n");
        return [self::parse($fm), $body];
    }

    /** @return array<string,mixed> */
    public static function parse(string $yaml): array
    {
        $yaml  = str_replace(["\r\n", "\r"], "\n", $yaml);
        $lines = explode("\n", $yaml);
        $i     = 0;
        $out   = self::parseMap($lines, $i, 0);
        return is_array($out) ? $out : [];
    }

    /** Indentation width of a line, or null for blank/comment lines. */
    private static function indentOf(string $line): ?int
    {
        if (trim($line) === '' || preg_match('/^\s*#/', $line)) {
            return null;
        }
        return strlen($line) - strlen(ltrim($line, ' '));
    }

    /** Advance $i past blank and comment lines. */
    private static function skipNoise(array $lines, int &$i): void
    {
        while ($i < count($lines) && self::indentOf($lines[$i]) === null) {
            $i++;
        }
    }

    /** @return array<string,mixed> */
    private static function parseMap(array $lines, int &$i, int $indent): array
    {
        $map = [];
        while (true) {
            self::skipNoise($lines, $i);
            if ($i >= count($lines)) {
                break;
            }
            $ind = self::indentOf($lines[$i]);
            if ($ind === null || $ind < $indent) {
                break;
            }
            $line = trim($lines[$i]);
            if (str_starts_with($line, '- ') || $line === '-') {
                break; // a list where a map was expected: caller handles it
            }
            if (!preg_match('/^([A-Za-z0-9_][A-Za-z0-9_.-]*)\s*:(.*)$/', $line, $m)) {
                $i++; // unparseable line: skip rather than fail the whole file
                continue;
            }
            $key  = $m[1];
            $rest = trim($m[2]);
            $i++;

            if ($rest === '>' || $rest === '|' || $rest === '>-' || $rest === '|-') {
                $map[$key] = self::parseBlockScalar($lines, $i, $ind, $rest);
                continue;
            }
            if ($rest !== '') {
                $map[$key] = self::scalar($rest);
                continue;
            }
            // Nothing after the colon: look ahead for a nested block.
            $peek = $i;
            self::skipNoise($lines, $peek);
            $childIndent = $peek < count($lines) ? self::indentOf($lines[$peek]) : null;
            if ($childIndent === null || $childIndent <= $ind) {
                $map[$key] = null;
                continue;
            }
            $childLine = trim($lines[$peek]);
            if (str_starts_with($childLine, '- ') || $childLine === '-') {
                $map[$key] = self::parseList($lines, $i, $childIndent);
            } else {
                $map[$key] = self::parseMap($lines, $i, $childIndent);
            }
        }
        return $map;
    }

    /** @return list<mixed> */
    private static function parseList(array $lines, int &$i, int $indent): array
    {
        $list = [];
        while (true) {
            self::skipNoise($lines, $i);
            if ($i >= count($lines)) {
                break;
            }
            $ind = self::indentOf($lines[$i]);
            if ($ind === null || $ind < $indent) {
                break;
            }
            $line = trim($lines[$i]);
            if (!str_starts_with($line, '- ') && $line !== '-') {
                break;
            }
            $rest = $line === '-' ? '' : trim(substr($line, 2));
            $i++;

            if ($rest !== '' && preg_match('/^([A-Za-z0-9_][A-Za-z0-9_.-]*)\s*:(.*)$/', $rest, $m)) {
                // list of maps: first pair is inline, the remaining pairs are indented further
                $item = [];
                $val  = trim($m[2]);
                if ($val === '>' || $val === '|' || $val === '>-' || $val === '|-') {
                    $item[$m[1]] = self::parseBlockScalar($lines, $i, $indent + 2, $val);
                } else {
                    $item[$m[1]] = $val === '' ? null : self::scalar($val);
                }
                while (true) {
                    self::skipNoise($lines, $i);
                    if ($i >= count($lines)) {
                        break;
                    }
                    $ind2 = self::indentOf($lines[$i]);
                    if ($ind2 === null || $ind2 <= $indent) {
                        break;
                    }
                    $l2 = trim($lines[$i]);
                    if (str_starts_with($l2, '- ')) {
                        break;
                    }
                    if (!preg_match('/^([A-Za-z0-9_][A-Za-z0-9_.-]*)\s*:(.*)$/', $l2, $m2)) {
                        $i++;
                        continue;
                    }
                    $i++;
                    $v2 = trim($m2[2]);
                    if ($v2 === '>' || $v2 === '|' || $v2 === '>-' || $v2 === '|-') {
                        $item[$m2[1]] = self::parseBlockScalar($lines, $i, $ind2, $v2);
                    } else {
                        $item[$m2[1]] = $v2 === '' ? null : self::scalar($v2);
                    }
                }
                $list[] = $item;
                continue;
            }
            $list[] = self::scalar($rest);
        }
        return $list;
    }

    private static function parseBlockScalar(array $lines, int &$i, int $parentIndent, string $marker): string
    {
        $collected = [];
        $blockInd  = null;
        while ($i < count($lines)) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $collected[] = '';
                $i++;
                continue;
            }
            $ind = strlen($line) - strlen(ltrim($line, ' '));
            if ($ind <= $parentIndent) {
                break;
            }
            $blockInd ??= $ind;
            $collected[] = substr($line, min($ind, $blockInd));
            $i++;
        }
        while ($collected && trim((string)end($collected)) === '') {
            array_pop($collected);
        }
        if (str_starts_with($marker, '>')) {
            $paras = [];
            $cur   = [];
            foreach ($collected as $l) {
                if (trim($l) === '') {
                    if ($cur) {
                        $paras[] = implode(' ', $cur);
                        $cur = [];
                    }
                } else {
                    $cur[] = trim($l);
                }
            }
            if ($cur) {
                $paras[] = implode(' ', $cur);
            }
            return implode("\n\n", $paras);
        }
        return implode("\n", $collected);
    }

    private static function scalar(string $v): mixed
    {
        $v = trim($v);
        if ($v === '' || $v === '~' || strcasecmp($v, 'null') === 0) {
            return null;
        }
        $len = strlen($v);
        if ($len >= 2 && (($v[0] === '"' && $v[$len - 1] === '"') || ($v[0] === "'" && $v[$len - 1] === "'"))) {
            $inner = substr($v, 1, -1);
            return $v[0] === '"' ? str_replace(['\\"', '\\n'], ['"', "\n"], $inner) : str_replace("''", "'", $inner);
        }
        if (str_starts_with($v, '[') && str_ends_with($v, ']')) {
            $inner = trim(substr($v, 1, -1));
            if ($inner === '') {
                return [];
            }
            return array_map(
                static fn(string $p): mixed => self::scalar(trim($p)),
                str_getcsv($inner, ',', '"', '\\')
            );
        }
        if (strcasecmp($v, 'true') === 0 || strcasecmp($v, 'yes') === 0) {
            return true;
        }
        if (strcasecmp($v, 'false') === 0 || strcasecmp($v, 'no') === 0) {
            return false;
        }
        if (preg_match('/^-?\d+$/', $v)) {
            $int = (int)$v;
            if ((string)$int === $v) {
                return $int;
            }
        }
        // Strip an inline trailing comment only when preceded by whitespace.
        if (preg_match('/^(.*?)\s+#\s.*$/', $v, $m)) {
            return trim($m[1]);
        }
        return $v;
    }

    /* ------------------------------------------------------------------ dump */

    /** Serialise a front-matter array. Round-trips everything parse() understands. */
    public static function dump(array $data): string
    {
        $out = '';
        foreach ($data as $key => $value) {
            $out .= self::dumpPair((string)$key, $value, 0);
        }
        return $out;
    }

    /** Build a complete content file: front matter fence + body. */
    public static function dumpFile(array $data, string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", rtrim($body));
        return "---\n" . self::dump($data) . "---\n\n" . $body . "\n";
    }

    private static function dumpPair(string $key, mixed $value, int $indent): string
    {
        $pad = str_repeat(' ', $indent);
        if ($value === null) {
            return $pad . $key . ":\n";
        }
        if (is_bool($value)) {
            return $pad . $key . ': ' . ($value ? 'true' : 'false') . "\n";
        }
        if (is_int($value) || is_float($value)) {
            return $pad . $key . ': ' . $value . "\n";
        }
        if (is_array($value)) {
            if ($value === []) {
                return $pad . $key . ": []\n";
            }
            if (array_is_list($value)) {
                $out = $pad . $key . ":\n";
                foreach ($value as $item) {
                    if (is_array($item)) {
                        $first = true;
                        foreach ($item as $k => $v) {
                            $line = self::dumpPair((string)$k, $v, $indent + 4);
                            if ($first) {
                                $line  = $pad . '  - ' . ltrim($line);
                                $first = false;
                            }
                            $out .= $line;
                        }
                        if ($first) {
                            $out .= $pad . "  -\n";
                        }
                    } else {
                        $out .= $pad . '  - ' . self::dumpScalar($item) . "\n";
                    }
                }
                return $out;
            }
            $out = $pad . $key . ":\n";
            foreach ($value as $k => $v) {
                $out .= self::dumpPair((string)$k, $v, $indent + 2);
            }
            return $out;
        }

        $s = (string)$value;
        if (str_contains($s, "\n")) {
            $out = $pad . $key . ": |\n";
            foreach (explode("\n", rtrim($s, "\n")) as $l) {
                $out .= $pad . '  ' . $l . "\n";
            }
            return $out;
        }
        return $pad . $key . ': ' . self::dumpScalar($s) . "\n";
    }

    private static function dumpScalar(mixed $v): string
    {
        if ($v === null) {
            return '';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_int($v) || is_float($v)) {
            return (string)$v;
        }
        $s = (string)$v;
        $needsQuotes = $s === ''
            || preg_match('/^[\s>|#&*!%@`\[\]{},]/', $s)
            || preg_match('/[:#]\s/', $s)
            || str_ends_with($s, ':')
            || preg_match('/^(true|false|yes|no|null|~|-?\d+)$/i', $s)
            || $s !== trim($s);
        if ($needsQuotes) {
            return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $s) . '"';
        }
        return $s;
    }
}
