<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color')->default('neutral');
            $table->timestamps();
            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('quick_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('shortcut');
            $table->text('body');
            $table->timestamps();
            $table->index(['workspace_id', 'shortcut']);
        });

        Schema::create('business_hours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day'); // 0=Sun..6=Sat
            $table->boolean('enabled')->default(true);
            $table->string('opens_at')->default('09:00');
            $table->string('closes_at')->default('17:00');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('quick_replies');
        Schema::dropIfExists('tags');
    }
};
