@extends('bindle::layout')

@section('content')
    <header>
        <div>
            <h1>Bindle</h1>
            <p class="muted">Local route &amp; component inventory.</p>
        </div>
        <form method="POST" action="{{ route('bindle.panel.scan-all') }}" class="driver-form">
            @csrf
            <label class="visually-hidden" for="driver-all">Browser driver</label>
            <select name="driver" id="driver-all">
                @foreach ($driverKinds as $kind)
                    <option value="{{ $kind->value }}" @selected($kind === \Maryeperry\Bindle\Browser\DriverKind::Dusk)>
                        {{ $kind === \Maryeperry\Bindle\Browser\DriverKind::Dusk ? 'Dusk' : 'Placeholder' }} — {{ $kind->label() }}
                    </option>
                @endforeach
            </select>
            <button type="submit" @disabled($running !== null)>
                Run full scan{{ $duskAvailable ? '' : ' — no screenshots' }}
            </button>
        </form>
    </header>

    @if (session('bindle_error'))
        <div class="error"><pre>{{ session('bindle_error') }}</pre></div>
    @endif

    @if (session('bindle_notice'))
        <div class="notice"><pre>{{ session('bindle_notice') }}</pre></div>
    @endif

    @unless ($duskAvailable)
        <div class="banner">
            <strong>Screenshots are placeholders right now.</strong>
            Scans run with the placeholder driver: routes, components and Markdown are real,
            but every <code>.png</code> is a 1&times;1 file and the DOM is empty, so Alpine
            bindings are not discovered. Satisfy the requirements below to enable real screenshots.
        </div>

        <h2>Real screenshots (Dusk) — requirements</h2>
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th>Requirement</th>
                    <th>Why it matters</th>
                    <th>Fix</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($duskRequirements as $requirement)
                    <tr>
                        <td>
                            <span class="badge {{ $requirement->satisfied ? 'completed' : 'running' }}">
                                {{ $requirement->satisfied ? 'ok' : 'missing' }}
                            </span>
                        </td>
                        <td>
                            <strong>{{ $requirement->label }}</strong>
                            @if ($requirement->detail !== null)
                                <br><span class="muted">{{ $requirement->detail }}</span>
                            @endif
                        </td>
                        <td class="muted">{{ $requirement->consequence }}</td>
                        <td>
                            <code>{{ $requirement->command }}</code>
                            @if ($requirement->isFixableFromPanel())
                                <form method="POST" action="{{ route('bindle.panel.install') }}" class="toolbar">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $requirement->action }}">
                                    <button type="submit" class="secondary">Run this</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endunless

    @if ($running !== null)
        <div class="banner">
            A scan is in progress (run #{{ $running->id }}, {{ $running->driverKind()->label() }}).
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
                            @if ($latest !== null)
                                <span class="badge driver-{{ $latest->driverKind()->value }}">{{ $latest->driverKind()->label() }}</span>
                            @endif
                        @else
                            <span class="muted">never</span>
                        @endif
                    </td>
                    <td>
                        @if ($route->hasParameters())
                            <span class="muted" title="Add a fixture in config/bindle.php to scan parameterized routes">needs fixture</span>
                        @else
                            <form method="POST" action="{{ route('bindle.panel.scan-page') }}" class="driver-form">
                                @csrf
                                <input type="hidden" name="route" value="{{ $route->name ?? $route->identifier() }}">
                                <label class="visually-hidden" for="driver-{{ $loop->index }}">Browser driver</label>
                                <select name="driver" id="driver-{{ $loop->index }}">
                                    @foreach ($driverKinds as $kind)
                                        <option value="{{ $kind->value }}" @selected($kind === \Maryeperry\Bindle\Browser\DriverKind::Dusk)>
                                            {{ $kind === \Maryeperry\Bindle\Browser\DriverKind::Dusk ? 'Dusk' : 'Placeholder' }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="secondary" @disabled($running !== null)>
                                    Scan this page{{ $duskAvailable ? '' : ' — no screenshot' }}
                                </button>
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
            <span class="badge driver-{{ $latest->driverKind()->value }}">{{ $latest->driverKind()->label() }}</span>
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
