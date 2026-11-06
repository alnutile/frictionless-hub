<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('summary')->nullable();
            $table->string('impact')->nullable();
            $table->string('effort')->nullable();
            $table->text('solutions')->nullable();
            $table->foreignId('friction_point_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        // A code belongs to at most one theme, so "codes not in a theme" is a clear, countable list.
        Schema::table('codes', function (Blueprint $table) {
            $table->foreignId('theme_id')->nullable()->after('initiative_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('codes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('theme_id');
        });

        Schema::dropIfExists('themes');
    }
};
