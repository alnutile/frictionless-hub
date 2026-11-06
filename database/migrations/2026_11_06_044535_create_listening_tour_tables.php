<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('initiatives', function (Blueprint $table) {
            $table->unsignedSmallInteger('interview_target')->nullable()->after('phase');
        });

        // Groups that matter for coverage. Exactly one category per initiative is the work-area category.
        Schema::create('segment_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_area')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['initiative_id', 'name']);
        });

        Schema::create('segment_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['segment_category_id', 'name']);
        });

        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('role')->nullable();
            $table->string('status')->default('candidate');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('invite_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // At most one value per category per participant; enforced in validation.
        Schema::create('participant_segment_value', function (Blueprint $table) {
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_value_id')->constrained()->cascadeOnDelete();
            $table->primary(['participant_id', 'segment_value_id']);
        });

        Schema::create('checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('checklist_item_participant', function (Blueprint $table) {
            $table->foreignId('checklist_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at')->useCurrent();
            $table->primary(['checklist_item_id', 'participant_id']);
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('purpose')->default('invite');
            $table->string('name');
            $table->string('channel')->default('email');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('checklist_item_participant');
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('participant_segment_value');
        Schema::dropIfExists('participants');
        Schema::dropIfExists('segment_values');
        Schema::dropIfExists('segment_categories');

        Schema::table('initiatives', function (Blueprint $table) {
            $table->dropColumn('interview_target');
        });
    }
};
