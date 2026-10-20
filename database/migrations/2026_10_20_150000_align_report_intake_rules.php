<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->boolean('is_sensitive')->default(false)->after('default_visibility');
        });

        Schema::table('reports', function (Blueprint $table): void {
            $table->json('assessment_answers')->nullable()->after('safety_risk_score');
            $table->string('incident_duration', 30)->nullable()->after('assessment_answers');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table): void {
            $table->dropColumn(['assessment_answers', 'incident_duration']);
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropColumn('is_sensitive');
        });
    }
};
