<li class="bindle-record-list__item">
    <article>
        <header><span class="bindle-badge">{{ $symbol->kind }}</span> <code>{{ $symbol->signature ?? $symbol->name }}</code></header>
        <p title="{{ $symbol->source->relativePath }}">
            @if($symbol->source->url)
                <a href="{{ $symbol->source->url }}">View {{ $symbol->name }} source</a>
            @else
                <span>Source link unavailable:</span>
            @endif
            <code>{{ $symbol->source->relativePath }}:{{ $symbol->source->startLine }}@if($symbol->source->endLine)-{{ $symbol->source->endLine }}@endif</code>
        </p>
    </article>
</li>
