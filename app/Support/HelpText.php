<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Merender teks Pusat Bantuan dengan penanda ringan.
 *
 * Penanda manual di config/help-center.php:
 *  - !!teks!!   kalimat penting (latar kuning lembut)
 *  - ((teks))   bagian opsional (diredupkan)
 *
 * Penanda otomatis dari config help-center.markup:
 *  - sebutan menu → tag tautan ke panduannya (sekali per paragraf, bukan ke artikel sendiri)
 *  - istilah → huruf miring (sekali per paragraf)
 */
final class HelpText
{
    private const MANUAL = '/!!(.+?)!!|\(\((.+?)\)\)/u';

    /**
     * @param  callable(string): ?string  $href  URL panduan tujuan, atau null bila tidak boleh dibuka.
     * @param  array<string, string>  $titles  Judul artikel yang boleh diakses, per slug.
     */
    public static function render(string $text, string $self, callable $href, array $titles): HtmlString
    {
        $used = [];
        $html = '';
        $offset = 0;

        preg_match_all(self::MANUAL, $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($matches as $match) {
            $html .= self::auto(substr($text, $offset, $match[0][1] - $offset), $self, $href, $titles, $used);
            if (($match[1][0] ?? '') !== '') {
                $html .= '<mark class="hc-important">'.self::auto($match[1][0], $self, $href, $titles, $used).'</mark>';
            } else {
                $html .= '<span class="hc-optional">'.self::auto($match[2][0], $self, $href, $titles, $used).'</span>';
            }
            $offset = $match[0][1] + strlen($match[0][0]);
        }
        $html .= self::auto(substr($text, $offset), $self, $href, $titles, $used);

        return new HtmlString($html);
    }

    /** Teks tanpa penanda, untuk pencarian. */
    public static function plain(string $text): string
    {
        return preg_replace(self::MANUAL, '$1$2', $text);
    }

    /**
     * @param  array<string, string>  $titles
     * @param  array<string, true>  $used
     */
    private static function auto(string $text, string $self, callable $href, array $titles, array &$used): string
    {
        $links = config('help-center.markup.links', []);
        $terms = config('help-center.markup.terms', []);
        $phrases = array_merge(array_keys($links), $terms);
        if ($phrases === []) {
            return e($text);
        }
        usort($phrases, fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = '/(?<![\pL\pN])('.implode('|', array_map(fn (string $p): string => preg_quote($p, '/'), $phrases)).')(?![\pL\pN])/iu';

        $html = '';
        $offset = 0;
        preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[1] as [$found, $position]) {
            $html .= e(substr($text, $offset, $position - $offset));
            $offset = $position + strlen($found);
            $html .= self::mark($found, $links, $terms, $self, $href, $titles, $used);
        }

        return $html.e(substr($text, $offset));
    }

    /**
     * @param  array<string, string>  $links
     * @param  list<string>  $terms
     * @param  array<string, string>  $titles
     * @param  array<string, true>  $used
     */
    private static function mark(string $found, array $links, array $terms, string $self, callable $href, array $titles, array &$used): string
    {
        // Sebutan menu peka huruf besar: "Kas harian" (menu) ditautkan, "kas harian" (umum) tidak.
        $slug = $links[$found] ?? null;
        if ($slug !== null && $slug !== $self && ! isset($used['link:'.$slug]) && ($url = $href($slug)) !== null) {
            $used['link:'.$slug] = true;

            return '<a class="hc-ref" href="'.e($url).'" data-tip="Buka panduan: '.e($titles[$slug] ?? $found).'">'.e($found).'</a>';
        }

        $term = mb_strtolower($found);
        if (in_array($term, array_map('mb_strtolower', $terms), true) && ! isset($used['term:'.$term])) {
            $used['term:'.$term] = true;

            return '<em class="hc-term">'.e($found).'</em>';
        }

        return e($found);
    }
}
