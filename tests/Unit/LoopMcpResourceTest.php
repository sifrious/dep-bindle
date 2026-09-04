<?php

declare(strict_types=1);

use Maryeperry\Bindle\LoopMcp\LoopMcpResourceAdapter;
use Maryeperry\Bindle\LoopMcp\LoopResourceDocument;
use Maryeperry\Bindle\LoopMcp\LoopResourceField;
use Maryeperry\Bindle\LoopMcp\LoopResourceType;
use Opis\JsonSchema\Parsers\SchemaParser;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\SchemaLoader;
use Opis\JsonSchema\Validator;
use Sifrious\AuthorizationContract\ActorContext;
use Sifrious\AuthorizationContract\ActorKind;
use Sifrious\AuthorizationContract\AuthorizationContext;
use Sifrious\AuthorizationContract\AuthorizationDecision;
use Sifrious\AuthorizationContract\DisclosureMode;
use Sifrious\AuthorizationContract\TenantScope;
use Sifrious\HarnessContractFixtures\Fixture;
use Sifrious\ReferenceContract\CrossPackageReference;

function loopReference(string $owner, string $type, string $id, ?string $version = null): CrossPackageReference
{
    return new CrossPackageReference($owner, $type, $id, $version);
}

function loopAuthorization(string $account = 'account_a', string $tenant = 'tenant_a'): AuthorizationContext
{
    return new AuthorizationContext(
        new ActorContext(
            loopReference('sifrious/zahir', 'account', $account),
            ActorKind::Human,
            originatingService: loopReference('maryeperry/bindle', 'application', 'loop-mcp'),
        ),
        TenantScope::forTenant('account', loopReference('sifrious/zahir', 'account-scope', $tenant)),
        loopReference('sifrious/logres', 'request', 'request_01'),
    );
}

it('serializes the shared causal fixture identically through PHP and MCP reads', function (): void {
    $fixture = Fixture::load('request-lifecycle-v1');
    $plan = loopReference('sifrious/titan', 'plan', $fixture['plan']['id'], '1');
    $step = loopReference('sifrious/titan', 'plan-step', $fixture['plan']['steps'][1]['id']);
    $request = loopReference('sifrious/logres', 'execution-request', $fixture['execution_request']['id']);
    $producer = loopReference('sifrious/aleph', 'model', 'fixture-interpreter-v1');

    $resource = LoopResourceDocument::available(
        LoopResourceType::Plan,
        $plan,
        loopAuthorization(),
        AuthorizationDecision::permit('loop_read', 'loop-read-v1'),
        facts: [
            LoopResourceField::fact('current-step', $step, [$plan]),
            LoopResourceField::fact('execution-requests', [$request], [$step]),
            LoopResourceField::fact('external-identifiers', [[
                'provider' => 'linear',
                'resource_type' => 'issue',
                'id' => 'MME-2272',
                'account' => loopReference('sifrious/zahir', 'provider-account', 'linear-account-a'),
            ]]),
            LoopResourceField::unknownFact('remaining-budget'),
            LoopResourceField::redactedFact('provider-session'),
        ],
        derivations: [
            LoopResourceField::derivation(
                'permitted-next-actions',
                ['inspect', 'clarify'],
                'loop-policy-v1',
                [$plan, $step],
            ),
        ],
        interpretations: [
            LoopResourceField::interpretation(
                'summary',
                'The implementation step is ready for an authorized read.',
                $producer,
                [$plan, $step, $request],
            ),
        ],
    );

    $phpJson = $resource->toJson();
    $mcp = (new LoopMcpResourceAdapter)->read($resource);

    expect($mcp['contents'][0]['text'])->toBe($phpJson)
        ->and($mcp['contents'][0]['uri'])->toBe($resource->uri)
        ->and(json_decode($phpJson, true, 512, JSON_THROW_ON_ERROR)['reference'])->toEqual($plan->toArray());

    $schemaDirectory = dirname(__DIR__, 2).'/resources/schemas/loop-mcp';
    $resolver = new SchemaResolver;
    foreach (['cross-package-reference-v1', 'authorization-context-v1', 'resource-v1'] as $schema) {
        $resolver->registerFile(
            "https://schemas.sifrious.com/loop-mcp/{$schema}.schema.json",
            "{$schemaDirectory}/{$schema}.schema.json",
        );
    }
    $validator = new Validator(new SchemaLoader(new SchemaParser, $resolver));
    $result = $validator->validate(
        json_decode($phpJson, false, 512, JSON_THROW_ON_ERROR),
        'https://schemas.sifrious.com/loop-mcp/resource-v1.schema.json',
    );

    expect($result->isValid())->toBeTrue();
});

it('publishes every stable read template and no tool surface', function (): void {
    $adapter = new LoopMcpResourceAdapter;
    $templates = $adapter->resourceTemplates();

    expect(array_column($templates, 'name'))->toBe(array_map(
        static fn (LoopResourceType $type): string => "loop.{$type->value}",
        LoopResourceType::cases(),
    ))
        ->and($templates)->toHaveCount(17)
        ->and(method_exists($adapter, 'tools'))->toBeFalse();
});

it('conceals a cross-account resource without private references counts or completion state', function (): void {
    $fixture = Fixture::load('request-lifecycle-v1');
    $requested = loopReference('sifrious/titan', 'plan', $fixture['plan']['id']);
    $resource = LoopResourceDocument::denied(
        LoopResourceType::Plan,
        $requested,
        loopAuthorization('account_b', 'tenant_b'),
        AuthorizationDecision::deny(
            'outside_tenant_scope',
            DisclosureMode::ConcealAsMissing,
            'loop-read-v1',
        ),
    );

    $document = $resource->toArray();
    $serialized = $resource->toJson();

    expect($document['resolution'])->toBe('missing')
        ->and($document['reference'])->toBeNull()
        ->and($document['facts'])->toBe([])
        ->and($document['deterministic_derivations'])->toBe([])
        ->and($document['ai_interpretations'])->toBe([])
        ->and($serialized)->not->toContain(
            'workkit_01',
            'request_01',
            'step_implement',
            'completion',
            'visible_private_relation_count',
        );
});

it('keeps missing knowledge explicit and paths outside canonical identity', function (): void {
    $workspace = loopReference('sifrious/stacks-contract', 'workspace', 'workspace_01');
    $resource = LoopResourceDocument::available(
        LoopResourceType::WorkspaceReference,
        $workspace,
        loopAuthorization(),
        AuthorizationDecision::permit('loop_read'),
        facts: [
            LoopResourceField::fact('repository', loopReference('sifrious/stacks-contract', 'repository', 'repository_01')),
            LoopResourceField::fact('current-path', '/fixture/workspaces/checkout_01'),
            LoopResourceField::unknownFact('terminal-outcome'),
        ],
    );

    expect($resource->reference?->id)->toBe('workspace_01')
        ->and($resource->uri)->not->toContain('/fixture/workspaces/checkout_01')
        ->and($resource->facts[2]->toArray())->toMatchArray([
            'name' => 'terminal-outcome',
            'availability' => 'unknown',
            'value' => null,
        ]);
});

it('requires secret-bearing fields to be redacted before serialization', function (): void {
    expect(fn (): LoopResourceField => LoopResourceField::fact('provider', [
        'access_token' => 'must-not-serialize',
    ]))->toThrow(InvalidArgumentException::class, 'must be redacted');
});
