<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->timestamp('community_chat_disabled_at')->nullable()->after('community_hidden_at');
            $table->foreignId('community_chat_disabled_by')->nullable()->after('community_chat_disabled_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::create('report_comment_moderation_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('comment_id')->constrained('report_comments')->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 30);
            $table->text('note')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->unique(['comment_id', 'reported_by']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_comment_moderation_reports');
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropForeign(['community_chat_disabled_by']);
            $table->dropColumn(['community_chat_disabled_at', 'community_chat_disabled_by']);
        });
    }
};
