# Forward composition

Bindle can turn a versioned behavior story into a validated, reuse-first page
composition. The forward path complements the existing reverse scan pipeline:

1. Validate provider-independent behavior input and safe target paths.
2. Query the latest scan catalog for existing layouts and components.
3. Build a deterministic Blade-first realization plan. Livewire is selected only
   when a behavior explicitly requires interaction.
4. Emit a dry-run plan and labeled desktop/mobile wireframes by default.
5. Optionally create allowlisted scaffolds. Existing files are never overwritten;
   every write is recorded in a recoverable manifest.
6. Map every selected behavior to acceptance-test skeletons and require target
   test results plus real Dusk captures before render evidence is accepted.

`bindle:compose` is local-only and dry-run by default. Provider adapters are
optional: the deterministic path does not invoke a model. Adapter output crosses
the same validation boundary as local input and may not reference components
that are absent from the supplied scan catalog.

## Safety and ownership

- Bindle owns behavior/composition contracts, catalog queries, plans, artifacts,
  safe writes, and verification evidence.
- Wardrobe may own provider invocation, but does not own the composition contract.
- Kilgore owns ChangeStory validation and fixtures.
- Burdgeon owns application read models, routes, and presentation.
- Relative targets must be traversal-safe. Scaffold writes are create-only and
  constrained to the configured allowlist.
- Placeholder screenshots are useful scan diagnostics, never successful render
  evidence.

## Burdgeon vertical slice

The bounded ChangeStory slice selects five presentation behaviors: ordered
chapters, claim relationship labels, observed-versus-inferred test evidence,
inspectable mechanical groups, and visible uncertainties. It reuses Burdgeon's
existing `layouts.app` contract and proposes only a new page target. The package
fixtures and generated plan can be verified without a provider or database.
Application feature and Dusk verification must run in Burdgeon against a real
local route; Bindle does not treat generated source or placeholder PNGs as proof.

The canonical 117-behavior inventory can be loaded through
`BehaviorClassification::fromGitBehaviorMarkdown()`. Every behavior receives one
of `reuse`, `compose`, `backend-only`, `legacy`, `deferred`, or `not-applicable`.
Backend-only behaviors are explicitly retained without inventing presentation.
