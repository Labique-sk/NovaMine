<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('remember_token');
            $table->text('google_access_token')->nullable();
            $table->text('google_refresh_token')->nullable();
            $table->timestamp('google_connected_at')->nullable();
            $table->string('google_spreadsheet_id')->nullable();
        });

        Schema::create('source_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('adapter_class');
            $table->boolean('enabled')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('mining_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query');
            $table->json('filters')->nullable();
            $table->string('status', 32)->default('queued')->index();
            $table->string('stage')->nullable();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->unsignedInteger('total_found')->default(0);
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('verified')->default(0);
            $table->unsignedInteger('duplicates')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mining_job_id')->nullable()->constrained('mining_jobs')->nullOnDelete();
            $table->string('name');
            $table->string('designation')->nullable();
            $table->string('company')->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->string('industry')->nullable();
            $table->string('location')->nullable();
            $table->string('professional_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('profile_url', 2048)->nullable();
            $table->string('portfolio_url', 2048)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('source_name')->nullable();
            $table->string('candidate_type', 32)->nullable();
            $table->string('verification_status', 32)->default('unverified')->index();
            $table->string('email_validation_status', 32)->nullable();
            $table->string('phone_validation_status', 32)->nullable();
            $table->string('profile_validation_status', 32)->nullable();
            $table->string('duplicate_status', 32)->default('unique');
            $table->unsignedTinyInteger('match_score')->nullable()->index();
            $table->json('score_breakdown')->nullable();
            $table->boolean('is_synthetic')->default(false);
            $table->timestamps();

            $table->index('professional_email');
            $table->index('phone');
            $table->index('name');
            $table->index('location');
            $table->index('designation');
            $table->index('company');
            $table->index('created_at');
            $table->index(['user_id', 'created_at']);
            $table->index(['user_id', 'verification_status']);
            $table->index(['user_id', 'match_score']);
            $table->index(['user_id', 'location', 'designation']);
        });

        Schema::create('candidate_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_match')->default(false);
            $table->timestamps();

            $table->index(['candidate_id', 'name']);
        });

        Schema::create('candidate_education', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('institution')->nullable();
            $table->string('credential')->nullable();
            $table->string('field')->nullable();
            $table->year('year')->nullable();
            $table->timestamps();
        });

        Schema::create('candidate_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('value');
            $table->boolean('is_public')->default(true);
            $table->timestamps();
        });

        Schema::create('candidate_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('source_name');
            $table->string('source_url', 2048)->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('validation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('status', 32);
            $table->string('message')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mining_job_id')->nullable()->constrained('mining_jobs')->nullOnDelete();
            $table->string('query');
            $table->json('filters')->nullable();
            $table->unsignedInteger('result_count')->default(0);
            $table->string('status', 32)->default('completed');
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('shortlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });

        Schema::create('shortlist_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shortlist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('candidate_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shortlist_id', 'candidate_id']);
        });

        Schema::create('exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shortlist_id')->nullable()->constrained()->nullOnDelete();
            $table->string('format', 16);
            $table->unsignedInteger('record_count')->default(0);
            $table->json('filters')->nullable();
            $table->string('disk')->default('local');
            $table->string('file_path')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('action');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('exports');
        Schema::dropIfExists('shortlist_candidates');
        Schema::dropIfExists('shortlists');
        Schema::dropIfExists('searches');
        Schema::dropIfExists('validation_results');
        Schema::dropIfExists('candidate_sources');
        Schema::dropIfExists('candidate_contacts');
        Schema::dropIfExists('candidate_education');
        Schema::dropIfExists('candidate_skills');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('mining_jobs');
        Schema::dropIfExists('source_configs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'preferences',
                'google_access_token',
                'google_refresh_token',
                'google_connected_at',
                'google_spreadsheet_id',
            ]);
        });
    }
};
