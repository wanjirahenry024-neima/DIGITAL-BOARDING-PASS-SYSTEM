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
        Schema::create('gate_pass_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('boarding_pass_id');
            $table->string('pass_reference', 32)->unique();
            $table->string('verification_token_hash', 64);
            $table->string('pass_status', 20);
            $table->string('booking_status', 30);
            $table->unsignedBigInteger('flight_id');
            $table->string('flight_status', 30);
            $table->boolean('flight_deleted')->default(false);
            $table->string('flight_number', 32);
            $table->string('destination', 10);
            $table->string('seat_number', 10);
            $table->timestamp('departure_at');
            $table->timestamp('expires_at');
            $table->uuid('lease_id');
            $table->timestamp('offline_valid_until');
            $table->string('snapshot_signature', 64);
            $table->boolean('is_enabled')->default(true);
            $table->timestamp('locally_used_at')->nullable();
            $table->timestamps();

            $table->index(['is_enabled', 'offline_valid_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_pass_snapshots');
    }
};
