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
        Schema::create('thermal_printers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('device_address')->nullable(); // Bluetooth MAC address or network address
            $table->enum('connection_type', ['bluetooth', 'network', 'usb', 'web_bluetooth'])
                  ->default('bluetooth');
            $table->enum('paper_size', ['58mm', '80mm'])->default('80mm');
            $table->json('settings')->nullable(); // Printer-specific settings
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['restaurant_id', 'is_active']);
            $table->index(['restaurant_id', 'is_default']);
            $table->index(['connection_type', 'is_active']);
            $table->index('device_address');
            
            // Unique constraint for default printer per restaurant
            $table->unique(['restaurant_id', 'is_default'], 'unique_default_printer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thermal_printers');
    }
};