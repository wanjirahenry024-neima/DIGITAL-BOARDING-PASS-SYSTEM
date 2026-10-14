<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('boarding_passes', function (Blueprint $table): void {
            $table->text('verification_token')->nullable();
            $table->char('verification_token_hash', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('boarding_passes', function (Blueprint $table): void {
            $table->dropUnique(['verification_token_hash']);
            $table->dropColumn(['verification_token', 'verification_token_hash']);
        });
    }
};
