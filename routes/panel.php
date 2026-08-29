<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Maryeperry\Bindle\Http\Controllers\PanelController;

/*
| Bindle admin panel routes. Registered by BindleServiceProvider only when
| APP_ENV is "local" and bindle.panel.enabled is true, inside a group that
| applies the configured middleware plus EnsureLocalAndEnabled. The group also
| sets the "bindle.panel." name prefix and URL prefix.
*/

Route::get('/', [PanelController::class, 'index'])->name('index');
Route::post('/scan', [PanelController::class, 'scanAll'])->name('scan-all');
Route::post('/scan/page', [PanelController::class, 'scanPage'])->name('scan-page');
Route::post('/install', [PanelController::class, 'install'])->name('install');
Route::get('/wireframe-board', [PanelController::class, 'wireframeBoard'])->name('wireframe-board');

// {run} is a plain int id, NOT route-model-bound — the Run model lives on the
// dedicated `bindle` connection, which isn't registered until the controller
// calls ensureSchema().
Route::get('/status/{run}', [PanelController::class, 'status'])
    ->where('run', '[0-9]+')
    ->name('status');
Route::get('/status', [PanelController::class, 'latestStatus'])->name('latest-status');
