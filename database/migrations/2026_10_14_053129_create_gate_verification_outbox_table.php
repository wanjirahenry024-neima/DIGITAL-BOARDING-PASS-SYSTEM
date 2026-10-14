<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'gate_terminal';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('gate_verification_outbox', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->unsignedBigInteger('boarding_pass_id')->nullable();
            $table->unsignedBigInteger('flight_id')->nullable();
            $table->unsignedBigInteger('passenger_id')->nullable();
            $table->unsignedBigInteger('staff_id')->nullable();
            $table->uuid('lease_id')->nullable();
            $table->string('verification_reference', 32)->nullable();
            $table->string('result', 20);
            $table->string('verification_method', 20)->default('OFFLINE');
            $table->string('gate', 50)->nullable();
            $table->string('device_identifier', 100)->nullable();
            $table->timestamp('verified_at');
            $table->text('notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('server_verification_record_id')->nullable();
            $table->string('sync_status', 20)->default('PENDING');
            $table->unsignedSmallInteger('sync_attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->text('last_sync_error')->nullable();
            $table->timestamps();

            $table->index(['sync_status', 'next_attempt_at']);
            $table->index(['lease_id', 'sync_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_verification_outbox');
    }
};
