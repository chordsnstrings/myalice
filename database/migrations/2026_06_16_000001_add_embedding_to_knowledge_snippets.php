<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores a precomputed embedding (JSON float list) per knowledge snippet so the
 * agent can rank by semantic similarity, hybrid-merged with keyword overlap.
 * Null embedding = keyword-only for that snippet (graceful fallback).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('knowledge_snippets', function (Blueprint $table) {
            $table->text('embedding')->nullable()->after('char_count');
            $table->string('embedding_model')->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('knowledge_snippets', function (Blueprint $table) {
            $table->dropColumn(['embedding', 'embedding_model']);
        });
    }
};
