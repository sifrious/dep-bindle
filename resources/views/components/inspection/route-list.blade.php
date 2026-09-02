<section aria-labelledby="{{ $id }}-title"><h2 id="{{ $id }}-title">Routes</h2>
@if($state === 'unavailable')<p>Route inspection unavailable.</p>@elseif($state === 'not-scanned')<p>Routes have not been scanned.</p>@elseif($routes === [])<p>Inspection found no routes.</p>@else
<table class="bindle-record-table"><caption>Discovered application routes</caption><thead><tr><th scope="col">Method</th><th scope="col">URI</th><th scope="col">Name</th><th scope="col">Action</th></tr></thead><tbody>
@foreach($routes as $route)<tr><td><code>{{ $route->method }}</code></td><td><code title="{{ $route->uri }}">{{ $route->uri }}</code></td><td><code>{{ $route->name ?? 'unnamed' }}</code></td><td><code title="{{ trim(($route->controller ?? '').'@'.($route->action ?? ''), '@') }}">{{ trim(($route->controller ?? '').'@'.($route->action ?? ''), '@') ?: 'closure' }}</code></td></tr>@endforeach
</tbody></table>@endif</section>
