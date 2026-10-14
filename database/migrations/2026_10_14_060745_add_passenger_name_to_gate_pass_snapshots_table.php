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
        Schema::table('gate_pass_snapshots', function (Blueprint $table): void {
            $table->string('passenger_name')->nullable()->after('pass_reference');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('gate_pass_snapshots', function (Blueprint $table): void {
            $table->dropColumn('passenger_name');
        });
    }
};
