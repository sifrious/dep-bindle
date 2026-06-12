<?php

declare(strict_types=1);

namespace Maryeperry\Bindle\Storage\Database;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

final class DatabaseManager
{
    public function __construct(
        private readonly ConnectionFactory $factory,
        private readonly Repository $config,
    ) {}

    public function ensureSchema(): void
    {
        $this->factory->register();

        $schema = $this->factory->connection()->getSchemaBuilder();

        $this->createRunsTable($schema);
        $this->createPagesTable($schema);
        $this->createComponentsTable($schema);
        $this->createPropsTable($schema);
        $this->createPageComponentsTable($schema);
        $this->createScreenshotsTable($schema);
        $this->createComponentVariantsTable($schema);
        $this->createErrorsTable($schema);
    }

    public function connection(): Connection
    {
        return $this->factory->connection();
    }

    public function truncateAll(): void
    {
        $tables = [
            'errors', 'screenshots', 'component_variants',
            'page_components', 'props', 'components', 'pages', 'runs',
        ];

        $conn = $this->factory->connection();
        foreach ($tables as $t) {
            if ($conn->getSchemaBuilder()->hasTable($t)) {
                $conn->table($t)->truncate();
            }
        }
    }

    private function createRunsTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('runs')) {
            return;
        }
        $schema->create('runs', function (Blueprint $t): void {
            $t->id();
            $t->timestamp('started_at')->useCurrent();
            $t->timestamp('finished_at')->nullable();
            $t->string('environment', 64);
            $t->string('git_sha', 64)->nullable();
            $t->string('status', 32)->default('running');
            $t->string('bindle_version', 32);
        });
    }

    private function createPagesTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('pages')) {
            return;
        }
        $schema->create('pages', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $t->string('route_name')->nullable();
            $t->string('uri');
            $t->string('http_method', 16);
            $t->string('slug');
            $t->string('controller')->nullable();
            $t->string('view')->nullable();
            $t->string('framework', 32);
            $t->string('html_hash', 64)->nullable();
            $t->index(['run_id', 'slug']);
        });
    }

    private function createComponentsTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('components')) {
            return;
        }
        $schema->create('components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('run_id')->constrained('runs')->cascadeOnDelete();
            $t->string('name');
            $t->string('slug');
            $t->string('kind', 32);
            $t->string('source_path')->nullable();
            $t->string('signature_hash', 64)->nullable();
            $t->index(['run_id', 'slug']);
            $t->unique(['run_id', 'kind', 'name']);
        });
    }

    private function createPropsTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('props')) {
            return;
        }
        $schema->create('props', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('component_id')->constrained('components')->cascadeOnDelete();
            $t->string('name');
            $t->string('type')->nullable();
            $t->text('default_value')->nullable();
            $t->boolean('required')->default(false);
            $t->string('source', 32);
            $t->text('description_phrase')->nullable();
            $t->unique(['component_id', 'name']);
        });
    }

    private function createPageComponentsTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('page_components')) {
            return;
        }
        $schema->create('page_components', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $t->foreignId('component_id')->constrained('components')->cascadeOnDelete();
            $t->unsignedBigInteger('parent_component_id')->nullable();
            $t->unsignedInteger('depth')->default(0);
            $t->json('props_passed')->nullable();
            $t->index(['page_id', 'component_id']);
        });
    }

    private function createScreenshotsTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('screenshots')) {
            return;
        }
        $schema->create('screenshots', function (Blueprint $t): void {
            $t->id();
            $t->string('subject_type', 32);
            $t->unsignedBigInteger('subject_id');
            $t->unsignedInteger('viewport_w');
            $t->unsignedInteger('viewport_h');
            $t->string('viewport_label', 32)->nullable();
            $t->string('path');
            $t->timestamp('taken_at')->useCurrent();
            $t->index(['subject_type', 'subject_id']);
        });
    }

    private function createComponentVariantsTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('component_variants')) {
            return;
        }
        $schema->create('component_variants', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('component_id')->constrained('components')->cascadeOnDelete();
            $t->string('variant_name');
            $t->json('props_combo');
        });
    }

    private function createErrorsTable(SchemaBuilder $schema): void
    {
        if ($schema->hasTable('errors')) {
            return;
        }
        $schema->create('errors', function (Blueprint $t): void {
            $t->id();
            $t->foreignId('run_id')->nullable()->constrained('runs')->nullOnDelete();
            $t->string('phase', 32);
            $t->string('subject_type', 32)->nullable();
            $t->unsignedBigInteger('subject_id')->nullable();
            $t->string('severity', 16)->default('warn');
            $t->text('message');
            $t->json('context')->nullable();
            $t->timestamp('occurred_at')->useCurrent();
            $t->index(['run_id', 'severity']);
        });
    }
}
