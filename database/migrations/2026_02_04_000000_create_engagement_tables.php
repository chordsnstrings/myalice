<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('category')->default('marketing'); // marketing, utility, authentication
            $table->string('language', 8)->default('en');
            $table->text('body');
            $table->string('approval_status')->default('pending'); // approved, pending, rejected
            $table->string('quality')->default('green'); // green, yellow, red
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('dynamic'); // static, dynamic
            $table->json('filters')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::create('broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('message_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('audience_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('draft'); // draft, scheduled, sending, sent, paused, failed
            $table->timestamp('schedule_at')->nullable();
            $table->decimal('credit_cost', 10, 2)->default(0);
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('read')->default(0);
            $table->unsignedInteger('replied')->default(0);
            $table->timestamps();
            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
        Schema::dropIfExists('audiences');
        Schema::dropIfExists('message_templates');
    }
};
