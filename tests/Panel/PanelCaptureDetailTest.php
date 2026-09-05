<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maryeperry\Bindle\Storage\Database\DatabaseManager;
use Maryeperry\Bindle\Storage\Models\Component;
use Maryeperry\Bindle\Storage\Models\ComponentVariant;
use Maryeperry\Bindle\Storage\Models\ErrorLog;
use Maryeperry\Bindle\Storage\Models\Page;
use Maryeperry\Bindle\Storage\Models\PageComponent;
use Maryeperry\Bindle\Storage\Models\Prop;
use Maryeperry\Bindle\Storage\Models\Run;
use Maryeperry\Bindle\Storage\Models\Screenshot;

beforeEach(function (): void {
    $this->dbPath = tempnam(sys_get_temp_dir(), 'bindle-panel-capture-').'.sqlite';
    $this->outputPath = sys_get_temp_dir().'/bindle-output-'.uniqid();
    config()->set('bindle.database_path', $this->dbPath);
    config()->set('bindle.output_path', $this->outputPath);

    Route::get('/hello', fn () => 'Hello World')->name('hello');
});

afterEach(function (): void {
    if (isset($this->dbPath) && is_file($this->dbPath)) {
        @unlink($this->dbPath);
    }
});

it('links to page capture detail from inventory and renders capture fields', function (): void {
    app(DatabaseManager::class)->ensureSchema();

    $run = Run::create([
        'environment' => 'local',
        'status' => 'completed',
        'bindle_version' => 'test',
        'driver' => 'dusk',
    ]);

    $page = Page::create([
        'run_id' => $run->id,
        'route_name' => 'hello',
        'uri' => 'hello',
        'http_method' => 'GET',
        'slug' => 'hello',
        'framework' => 'blade',
        'html_hash' => 'abc123',
    ]);

    $component = Component::create([
        'run_id' => $run->id,
        'name' => 'hero-card',
        'slug' => 'blade-hero-card',
        'kind' => 'blade',
    ]);
    Prop::create([
        'component_id' => $component->id,
        'name' => 'title',
        'type' => 'string',
        'required' => true,
        'source' => 'blade',
    ]);
    ComponentVariant::create([
        'component_id' => $component->id,
        'variant_name' => 'default',
        'props_combo' => ['title' => 'Welcome'],
    ]);
    PageComponent::create([
        'page_id' => $page->id,
        'component_id' => $component->id,
        'depth' => 0,
        'props_passed' => ['title' => 'Landing'],
    ]);
    ErrorLog::create([
        'run_id' => $run->id,
        'phase' => 'render',
        'severity' => 'warn',
        'subject_type' => 'page',
        'subject_id' => $page->id,
        'message' => 'Route returned HTTP 500.',
    ]);

    @mkdir($this->outputPath.'/pages/hello', 0o755, true);
    file_put_contents(
        $this->outputPath.'/pages/hello/hello-description.md',
        "# Page description: `hello`\n\nThe page has a simple header and card layout."
    );

    $desktopPath = tempnam(sys_get_temp_dir(), 'bindle-shot-').'.png';
    $mobilePath = tempnam(sys_get_temp_dir(), 'bindle-shot-').'.png';
    file_put_contents($desktopPath, 'desktop');
    file_put_contents($mobilePath, 'mobile');

    Screenshot::create([
        'subject_type' => 'page',
        'subject_id' => $page->id,
        'viewport_w' => 1440,
        'viewport_h' => 900,
        'viewport_label' => 'desktop',
        'path' => $desktopPath,
    ]);
    Screenshot::create([
        'subject_type' => 'page',
        'subject_id' => $page->id,
        'viewport_w' => 390,
        'viewport_h' => 844,
        'viewport_label' => 'mobile',
        'path' => $mobilePath,
    ]);

    $this->get('/_bindle')
        ->assertOk()
        ->assertSee(route('bindle.panel.captures.page', ['page' => $page->id]), false);

    $this->get(route('bindle.panel.captures.page', ['page' => $page->id]))
        ->assertOk()
        ->assertSee('Capture detail')
        ->assertSee('Screenshots')
        ->assertSee('Variants &amp; props', false)
        ->assertSee('DOM / semantic snippet')
        ->assertSee('Accessibility notes')
        ->assertSee('Scan errors')
        ->assertSee('hero-card')
        ->assertSee('default')
        ->assertSee('Route returned HTTP 500.');
});

it('labels placeholder runs so they cannot look like real screenshots', function (): void {
    app(DatabaseManager::class)->ensureSchema();

    $run = Run::create([
        'environment' => 'local',
        'status' => 'completed',
        'bindle_version' => 'test',
        'driver' => 'null',
    ]);

    $page = Page::create([
        'run_id' => $run->id,
        'route_name' => 'hello',
        'uri' => 'hello',
        'http_method' => 'GET',
        'slug' => 'hello',
        'framework' => 'blade',
    ]);

    $placeholderPng = tempnam(sys_get_temp_dir(), 'bindle-null-shot-').'.png';
    file_put_contents($placeholderPng, 'x');

    Screenshot::create([
        'subject_type' => 'page',
        'subject_id' => $page->id,
        'viewport_w' => 1440,
        'viewport_h' => 900,
        'viewport_label' => 'desktop',
        'path' => $placeholderPng,
    ]);

    $this->get(route('bindle.panel.captures.page', ['page' => $page->id]))
        ->assertOk()
        ->assertSee('Placeholder capture.')
        ->assertSee('Placeholder screenshot')
        ->assertDontSee('<img', false);
});

it('streams a real screenshot file on the capture image route', function (): void {
    app(DatabaseManager::class)->ensureSchema();

    $run = Run::create([
        'environment' => 'local',
        'status' => 'completed',
        'bindle_version' => 'test',
        'driver' => 'dusk',
    ]);

    $page = Page::create([
        'run_id' => $run->id,
        'route_name' => 'hello',
        'uri' => 'hello',
        'http_method' => 'GET',
        'slug' => 'hello',
        'framework' => 'blade',
    ]);

    $shotPath = tempnam(sys_get_temp_dir(), 'bindle-real-shot-').'.png';
    file_put_contents($shotPath, 'real-shot');

    $shot = Screenshot::create([
        'subject_type' => 'page',
        'subject_id' => $page->id,
        'viewport_w' => 1440,
        'viewport_h' => 900,
        'viewport_label' => 'desktop',
        'path' => $shotPath,
    ]);

    $this->get(route('bindle.panel.captures.page', ['page' => $page->id]))
        ->assertOk()
        ->assertSee(route('bindle.panel.captures.screenshot', ['screenshot' => $shot->id]), false)
        ->assertDontSee('Placeholder screenshot');

    $this->get(route('bindle.panel.captures.screenshot', ['screenshot' => $shot->id]))
        ->assertOk()
        ->assertSee('real-shot');
});
