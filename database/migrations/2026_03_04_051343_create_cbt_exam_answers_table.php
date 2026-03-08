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
        // Schema::create('cbt_exam_answers', function (Blueprint $table) {
        //     $table->id('answerId');
        //     $table->unsignedBigInteger('attemptId');
        //     $table->unsignedBigInteger('questionId');
        //     $table->json('selectedOptionIds')->nullable();
        //     $table->text('answerText')->nullable();
        //     $table->boolean('isCorrect')->default(false);
        //     $table->integer('markAwarded')->default(0);
        //     $table->timestamps();

        //     $table->foreign('attemptId')->references('attemptId')->on('cbt_exam_attempts')->onDelete('cascade');
        //     $table->foreign('questionId')->references('questionId')->on('cbt_questions')->onDelete('cascade');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_exam_answers');
    }
};
