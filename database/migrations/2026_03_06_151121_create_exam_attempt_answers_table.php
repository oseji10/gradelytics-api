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
    Schema::create('exam_attempt_answers', function (Blueprint $table) {
    $table->id('examAttemptAnswerId');
    $table->unsignedBigInteger('examAttemptId');
    $table->unsignedBigInteger('questionId');
    $table->unsignedBigInteger('studentId');
    $table->json('selectedOptionIds')->nullable(); // for objective
    $table->longText('theoryAnswer')->nullable();  // for theory
    $table->boolean('isCorrect')->nullable();
    $table->decimal('awardedScore', 8, 2)->default(0);
    $table->timestamp('answeredAt')->nullable();
    $table->timestamps();

    $table->foreign('examAttemptId')->references('attemptId')->on('cbt_exam_attempts')->onDelete('cascade');
    $table->foreign('questionId')->references('questionId')->on('cbt_questions')->onDelete('cascade');
    $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_answers');
    }
};
