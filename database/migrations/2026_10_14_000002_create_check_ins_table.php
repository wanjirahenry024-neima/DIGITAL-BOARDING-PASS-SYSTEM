<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('check_ins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('booking_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('passenger_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('flight_id')->constrained()->restrictOnDelete();
            $table->string('check_in_reference', 32)->unique();
            $table->timestamp('checked_in_at');
            $table->string('seat_number', 10);
            $table->string('status', 20)->default('Checked In');
            $table->timestamps();

            $table->unique(['flight_id', 'seat_number']);
            $table->index(['passenger_id', 'checked_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('check_ins');
    }
};
