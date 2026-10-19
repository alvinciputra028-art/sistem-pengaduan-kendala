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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 40)->unique();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('building_id')->constrained()->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('location_type', 20);
            $table->string('location_description')->nullable();
            $table->string('visibility', 30)->default('private')->index();
            $table->string('identity_visibility', 30)->default('visible');
            $table->unsignedTinyInteger('impact_score')->default(1);
            $table->unsignedTinyInteger('urgency_score')->default(1);
            $table->unsignedTinyInteger('safety_risk_score')->default(0);
            $table->string('priority', 20)->default('normal')->index();
            $table->unsignedSmallInteger('priority_score')->default(0);
            $table->string('status', 30)->default('submitted')->index();
            $table->timestamp('estimated_response_at')->nullable();
            $table->timestamp('estimated_resolution_at')->nullable();
            $table->text('estimate_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['building_id', 'visibility']);
            $table->index(['reporter_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
