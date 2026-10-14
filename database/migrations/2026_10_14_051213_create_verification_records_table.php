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
        Schema::create('verification_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boarding_pass_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('passenger_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('flight_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->string('verification_reference', 32)->nullable();
            $table->string('result', 20);
            $table->string('verification_method', 20)->default('ONLINE');
            $table->string('gate', 50)->nullable();
            $table->string('device_identifier', 100)->nullable();
            $table->timestamp('verified_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'verified_at']);
            $table->index(['result', 'verified_at']);
            $table->index('verification_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('verification_records');
    }
};
