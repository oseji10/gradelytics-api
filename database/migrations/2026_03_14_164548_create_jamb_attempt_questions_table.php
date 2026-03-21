<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jamb_attempt_questions', function (Blueprint $table) {
            $table->bigIncrements('attemptQuestionId');
            $table->unsignedBigInteger('attemptId');
            $table->unsignedBigInteger('subjectId');
            $table->unsignedBigInteger('questionId');

            $table->unsignedInteger('questionOrder');
            $table->decimal('allocatedMark', 8, 2)->default(1);

            $table->boolean('isAnswered')->default(false);
            $table->boolean('isCorrect')->default(false);
            $table->enum('selectedOption', ['A', 'B', 'C', 'D'])->nullable();
            $table->boolean('isFlagged')->default(false);

            $table->unsignedInteger('timeSpentSeconds')->default(0);
            $table->timestamp('answeredAt')->nullable();

            $table->timestamps();

            $table->foreign('attemptId')
                ->references('attemptId')
                ->on('jamb_attempts')
                ->cascadeOnDelete();

            $table->foreign('subjectId')
                ->references('subjectId')
                ->on('jamb_subjects')
                ->cascadeOnDelete();

            $table->foreign('questionId')
                ->references('questionId')
                ->on('jamb_questions')
                ->cascadeOnDelete();

            $table->unique(['attemptId', 'questionOrder']);
            $table->unique(['attemptId', 'questionId']);
            $table->index(['attemptId', 'isAnswered']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jamb_attempt_questions');
    }
};