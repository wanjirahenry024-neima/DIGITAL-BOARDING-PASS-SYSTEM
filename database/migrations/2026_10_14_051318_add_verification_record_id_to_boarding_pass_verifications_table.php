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
        Schema::table('boarding_pass_verifications', function (Blueprint $table): void {
            $table->foreignId('verification_record_id')
                ->nullable()
                ->after('boarding_pass_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boarding_pass_verifications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verification_record_id');
        });
    }
};
