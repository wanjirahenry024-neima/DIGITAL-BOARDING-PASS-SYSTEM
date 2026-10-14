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
        Schema::create('boarding_pass_offline_leases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boarding_pass_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('gate_terminal_id')->constrained()->restrictOnDelete();
            $table->uuid('lease_id')->unique();
            $table->string('status', 20)->default('ACTIVE');
            $table->timestamp('offline_valid_until');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['gate_terminal_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('boarding_pass_offline_leases');
    }
};
