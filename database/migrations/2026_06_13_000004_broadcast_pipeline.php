<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 — the real send pipeline: broadcasts gain channel/variable-mapping/
 * cost-accounting fields, and a per-recipient table makes sends idempotent,
 * resumable, and trackable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->string('channel')->default('whatsapp')->after('name');
            $table->foreignId('sending_channel_id')->nullable()->after('channel'); // which Channel (e.g. WA number) sends
            $table->json('variable_map')->nullable()->after('message_template_id'); // {{n}} -> contact field
            $table->decimal('reserved_cost', 12, 2)->default(0)->after('credit_cost');
            $table->decimal('spent_cost', 12, 2)->default(0)->after('reserved_cost');
            $table->unsignedInteger('failed')->default(0)->after('replied');
            $table->foreignId('approved_by')->nullable()->after('failed');
            $table->timestamp('started_at')->nullable()->after('schedule_at');
            $table->timestamp('completed_at')->nullable()->after('started_at');
        });

        Schema::create('broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('broadcast_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('external_id');
            $table->string('status')->default('queued'); // queued|sent|delivered|read|replied|failed|skipped
            $table->string('skip_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('error_code')->nullable();
            $table->decimal('cost', 10, 4)->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();

            $table->unique(['broadcast_id', 'contact_id']); // exactly-once per contact
            $table->index(['broadcast_id', 'status']);
            $table->index(['workspace_id', 'provider_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_recipients');
        Schema::table('broadcasts', function (Blueprint $table) {
            $table->dropColumn(['channel', 'sending_channel_id', 'variable_map', 'reserved_cost', 'spent_cost', 'failed', 'approved_by', 'started_at', 'completed_at']);
        });
    }
};
