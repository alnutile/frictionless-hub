<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('initiatives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('scope')->nullable();
            $table->text('description')->nullable();
            $table->string('phase')->default('discover');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('initiatives');
    }
};
