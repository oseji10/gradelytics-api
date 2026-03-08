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
    Schema::create('exam_sections', function (Blueprint $table) {
    $table->id('examSectionId');
    $table->unsignedBigInteger('schoolId')->nullable();
    $table->unsignedBigInteger('examId')->nullable();
    $table->string('title')->nullable();
    $table->text('instructions')->nullable();
    $table->integer('sectionOrder')->default(1);
    $table->integer('durationMinutes')->nullable(); // optional
    $table->integer('totalMarks')->default(0);
    $table->boolean('shuffleQuestions')->default(false);
    $table->timestamps();

    $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
    $table->foreign('examId')->references('examId')->on('cbt_exams')->onDelete('cascade');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_sections');
    }
};
