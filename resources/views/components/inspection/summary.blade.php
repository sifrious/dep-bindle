<section class="bindle-card" aria-labelledby="{{ $id }}-title">
    <h2 id="{{ $id }}-title">Inspection summary</h2>
    <dl>
        <dt>Status</dt><dd>{{ $snapshot->state->value }}</dd>
        <dt>Workspace</dt><dd><code>{{ $snapshot->workspaceId }}</code></dd>
        <dt>Scope</dt><dd><code>{{ $snapshot->scope }}</code></dd>
        @if($snapshot->revision)<dt>Revision</dt><dd><code>{{ $snapshot->revision }}</code></dd>@endif
        <dt>Inspected</dt><dd><time datetime="{{ $snapshot->inspectedAt->format(DATE_ATOM) }}">{{ $snapshot->inspectedAt->format('Y-m-d H:i:s T') }}</time></dd>
        <dt>Symbols</dt><dd><data value="{{ count($snapshot->symbols) }}">{{ count($snapshot->symbols) }}</data></dd>
        <dt>Resources</dt><dd><data value="{{ count($snapshot->resources) }}">{{ count($snapshot->resources) }}</data></dd>
        <dt>Relationships</dt><dd><data value="{{ count($snapshot->relationships) }}">{{ count($snapshot->relationships) }}</data></dd>
    </dl>
    @if($snapshot->message)<p>{{ $snapshot->message }}</p>@elseif($snapshot->state->value === 'empty')<p>Inspection completed with no findings.</p>@endif
</section>
