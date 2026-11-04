<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position')->default(0);
            $table->text('notes')->nullable();
            $table->boolean('ai_assisted')->default(false);
            $table->timestamps();

            $table->index(['initiative_id', 'position']);
        });

        Schema::create('stage_tools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_stage_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_ai')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stage_tools');
        Schema::dropIfExists('workflow_stages');
    }
};
