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
        Schema::create('cbt_exam_questions', function (Blueprint $table) {
            $table->id('examQuestionId');
            $table->unsignedBigInteger('examId');
            $table->unsignedBigInteger('questionId');
            $table->integer('orderIndex')->nullable();
            $table->timestamps();

            $table->foreign('examId')->references('examId')->on('cbt_exams')->onDelete('cascade');
            $table->foreign('questionId')->references('questionId')->on('cbt_questions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_exam_questions');
    }
};
