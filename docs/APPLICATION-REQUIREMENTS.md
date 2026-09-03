# Application requirements contract

`bindle.application-requirements.v1`

What a scanned codebase appears to need from a host, with the evidence for every
claim. Produced by Bindle's requirement detectors ([MME-2066]) and consumed by
host reconciliation ([MME-2068]), setup-plan generation ([MME-2069]) and the read
APIs ([MME-2070]).

Defined in `src/Requirements/Domain`. This document describes the wire format;
the PHP value objects are the authority.

## What it is not

- **Not an installer input.** Nothing here says what to run. Requirements carry
  typed `setupHints` describing *intent* — `ensure_service postgresql` — and
  deciding what that means on a given machine belongs to a later trusted
  installer under its own approval policy.
- **Not a workspace registry.** `workspaceId` is an opaque string. How it is
  minted, stored or resolved is Stacks' business ([MME-2064] assigns canonical
  workspace identity there); binding a scan to it is [MME-2067].
- **Not a resolver.** Conflicting sources are recorded, never silently
  reconciled.

## Shape

```json
{
  "schemaVersion": "bindle.application-requirements.v1",
  "workspaceId": "ws_01HXAMPLE",
  "revision": "9f25cdd…",
  "generatedAt": "2026-09-02T09:15:00+00:00",
  "requirements": [ … ]
}
```

`schemaVersion` is stamped on write and checked on read: a payload written
against a different version is rejected with `MalformedManifestException` rather
than partially understood.

### Requirement

| Field | Notes |
| --- | --- |
| `name` | Ecosystem-neutral identity: `php`, `pnpm`, `postgresql`. |
| `kind` | `runtime`, `package_manager`, `system_tool`, `service`, `environment_variable`, `filesystem_capability`, `build_step`. |
| `necessity` | `required`, `optional`, `dev_only`, `test_only`. |
| `confidence` | `high`, `medium`, `low` — the detector's belief that the requirement is real. |
| `version` | `{raw, normalized}`. `raw` is verbatim from the source and authoritative; `normalized` is present only when a detector could produce one deterministically. |
| `evidence` | One or more records. A requirement with none is refused at construction. |
| `setupHints` | Typed intent. Never a command. |
| `conflicts` | Disagreements, preserved. |

### Evidence and trust

Every requirement points back at what was read. `strength` grades the source,
and the ranking is the contract:

| Strength | Examples | Rank |
| --- | --- | --- |
| `lockfile` | `composer.lock`, `uv.lock`, `pnpm-lock.yaml` | 60 |
| `manifest` | `composer.json`, `package.json`, `pyproject.toml` | 50 |
| `version_file` | `.tool-versions`, `.nvmrc` | 40 |
| `config` | `Dockerfile`, compose, `.env.example` | 30 |
| `automation` | CI workflows, `Makefile` | 20 |
| `documentation` | `README.md` | 10 |

`documentation` is the only strength where `isAuthoritative()` is false. A README
line reading `brew install postgresql@16` is recorded verbatim as evidence and
may inform a `setupHint`, but it never becomes an executable action on its own
authority.

`locator` pins the observation: a workspace-relative `relativePath`, plus a
`pointer` into structured files (`/require/php`) or a 1-indexed `line` for prose.

### Conflicts

When sources disagree, both sides are kept:

```json
{
  "kind": "version_disagreement",
  "evidence": [
    { "strength": "manifest",     "excerpt": "^8.3",       "locator": { "relativePath": "composer.json", "pointer": "/require/php" } },
    { "strength": "version_file", "excerpt": "php 8.2.10", "locator": { "relativePath": ".tool-versions", "line": 1 } }
  ],
  "note": "composer.json requires ^8.3; .tool-versions pins 8.2.10."
}
```

`RequirementConflict::strongest()` returns the winner on strength alone, or
`null` when two equally-ranked sources disagree — a tie is a real answer, not a
coin toss. `ApplicationRequirementsManifest::contested()` lists every affected
requirement so reconciliation can treat them carefully.

## Worked examples

`tests/Fixtures/requirements/` holds one manifest per ecosystem, exercised by
`tests/Unit/RequirementsFixtureTest.php`:

- `laravel-composer-postgres.json` — PHP/Composer/PostgreSQL, including the
  composer-versus-`.tool-versions` conflict and a README-only install line.
- `node-pnpm.json` — Node and pnpm, lockfile outranking the manifest.
- `python-uv.json` — Python, uv, and a dev-only tool inferred from CI.

## Usage

```php
use Maryeperry\Bindle\Requirements\Domain\ApplicationRequirementsManifest;
use Maryeperry\Bindle\Requirements\Domain\RequirementKind;

$manifest = ApplicationRequirementsManifest::fromJson($json);

foreach ($manifest->ofKind(RequirementKind::Service) as $service) {
    $why = $service->strongestEvidence();

    printf("%s — %s (%s)\n", $service->name, $why->excerpt, $why->locator->describe());
}
```

[MME-2064]: https://linear.app/sifirous/issue/MME-2064
[MME-2066]: https://linear.app/sifirous/issue/MME-2066
[MME-2067]: https://linear.app/sifirous/issue/MME-2067
[MME-2068]: https://linear.app/sifirous/issue/MME-2068
[MME-2069]: https://linear.app/sifirous/issue/MME-2069
[MME-2070]: https://linear.app/sifirous/issue/MME-2070
