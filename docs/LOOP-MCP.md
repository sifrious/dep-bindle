# Loop MCP read contracts

Bindle owns the provider-neutral MCP representation, not the Loop domain. The
version 1 contract projects existing identities from Elwin, Titan, Logres,
Funes, Stacks, and the application composition defined by MME-2273. It creates
no Loop table, provider session identity, work-item identity, or copied domain
record.

## Resource surface

`LoopMcpResourceAdapter::resourceTemplates()` publishes read templates for:

- loop, plan, epic, phase, task, step, run, and attempt;
- dependency, blocker, question, and decision;
- verification plan and evidence;
- execution target, workspace reference, and terminal outcome.

Every URI has the form
`loop://resources/{resource-type}{?owner,type,id,object_version}`. The query
coordinates are the fields of
`sifrious/reference-contract`'s `CrossPackageReference`. A checkout path may be
an authorized workspace fact, but it is never a repository or resource
identity.

The expected canonical owners follow MME-2273:

| Concern | Canonical owner |
|---|---|
| deliberation, question, decision | Elwin |
| plan, step, planned task, dependency, readiness | Titan |
| run, attempt, target selection, verification plan, terminal result | Logres |
| evidence and historical provenance | Funes |
| repository, checkout, workspace | Stacks |
| composed Loop, epic, and phase projections | the MME-2273 application projection and the identities it composes |

This mapping is descriptive. Bindle does not import those packages or redefine
their statuses. A resource adapter receives their stable
`CrossPackageReference` values and domain-selected projection fields.

## Representation

Each resource carries:

- the canonical reference, or `null` when policy conceals/forbids it;
- the existing actor and tenant `AuthorizationContext`;
- the domain-owned `AuthorizationDecision`;
- separate facts, deterministic derivations, and AI interpretations;
- explicit `known`, `unknown`, or `redacted` field availability.

Deterministic derivations name their method and basis references. Known AI
interpretations identify their producer and basis. Unknown and redacted values
cannot carry a hidden value, producer, or reference list.

Provider-native IDs are data at an integration boundary, never the resource
reference. If disclosed, expose them as an `external-identifiers` fact whose
value identifies the provider, provider resource type, provider ID, and
authorized provider-account reference. Do not use a Linear issue ID, provider
run ID, or provider session ID as the canonical `reference`.

Denied cross-tenant reads are fail-closed. `conceal_as_missing` serializes a
`missing` resolution with no canonical reference, fields, relation counts, or
completion metadata. `explicit_forbidden` produces the same empty shape with a
`forbidden` resolution.

## Schemas and compatibility

The normative schemas are:

- `resources/schemas/loop-mcp/cross-package-reference-v1.schema.json`;
- `resources/schemas/loop-mcp/authorization-context-v1.schema.json`;
- `resources/schemas/loop-mcp/resource-v1.schema.json`.

Contract version 1 is additive within its major version: consumers must ignore
new resource templates and new domain-selected field names. Existing keys,
meanings, availability states, resource types, and canonical JSON behavior
cannot change incompatibly. Removing or renaming any of them, changing a field
meaning, or changing authorization/redaction behavior requires contract version
2 and a new media type version.

`LoopResourceDocument::toJson()` recursively sorts object keys while preserving
list order. The MCP adapter returns those exact bytes as
`application/vnd.sifrious.loop-resource+json;version=1`, so PHP and MCP paths
cannot drift.

## Capability boundary

This increment defines only MCP resource templates and reads. It registers no
MCP tools and performs no mutation. Permitted next actions, policy versions,
and remaining budgets may be projected as authorized facts or derivations; they
are information, not executable capabilities.
