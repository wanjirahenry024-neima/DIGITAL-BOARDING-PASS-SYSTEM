<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_passes', function (Blueprint $table): void {
            $table->id();
            $table->string('pass_reference', 32)->unique();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('flight_id')->constrained()->restrictOnDelete();
            $table->foreignId('check_in_id')->unique()->constrained()->restrictOnDelete();
            $table->string('seat_number', 10);
            $table->string('pass_status', 20)->default('ACTIVE');
            $table->timestamp('issued_at');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->unique(['flight_id', 'seat_number']);
            $table->index(['passenger_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_passes');
    }
};
