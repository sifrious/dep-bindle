<section aria-labelledby="{{ $id }}-title"><h2 id="{{ $id }}-title">Component inventory</h2>
@if($state === 'not-scanned')<p>Components have not been scanned.</p>@elseif($state === 'unavailable')<p>Component inspection unavailable.</p>@elseif($components === [])<p>Inspection found no components.</p>@else
<table class="bindle-record-table"><caption>Bindle-discovered components and optional Novella associations</caption><thead><tr><th scope="col">Component</th><th scope="col">Kind</th><th scope="col">Source</th><th scope="col">Evidence owner</th><th scope="col">Novella contract</th></tr></thead><tbody>
@foreach($components as $component)<tr><td><code>{{ $component['name'] }}</code></td><td>{{ $component['kind'] }}</td><td><code title="{{ $component['source'] }}">{{ $component['source'] }}</code></td><td>Bindle</td><td>{{ $component['contract'] ?? 'Unmatched' }}</td></tr>@endforeach
</tbody></table>@endif</section>
