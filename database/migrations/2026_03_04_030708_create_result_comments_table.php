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
        Schema::create('result_comments', function (Blueprint $table) {
            $table->id('commentId');
            $table->unsignedBigInteger('studentId');
            // $table->unsignedBigInteger('teacherId');
            $table->unsignedBigInteger('schoolId');
            $table->unsignedBigInteger('classId');
            $table->unsignedBigInteger('termId');
            $table->unsignedBigInteger('academicYearId');
            $table->string('commentType'); 
            $table->unsignedBigInteger('commentedBy');
            $table->text('comment');
            $table->timestamps();

            $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade');
            // $table->foreign('teacherId')->references('teacherId')->on('teachers')->onDelete('cascade');
            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->foreign('classId')->references('classId')->on('classes')->onDelete('cascade');
            $table->foreign('termId')->references('termId')->on('terms')->onDelete('cascade');
            $table->foreign('academicYearId')->references('academicYearId')->on('academic_years')->onDelete('cascade');
            $table->foreign('commentedBy')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('result_comments');
    }
};
