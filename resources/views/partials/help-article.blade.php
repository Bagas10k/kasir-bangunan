@php
    $stepsId = $primary ? 'langkah' : 'langkah-'.$slug;
    $questionsId = $primary ? 'kendala' : 'kendala-'.$slug;
    $detailsId = $primary ? 'penjelasan' : 'penjelasan-'.$slug;
    $illustration = config('help-center.illustrations.'.$slug);
    // Tag tautan: dalam modul yang sama cukup digulir, modul lain membuka halaman panduannya.
    $helpHref = fn (string $target): ?string => isset($articles[$target])
        ? (($current && $articles[$target]['group'] === $current['group']) ? '#panduan-'.$target : route('help.index', $target))
        : null;
    $rich = fn (string $text) => \App\Support\HelpText::render($text, $slug, $helpHref, array_map(fn (array $a): string => $a['title'], $articles));
@endphp
<article class="hc-article{{ $primary ? ' is-current' : '' }}" id="panduan-{{ $slug }}" tabindex="-1" aria-labelledby="judul-{{ $slug }}">
    <header class="hc-article-hero">
        <div class="hc-article-overview">
            @if ($primary)
                <h1 class="hc-article-title" id="judul-{{ $slug }}">{{ $entry['title'] }}</h1>
            @else
                <h2 class="hc-article-title" id="judul-{{ $slug }}">{{ $entry['title'] }}</h2>
            @endif
            <p class="hc-intro">{{ $rich($entry['intro']) }}</p>
            <p class="hc-meta"><span>{{ $entry['adminOnly'] ? 'Panduan admin' : 'Kasir dan admin' }}</span></p>
        </div>
        <div class="hc-actions">
            <a class="hc-button" href="{{ $entry['feature'] }}" target="_blank" rel="noopener" aria-label="Buka fitur (tab baru)">Buka fitur <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 4h7v7m0-7-9 9m7 0v7H4V6h7"/></svg></a>
            <button type="button" class="hc-button hc-button--ghost hc-tool" data-print-article hidden>Cetak</button>
        </div>
    </header>
    @if ($entry['details'] ?? [])
        <section id="{{ $detailsId }}" class="hc-details">
            <div class="hc-section-head">
                @if ($primary)<h2>Penjelasan</h2>@else<h3>Penjelasan</h3>@endif
            </div>
            @foreach ($entry['details'] as $paragraph)
                <p>{{ $rich($paragraph) }}</p>
            @endforeach
        </section>
    @endif
    <section id="{{ $stepsId }}">
        <div class="hc-section-head">
            @if ($primary)<h2>Langkah singkat</h2>@else<h3>Langkah singkat</h3>@endif
            <span class="hc-section-count">{{ count($entry['steps']) }} langkah</span>
        </div>
        <div class="hc-guide-body{{ $illustration ? ' hc-guide-body--illustrated' : '' }}">
            @if ($illustration)
                @include('partials.help-illustration', ['illustration' => $illustration])
            @endif
            <ol class="hc-steps" role="list">
                @foreach ($entry['steps'] as $step)<li><span>{{ $rich($step) }}</span></li>@endforeach
            </ol>
        </div>
    </section>
    <section id="{{ $questionsId }}">
        <div class="hc-section-head">
            @if ($primary)<h2>Jika bingung</h2>@else<h3>Jika bingung</h3>@endif
            <span class="hc-section-count">{{ count($entry['questions']) }} jawaban</span>
        </div>
        <div class="hc-faq">
            @foreach ($entry['questions'] as $question => $answer)
                <details class="hc-faq-item" @if ($loop->first) open @endif>
                    <summary>{{ $question }}</summary>
                    <p>{{ $rich($answer) }}</p>
                </details>
            @endforeach
        </div>
    </section>
    @if ($relatedEntries)
        <section class="hc-related" id="terkait">
            <h2>Panduan terkait</h2>
            <ul class="hc-list">
                @foreach ($relatedEntries as $relatedSlug => $relatedTitle)
                    <li><a href="#panduan-{{ $relatedSlug }}"><strong>{{ $relatedTitle }}</strong></a></li>
                @endforeach
            </ul>
        </section>
    @endif
    <footer class="hc-muted">
        <p>Terakhir ditinjau {{ config('help-center.reviewed') }}</p>
        <a class="hc-modules-jump" href="#modul-panduan">Pilih panduan lain</a>
    </footer>
</article>
