<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('signature_id')->constrained('signatures')->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('deadline');
            $table->timestamp('publish_date')->nullable();
            $table->string('status')->default('draft');
            $table->boolean('is_approved')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('public_token')->nullable()->unique();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['author_id', 'status']);
            $table->index(['deadline', 'publish_date']);
            $table->index('project_id');
        });

        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('order')->default(0);
            $table->string('completion_condition')->default('all');
            $table->boolean('show_results_before_vote')->default(false);
            $table->boolean('participants_see_each_other')->default(true);
            $table->boolean('is_approved')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['agreement_id', 'order']);
        });

        Schema::create('section_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signature_id')->constrained('signatures')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['section_id', 'user_id', 'signature_id']);
        });

        Schema::create('information_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title')->nullable();
            $table->json('content')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('information_block_id')->constrained('information_blocks')->cascadeOnDelete();
            $table->string('name');
            $table->string('path');
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->timestamps();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('signature_id')->constrained('signatures')->restrictOnDelete();
            $table->string('vote');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['section_id', 'user_id', 'signature_id']);
        });

        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('user_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_group_id')->constrained('user_groups')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_group_id', 'user_id']);
        });

        Schema::create('agreement_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('filters')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('slug')->nullable();
            $table->timestamps();
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'agreement_id']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('signature_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->json('changes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['model_type', 'model_id']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('agreement_groups');
        Schema::dropIfExists('user_group_members');
        Schema::dropIfExists('user_groups');
        Schema::dropIfExists('votes');
        Schema::dropIfExists('files');
        Schema::dropIfExists('information_blocks');
        Schema::dropIfExists('section_participants');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('agreements');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('signatures');
    }
};
