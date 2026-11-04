<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['initiative_id', 'name']);
        });

        Schema::create('friction_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workflow_stage_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency')->nullable();
            $table->unsignedTinyInteger('pain')->nullable();
            $table->string('affected')->nullable();
            $table->timestamps();
        });

        Schema::create('dimension_friction_point', function (Blueprint $table) {
            $table->foreignId('dimension_id')->constrained()->cascadeOnDelete();
            $table->foreignId('friction_point_id')->constrained()->cascadeOnDelete();
            $table->primary(['dimension_id', 'friction_point_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dimension_friction_point');
        Schema::dropIfExists('friction_points');
        Schema::dropIfExists('dimensions');
    }
};
