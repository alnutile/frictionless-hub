<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One private assistant conversation per user per initiative
     * (initiative_id is null for the conversation on the initiatives list).
     */
    public function up(): void
    {
        Schema::create('assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('initiative_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->string('activity_key')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'initiative_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_messages');
    }
};
