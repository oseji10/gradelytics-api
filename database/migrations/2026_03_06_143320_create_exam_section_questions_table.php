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
    Schema::create('exam_section_questions', function (Blueprint $table) {
    $table->id('examSectionQuestionId');
    $table->unsignedBigInteger('examSectionId')->nullable();
    $table->unsignedBigInteger('questionId')->nullable();
    $table->integer('orderIndex')->default(1);
    $table->integer('mark')->default(1);
    $table->timestamps();

    $table->foreign('examSectionId')->references('examSectionId')->on('exam_sections')->onDelete('cascade');
    $table->foreign('questionId')->references('questionId')->on('cbt_questions')->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_section_questions');
    }
};
