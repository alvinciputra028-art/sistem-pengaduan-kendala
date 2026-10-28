<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_estimate_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->timestamp('old_estimated_response_at')->nullable();
            $table->timestamp('new_estimated_response_at')->nullable();
            $table->timestamp('old_estimated_resolution_at')->nullable();
            $table->timestamp('new_estimated_resolution_at')->nullable();
            $table->text('estimate_note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->timestamps();

            $table->index(['report_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_estimate_histories');
    }
};
