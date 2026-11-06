<?php

use App\Http\Controllers\Admin\AiSettingsController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\InitiativeController;
use App\Http\Controllers\Initiatives\ChecklistItemController;
use App\Http\Controllers\Initiatives\DimensionController;
use App\Http\Controllers\Initiatives\FrictionPointController;
use App\Http\Controllers\Initiatives\ListeningTourController;
use App\Http\Controllers\Initiatives\MessageTemplateController;
use App\Http\Controllers\Initiatives\ParticipantController;
use App\Http\Controllers\Initiatives\PlannedActivityController;
use App\Http\Controllers\Initiatives\RatingController;
use App\Http\Controllers\Initiatives\SegmentController;
use App\Http\Controllers\Initiatives\StageToolController;
use App\Http\Controllers\Initiatives\StoryMapController;
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
        Route::get('planned/{key}', [PlannedActivityController::class, 'show'])->name('planned');

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

        Route::get('story-map', [StoryMapController::class, 'index'])->name('story-map');
        Route::post('story-map', [StoryMapController::class, 'store'])->name('story-map.store');
        Route::patch('story-map/{storyMapItem}', [StoryMapController::class, 'update'])->name('story-map.update');
        Route::delete('story-map/{storyMapItem}', [StoryMapController::class, 'destroy'])->name('story-map.destroy');
        Route::post('story-map/{storyMapItem}/move', [StoryMapController::class, 'move'])->name('story-map.move');

        Route::get('ratings', [RatingController::class, 'index'])->name('ratings');
        Route::post('rating-groups', [RatingController::class, 'storeGroup'])->name('rating-groups.store');
        Route::patch('rating-groups/{ratingGroup}', [RatingController::class, 'updateGroup'])->name('rating-groups.update');
        Route::delete('rating-groups/{ratingGroup}', [RatingController::class, 'destroyGroup'])->name('rating-groups.destroy');
        Route::post('rating-groups/{ratingGroup}/items', [RatingController::class, 'storeItem'])->name('rating-items.store');
        Route::delete('rating-items/{ratingItem}', [RatingController::class, 'destroyItem'])->name('rating-items.destroy');
        Route::put('rating-items/{ratingItem}/rating', [RatingController::class, 'rate'])->name('rating-items.rate');

        Route::get('listening-tour', [ListeningTourController::class, 'index'])->name('listening-tour');
        Route::put('listening-tour/target', [ListeningTourController::class, 'updateTarget'])->name('listening-tour.target');
        Route::post('segment-categories', [SegmentController::class, 'storeCategory'])->name('segment-categories.store');
        Route::delete('segment-categories/{segmentCategory}', [SegmentController::class, 'destroyCategory'])->name('segment-categories.destroy');
        Route::post('segment-categories/{segmentCategory}/values', [SegmentController::class, 'storeValue'])->name('segment-values.store');
        Route::delete('segment-values/{segmentValue}', [SegmentController::class, 'destroyValue'])->name('segment-values.destroy');
        Route::post('participants', [ParticipantController::class, 'store'])->name('participants.store');
        Route::patch('participants/{participant}', [ParticipantController::class, 'update'])->name('participants.update');
        Route::delete('participants/{participant}', [ParticipantController::class, 'destroy'])->name('participants.destroy');
        Route::post('participants/{participant}/checklist/{checklistItem}', [ParticipantController::class, 'toggleChecklistItem'])->withoutScopedBindings()->name('participants.checklist');
        Route::post('checklist-items', [ChecklistItemController::class, 'store'])->name('checklist-items.store');
        Route::delete('checklist-items/{checklistItem}', [ChecklistItemController::class, 'destroy'])->name('checklist-items.destroy');
        Route::post('message-templates', [MessageTemplateController::class, 'store'])->name('message-templates.store');
        Route::patch('message-templates/{messageTemplate}', [MessageTemplateController::class, 'update'])->name('message-templates.update');
        Route::delete('message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy'])->name('message-templates.destroy');

        Route::put('notes/{key}', [GuideController::class, 'updateNote'])->name('notes.update');

        Route::post('assistant', [AssistantController::class, 'store'])->middleware('throttle:assistant')->name('assistant.store');
        Route::delete('assistant', [AssistantController::class, 'destroy'])->name('assistant.destroy');
    });

    Route::post('assistant', [AssistantController::class, 'store'])->middleware('throttle:assistant')->name('assistant.store');
    Route::delete('assistant', [AssistantController::class, 'destroy'])->name('assistant.destroy');

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('guide/{key}', [GuideController::class, 'update'])->name('guide.update');
        Route::delete('guide/{key}', [GuideController::class, 'destroy'])->name('guide.destroy');
        Route::get('ai', [AiSettingsController::class, 'edit'])->name('ai.edit');
        Route::put('ai', [AiSettingsController::class, 'update'])->name('ai.update');
        Route::post('ai/test', [AiSettingsController::class, 'test'])->middleware('throttle:assistant')->name('ai.test');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
