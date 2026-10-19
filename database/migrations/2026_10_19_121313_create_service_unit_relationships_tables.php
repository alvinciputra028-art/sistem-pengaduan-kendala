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
        Schema::create('service_unit_building', function (Blueprint $table) {
            $table->foreignId('service_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->primary(['service_unit_id', 'building_id']);
        });

        Schema::create('user_service_unit', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_unit_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'service_unit_id']);
        });

        Schema::create('user_building', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('building_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'building_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_building');
        Schema::dropIfExists('user_service_unit');
        Schema::dropIfExists('service_unit_building');
    }
};
