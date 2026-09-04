<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\LoopMcp;

/**
 * Stable MCP presentation types. These identify resource views, not new domain
 * objects; every resource still points at its owning package's canonical ID.
 */
enum LoopResourceType: string
{
    case Loop = 'loop';
    case Plan = 'plan';
    case Epic = 'epic';
    case Phase = 'phase';
    case Task = 'task';
    case Step = 'step';
    case Run = 'run';
    case Attempt = 'attempt';
    case Dependency = 'dependency';
    case Blocker = 'blocker';
    case Question = 'question';
    case Decision = 'decision';
    case VerificationPlan = 'verification-plan';
    case Evidence = 'evidence';
    case ExecutionTarget = 'execution-target';
    case WorkspaceReference = 'workspace-reference';
    case TerminalOutcome = 'terminal-outcome';
}
