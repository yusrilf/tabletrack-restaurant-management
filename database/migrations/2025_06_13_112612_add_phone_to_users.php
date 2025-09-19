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
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('email');
            $table->string('phone_code')->nullable()->after('phone_number');
        });
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('phone_code')->nullable()->after('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone_number', 'phone_code']);
        });
        Schema::table('restaurants', function (Blueprint $table) {
            $table->dropColumn('phone_code');
        });
    }

};
