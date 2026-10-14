<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table): void {
            $table->id();
            $table->string('booking_reference', 32)->unique();
            $table->foreignId('passenger_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('flight_id')->constrained()->restrictOnDelete();
            $table->string('booking_status', 20)->default('Confirmed');
            $table->timestamp('booking_date');
            $table->unsignedSmallInteger('number_of_passengers');
            $table->timestamps();
            $table->index(['passenger_id', 'booking_date']);
            $table->index(['flight_id', 'booking_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
