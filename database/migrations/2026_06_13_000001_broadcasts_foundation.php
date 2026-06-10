<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0 of the multi-channel broadcast feature (docs/BROADCASTS_PLAN.md):
 * per-channel contact identity + consent, a provider message id on messages for
 * delivery reconciliation, and an append-only consent audit log.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Per-channel identity + consent + 24h-window state. The spine of broadcasts.
        Schema::create('contact_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel'); // whatsapp | messenger | instagram | ...
            $table->string('external_id'); // phone (WA) | PSID (Messenger) | IGSID (Instagram)
            $table->timestamp('opted_in_at')->nullable();
            $table->string('opt_in_source')->nullable();
            $table->text('opt_in_text')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->string('opt_out_reason')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamp('window_expires_at')->nullable(); // 24h session window end
            $table->timestamps();

            $table->unique(['workspace_id', 'channel', 'external_id']);
            $table->index(['workspace_id', 'contact_id']);
            $table->index(['workspace_id', 'channel', 'opted_out_at']);
        });

        // Store the provider message id so delivery/read receipts can map back.
        Schema::table('messages', function (Blueprint $table) {
            $table->string('external_id')->nullable()->after('status');
            $table->index(['workspace_id', 'external_id']);
        });

        // Append-only proof of opt-in / opt-out for compliance + audits.
        Schema::create('consent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('type'); // opt_in | opt_out
            $table->string('source')->nullable(); // inbound_keyword | broadcast | import | api | legacy
            $table->text('raw_text')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['workspace_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_events');
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'external_id']);
            $table->dropColumn('external_id');
        });
        Schema::dropIfExists('contact_channels');
    }
};
