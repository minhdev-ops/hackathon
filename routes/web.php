<?php

use App\Http\Controllers\Auth\LoginController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// SPA shell cho các trang React (client-side router).
Route::get('/vault', fn () => view('welcome'))
    ->middleware(['auth', 'role:owner'])
    ->name('vault');

Route::get('/playbooks', fn () => view('welcome'))
    ->middleware(['auth', 'role:owner,admin'])
    ->name('playbooks');

Route::get('/tasks/new', fn () => view('welcome'))
    ->middleware(['auth', 'role:owner'])
    ->name('tasks.new');

Route::get('/tasks/{task}', fn () => view('welcome'))
    ->middleware(['auth', 'role:owner,admin'])
    ->name('tasks.show');

Route::get('/grants/{grant}', fn () => view('welcome'))
    ->middleware(['auth', 'role:owner'])
    ->name('grants.show');

Route::get('/agent-console', fn () => view('welcome'))
    ->middleware(['auth', 'role:owner,admin'])
    ->name('agent.console');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::get('/admin/quorum', fn () => view('admin.quorum'))
    ->middleware(['auth', 'role:admin'])
    ->name('admin.quorum');

Route::get('/admin/evil', fn () => view('admin.evil'))
    ->middleware(['auth', 'role:admin', 'demo.enabled'])
    ->name('admin.evil');

Route::get('/timeline', fn () => view('timeline'))
    ->middleware(['auth', 'role:owner,admin'])
    ->name('timeline');
