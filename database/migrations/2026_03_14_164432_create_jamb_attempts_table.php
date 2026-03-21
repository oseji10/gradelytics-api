<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jamb_attempts', function (Blueprint $table) {
            $table->bigIncrements('attemptId');
            $table->unsignedBigInteger('studentId');

            $table->enum('mode', ['practice', 'full_simulation'])->default('practice');
            $table->enum('status', ['not_started', 'in_progress', 'paused', 'submitted', 'expired'])->default('not_started');

            $table->unsignedBigInteger('subjectId')->nullable();
            $table->unsignedBigInteger('topicId')->nullable();

            $table->unsignedInteger('durationMinutes')->nullable();
            $table->unsignedInteger('timeRemainingSeconds')->nullable();

            $table->unsignedInteger('totalQuestions')->default(0);
            $table->unsignedInteger('answeredQuestions')->default(0);
            $table->unsignedInteger('correctAnswers')->default(0);
            $table->unsignedInteger('wrongAnswers')->default(0);
            $table->unsignedInteger('unansweredQuestions')->default(0);

            $table->decimal('score', 8, 2)->default(0);
            $table->decimal('percentage', 8, 2)->default(0);

            $table->timestamp('startedAt')->nullable();
            $table->timestamp('submittedAt')->nullable();
            $table->timestamp('expiresAt')->nullable();

            $table->unsignedInteger('currentQuestionOrder')->default(1);
            $table->json('settingsJson')->nullable();

            $table->timestamps();

            $table->index(['studentId', 'status']);
            $table->index(['studentId', 'mode']);

            $table->foreign('subjectId')
                ->references('subjectId')
                ->on('jamb_subjects')
                ->nullOnDelete();

            $table->foreign('topicId')
                ->references('topicId')
                ->on('jamb_topics')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jamb_attempts');
    }
};