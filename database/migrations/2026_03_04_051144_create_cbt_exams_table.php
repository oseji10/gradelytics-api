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
        Schema::create('cbt_exams', function (Blueprint $table) {
            $table->id('examId');
                $table->unsignedBigInteger('schoolId');
                $table->unsignedBigInteger('academicYearId');   
                $table->unsignedBigInteger('termId');
                $table->unsignedBigInteger('classId');
                $table->unsignedBigInteger('subjectId');
                $table->string('title');
                $table->text('instructions')->nullable();
                $table->integer('durationMinutes');
                $table->dateTime('startsAt');
                $table->dateTime('endsAt');
                $table->integer('totalMarks');
                $table->boolean('shuffleQuestions')->default(false);
                $table->boolean('shuffleOptions')->default(false);
                $table->integer('attemptLimit')->default(1);
                $table->boolean('isPublished')->default(false);
                $table->string('scoreMode')->default('practice'); // practice or graded
                $table->string('resultComponent')->default('custom'); // ca, exam, custom

                $table->unsignedBigInteger('createdBy');
            $table->timestamps();

            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->foreign('academicYearId')->references('academicYearId')->on('academic_years')->onDelete('cascade');
            $table->foreign('termId')->references('termId')->on('terms')->onDelete('cascade');
            $table->foreign('classId')->references('classId')->on('classes')->onDelete('cascade');
            $table->foreign('subjectId')->references('subjectId')->on('subjects')->onDelete('cascade');
            $table->foreign('createdBy')->references('id')->on('users')->onDelete('cascade');
        });
    }



    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_exams');
    }
};
