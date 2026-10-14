<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flights', function (Blueprint $table): void {
            $table->id();
            $table->string('flight_number', 20)->unique();
            $table->string('airline');
            $table->string('departure_airport');
            $table->string('arrival_airport');
            $table->date('departure_date');
            $table->time('departure_time');
            $table->time('arrival_time');
            $table->string('aircraft')->nullable();
            $table->unsignedInteger('total_seats');
            $table->unsignedInteger('available_seats');
            $table->string('status', 20)->default('Scheduled');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['departure_date', 'status']);
            $table->index(['departure_airport', 'arrival_airport']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flights');
    }
};
