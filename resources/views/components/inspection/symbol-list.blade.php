<section aria-labelledby="{{ $id }}-title">
    <h2 id="{{ $id }}-title">{{ $title ?? 'Symbols' }}</h2>
    @if($snapshot->state->value === 'unavailable')
        <p role="status">Inspection unavailable. {{ $snapshot->message }}</p>
    @elseif($snapshot->state->value === 'stale')
        <p role="status">Inspection evidence is stale.</p>
    @elseif(count($snapshot->symbols) === 0)
        <p>No symbols were found in this inspected scope.</p>
    @else
        <ul class="bindle-record-list">
            @foreach($snapshot->symbols as $symbol)
                <x-bindle::inspection.symbol-row :symbol="$symbol" />
            @endforeach
        </ul>
    @endif
</section>
