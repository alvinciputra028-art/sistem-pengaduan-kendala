<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->foreignId('duplicate_of_report_id')->nullable()->after('retained_until')
                ->constrained('reports')->nullOnDelete();
            $table->timestamp('community_hidden_at')->nullable()->after('duplicate_of_report_id');
            $table->index(['building_id', 'visibility', 'community_hidden_at']);
        });

        Schema::create('report_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['report_id', 'user_id']);
        });

        Schema::create('report_moderation_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->unique(['report_id', 'reported_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_moderation_reports');
        Schema::dropIfExists('report_acknowledgements');
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropForeign(['duplicate_of_report_id']);
            $table->dropIndex(['building_id', 'visibility', 'community_hidden_at']);
            $table->dropColumn(['duplicate_of_report_id', 'community_hidden_at']);
        });
    }
};
