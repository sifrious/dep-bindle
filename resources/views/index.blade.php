@extends('bindle::layout')

@section('content')
    <header>
        <div>
            <h1>Bindle</h1>
            <p class="muted">Local route &amp; component inventory.</p>
        </div>
        <form method="POST" action="{{ route('bindle.panel.scan-all') }}">
            @csrf
            <button type="submit" @disabled($running !== null)>Run full scan</button>
        </form>
    </header>

    @if (session('bindle_error'))
        <div class="error">{{ session('bindle_error') }}</div>
    @endif

    @if ($running !== null)
        <div class="banner">
            A scan is in progress (run #{{ $running->id }}).
            <a href="{{ route('bindle.panel.status', ['run' => $running->id]) }}">View status &rarr;</a>
        </div>
    @endif

    <h2>Routes <span class="muted">({{ count($routes) }})</span></h2>
    <table>
        <thead>
            <tr>
                <th>Name / URI</th>
                <th>Method</th>
                <th>Framework</th>
                <th>Last scanned</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($routes as $route)
                @php($scanned = $scannedPages[$route->name ?? $route->uri] ?? null)
                <tr>
                    <td>
                        <strong>{{ $route->name ?? $route->identifier() }}</strong><br>
                        <code class="muted">{{ $route->uri }}</code>
                    </td>
                    <td><span class="badge method">{{ $route->method }}</span></td>
                    <td>{{ $route->framework }}</td>
                    <td>
                        @if ($scanned !== null)
                            run #{{ $scanned->run_id }}
                        @else
                            <span class="muted">never</span>
                        @endif
                    </td>
                    <td>
                        @if ($route->hasParameters())
                            <span class="muted" title="Add a fixture in config/bindle.php to scan parameterized routes">needs fixture</span>
                        @else
                            <form method="POST" action="{{ route('bindle.panel.scan-page') }}">
                                @csrf
                                <input type="hidden" name="route" value="{{ $route->name ?? $route->identifier() }}">
                                <button type="submit" class="secondary" @disabled($running !== null)>Scan this page</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No routes found.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>
        Components <span class="muted">({{ $components->count() }})</span>
        @if ($latest !== null)
            <span class="muted">— from run #{{ $latest->id }}</span>
        @endif
    </h2>
    @if ($components->isEmpty())
        <p class="muted">No scans yet — run a full scan to discover components.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Kind</th>
                    <th>Source</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($components as $component)
                    <tr>
                        <td><strong>{{ $component->name }}</strong></td>
                        <td>{{ $component->kind }}</td>
                        <td><code class="muted">{{ $component->source_path ?? '—' }}</code></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endsection
