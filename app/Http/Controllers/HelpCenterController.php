<?php

namespace App\Http\Controllers;

use App\Services\V2\Operations;
use App\Support\HelpText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class HelpCenterController extends Controller
{
    public function __invoke(Request $request, Operations $operations, ?string $article = null): Response|JsonResponse
    {
        $admin = $operations->role((int) $request->user()->id) === 'admin';
        $groups = [];
        $articles = [];
        $workspace = route(config('v2.enabled') ? 'pos.index' : 'v2.index');

        foreach (config('help-center.groups') as $group => $entries) {
            foreach ($entries as $slug => [$title, $intro, $steps, $questions, $task, $adminOnly]) {
                if ($adminOnly && ! $admin) {
                    continue;
                }

                $articles[$slug] = compact('title', 'intro', 'steps', 'questions', 'group', 'adminOnly')
                    + ['feature' => $workspace.'#'.$task, 'details' => config('help-center.details.'.$slug, [])];
                $groups[$group][$slug] = $title;
            }
        }

        abort_if($article !== null && ! isset($articles[$article]), 404);
        $current = $articles[$article] ?? null;
        $input = $request->query('q', '');
        $query = is_string($input) ? trim(Str::substr($input, 0, 100)) : '';
        $results = [];
        $related = $current ? array_slice(array_diff_key($groups[$current['group']], [$article => true]), 0, 3, true) : [];

        foreach ($this->rank($articles, $query) as $slug) {
            $results[$slug] = $articles[$slug];
        }

        if ($request->wantsJson()) {
            return response()->json([
                'results' => array_map(fn (string $slug): array => [
                    'title' => $articles[$slug]['title'],
                    'group' => $articles[$slug]['group'],
                    'url' => route('help.index', $slug),
                ], array_slice(array_keys($results), 0, 6)),
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('help-center', compact(
            'groups', 'articles', 'article', 'current', 'workspace', 'query', 'results', 'related',
        ))->header('Cache-Control', 'private, no-store');
    }

    public function legacy(Request $request, ?string $article = null): RedirectResponse
    {
        $query = $request->query('q');

        return redirect()->route('help.index', array_filter(
            ['article' => $article, 'q' => is_string($query) ? $query : null],
            static fn (?string $value): bool => $value !== null,
        ));
    }

    /**
     * Urutkan artikel berdasarkan kata yang cocok, bukan kalimat utuh.
     * Artikel muncul bila minimal satu kata cocok; yang paling banyak cocok (terutama di judul) di atas.
     *
     * @param  array<string, array<string, mixed>>  $articles
     * @return list<string>
     */
    private function rank(array $articles, string $query): array
    {
        $terms = $this->terms($query);
        if ($terms === []) {
            return [];
        }

        $ranked = [];
        foreach ($articles as $slug => $entry) {
            $fields = [
                4 => $this->normalize($entry['title']),
                2 => $this->normalize(implode(' ', [$entry['intro'], ...array_keys($entry['questions'])])),
                1 => $this->normalize(implode(' ', [
                    $entry['group'], ...$entry['details'], ...$entry['steps'], ...array_values($entry['questions']),
                ])),
            ];
            $matched = 0;
            $score = 0;
            foreach ($terms as $variants) {
                foreach ($fields as $weight => $text) {
                    if (array_filter($variants, fn (string $variant): bool => str_contains($text, $variant))) {
                        $matched++;
                        $score += $weight;
                        break;
                    }
                }
            }
            if ($matched > 0) {
                $ranked[$slug] = [$matched, $score];
            }
        }

        uasort($ranked, fn (array $a, array $b): int => $b <=> $a);

        return array_keys($ranked);
    }

    /**
     * Pecah kueri menjadi kata kunci beserta bentuk dasar dan sinonimnya.
     *
     * @return list<list<string>>
     */
    private function terms(string $query): array
    {
        $words = preg_split('/[^a-z0-9]+/', $this->normalize($query), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_unique(array_diff($words, config('help-center.search.stopwords', [])));
        $synonyms = config('help-center.search.synonyms', []);
        $terms = [];

        foreach ($words as $word) {
            if (strlen($word) < 2) {
                continue;
            }
            $base = $this->stem($word);
            $variants = [$word, $base];
            foreach ($synonyms as $group) {
                if (array_intersect([$word, $base], $group)) {
                    $variants = [...$variants, ...$group];
                }
            }
            $terms[] = array_values(array_unique($variants));
        }

        return $terms;
    }

    /** Buang imbuhan umum bahasa Indonesia agar "membayar" cocok dengan "bayar". */
    private function stem(string $word): string
    {
        $base = preg_replace('/(nya|kan|an|lah)$/', '', $word);
        $base = strlen($base) >= 4 ? $base : $word;
        $stripped = preg_replace('/^(meng|meny|mem|men|me|peng|peny|pem|pen|pe|ber|di|ter)/', '', $base);

        return strlen($stripped) >= 4 ? $stripped : $base;
    }

    private function normalize(string $text): string
    {
        return preg_replace('/\bshift\b/', 'kas harian', strtolower(Str::ascii(HelpText::plain($text))));
    }
}
