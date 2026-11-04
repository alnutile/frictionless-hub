<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\InitiativeController;
use App\Http\Controllers\Initiatives\DimensionController;
use App\Http\Controllers\Initiatives\FrictionPointController;
use App\Http\Controllers\Initiatives\StageToolController;
use App\Http\Controllers\Initiatives\WorkflowStageController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', 'initiatives')->name('dashboard');

    Route::resource('initiatives', InitiativeController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

    Route::prefix('initiatives/{initiative}')->name('initiatives.')->scopeBindings()->group(function () {
        Route::get('workflow', [WorkflowStageController::class, 'index'])->name('workflow');
        Route::post('stages', [WorkflowStageController::class, 'store'])->name('stages.store');
        Route::patch('stages/{stage}', [WorkflowStageController::class, 'update'])->name('stages.update');
        Route::delete('stages/{stage}', [WorkflowStageController::class, 'destroy'])->name('stages.destroy');
        Route::post('stages/{stage}/move', [WorkflowStageController::class, 'move'])->name('stages.move');

        Route::post('stages/{stage}/tools', [StageToolController::class, 'store'])->name('tools.store');
        Route::delete('stages/{stage}/tools/{tool}', [StageToolController::class, 'destroy'])->name('tools.destroy');

        Route::get('friction', [FrictionPointController::class, 'index'])->name('friction');
        Route::post('friction', [FrictionPointController::class, 'store'])->name('friction.store');
        Route::patch('friction/{frictionPoint}', [FrictionPointController::class, 'update'])->name('friction.update');
        Route::delete('friction/{frictionPoint}', [FrictionPointController::class, 'destroy'])->name('friction.destroy');

        Route::post('dimensions', [DimensionController::class, 'store'])->name('dimensions.store');
        Route::patch('dimensions/{dimension}', [DimensionController::class, 'update'])->name('dimensions.update');
        Route::delete('dimensions/{dimension}', [DimensionController::class, 'destroy'])->name('dimensions.destroy');
    });

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
