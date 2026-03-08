<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cbt_exam_answers', function (Blueprint $table) {
            $table->id('answerId');
            $table->unsignedBigInteger('attemptId');
            $table->unsignedBigInteger('examId');
            $table->unsignedBigInteger('studentId');
            $table->unsignedBigInteger('questionId');
            $table->unsignedBigInteger('selectedOptionId')->nullable();
            $table->longText('answerText')->nullable();
            $table->boolean('isCorrect')->nullable();
            $table->decimal('scoreAwarded', 10, 2)->default(0);
            $table->boolean('isFlagged')->default(false);
            $table->timestamp('answeredAt')->nullable();
            $table->timestamps();

            $table->unique(['attemptId', 'questionId']);

            $table->index('attemptId');
            $table->index('examId');
            $table->index('studentId');
            $table->index('questionId');

                $table->foreign('attemptId')->references('attemptId')->on('cbt_exam_attempts')->onDelete('cascade');
                $table->foreign('questionId')->references('questionId')->on('cbt_questions')->onDelete('cascade');
                $table->foreign('selectedOptionId')->references('optionId')->on('cbt_question_options')->onDelete('set null');
                $table->foreign('examId')->references('examId')->on('cbt_exams')->onDelete('cascade');
                $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cbt_exam_answers');
    }
};