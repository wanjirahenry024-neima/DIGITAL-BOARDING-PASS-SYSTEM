<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('verification_records', function (Blueprint $table): void {
            $table->uuid('sync_id')->nullable()->unique();
            $table->string('sync_status', 20)->default('SYNCED');
            $table->timestamp('approved_at')->nullable();
            $table->index(['verification_method', 'verified_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('verification_records', function (Blueprint $table): void {
            $table->dropIndex(['verification_method', 'verified_at']);
            $table->dropUnique(['sync_id']);
            $table->dropColumn(['sync_id', 'sync_status', 'approved_at']);
        });
    }
};
