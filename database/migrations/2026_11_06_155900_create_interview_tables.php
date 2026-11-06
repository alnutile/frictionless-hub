<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('participant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('conducted_at')->nullable();
            $table->string('status')->default('draft');
            $table->text('responsibilities')->nullable();
            $table->text('tools')->nullable();
            $table->longText('transcript')->nullable();
            $table->timestamps();

            $table->unique(['initiative_id', 'participant_id']);
        });

        // Initiative-wide tags applied to individual notes; step 7 groups them into themes.
        Schema::create('codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->unique(['initiative_id', 'name']);
        });

        Schema::create('interview_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->text('body');
            $table->foreignId('workflow_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('friction_point_id')->nullable()->constrained()->nullOnDelete();
            $table->string('owner')->nullable();
            $table->boolean('done')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['interview_id', 'kind', 'position']);
        });

        Schema::create('code_interview_note', function (Blueprint $table) {
            $table->foreignId('code_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interview_note_id')->constrained()->cascadeOnDelete();
            $table->primary(['code_id', 'interview_note_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_interview_note');
        Schema::dropIfExists('interview_notes');
        Schema::dropIfExists('codes');
        Schema::dropIfExists('interviews');
    }
};
