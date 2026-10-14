<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boarding_pass_verifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('boarding_pass_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('staff_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pass_reference', 32)->nullable();
            $table->string('status', 20);
            $table->text('reason')->nullable();
            $table->timestamp('verified_at');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'verified_at']);
            $table->index(['status', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boarding_pass_verifications');
    }
};
