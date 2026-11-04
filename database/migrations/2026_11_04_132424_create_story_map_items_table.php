<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One self-referencing table: activities (no parent) → tasks → cards.
     */
    public function up(): void
    {
        Schema::create('story_map_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('story_map_items')->cascadeOnDelete();
            $table->string('level');
            $table->string('kind')->nullable();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['initiative_id', 'level', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_map_items');
    }
};
