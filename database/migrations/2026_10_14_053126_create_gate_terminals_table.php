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
        Schema::create('gate_terminals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('terminal_id')->unique();
            $table->string('name', 100);
            $table->string('gate', 50)->nullable();
            $table->string('api_token_hash', 64)->unique();
            $table->text('api_token');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_terminals');
    }
};
