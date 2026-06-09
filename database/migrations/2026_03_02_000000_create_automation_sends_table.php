<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Records each automation message to a contact, for frequency capping (C-22).
        Schema::create('automation_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('automation_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();
            $table->index(['workspace_id', 'contact_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_sends');
    }
};
