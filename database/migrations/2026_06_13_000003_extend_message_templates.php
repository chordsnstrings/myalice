<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 — bring message_templates up to Meta's HSM shape: structured
 * components (header/body/footer/buttons), variables + examples, the Meta
 * template id, and richer status. `approval_status` now also carries
 * draft | paused | disabled alongside pending | approved | rejected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->string('meta_template_id')->nullable()->after('name');
            $table->json('components')->nullable()->after('body');         // normalized header/body/footer/buttons
            $table->unsignedSmallInteger('variable_count')->default(0)->after('components');
            $table->json('variable_samples')->nullable()->after('variable_count');
            $table->string('header_format')->nullable()->after('variable_samples'); // none|text|image|video|document
            $table->string('header_media_url')->nullable()->after('header_format');
        });
    }

    public function down(): void
    {
        Schema::table('message_templates', function (Blueprint $table) {
            $table->dropColumn(['meta_template_id', 'components', 'variable_count', 'variable_samples', 'header_format', 'header_media_url']);
        });
    }
};
