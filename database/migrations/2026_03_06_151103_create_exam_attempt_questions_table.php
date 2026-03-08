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
    Schema::create('exam_attempt_questions', function (Blueprint $table) {
    $table->id('examAttemptQuestionId');
    $table->unsignedBigInteger('schoolId')->nullable();
    $table->unsignedBigInteger('studentId')->nullable();
    $table->unsignedBigInteger('examId')->nullable();
    $table->unsignedBigInteger('examAttemptId')->nullable();
    $table->unsignedBigInteger('questionId')->nullable();
    $table->unsignedBigInteger('examSectionId')->nullable();
    $table->integer('orderIndex')->default(1);
    $table->integer('mark')->default(1);
    $table->text('questionSnapshot')->nullable(); // optional JSON copy
    $table->timestamps();

    $table->foreign('examAttemptId')->references('attemptId')->on('cbt_exam_attempts')->onDelete('cascade');
    $table->foreign('questionId')->references('questionId')->on('cbt_questions')->onDelete('cascade');
    $table->foreign('examSectionId')->references('examSectionId')->on('exam_sections')->onDelete('set null');
    $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade');
    $table->foreign('examId')->references('examId')->on('cbt_exams')->onDelete('cascade');
    $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attempt_questions');
    }
};
