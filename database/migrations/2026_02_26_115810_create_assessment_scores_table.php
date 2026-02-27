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
        Schema::create('assessment_scores', function (Blueprint $table) {
            $table->id('assessmentScoreId');
            $table->unsignedBigInteger('assessmentId')->nullable();
            $table->unsignedBigInteger('subjectId')->nullable();
            $table->unsignedBigInteger('studentId')->nullable();
            $table->decimal('score', 15, 2)->nullable();
            $table->unsignedBigInteger('schoolId')->nullable();
            $table->unsignedBigInteger('classId')->nullable();
            $table->unsignedBigInteger('academicYearId')->nullable();
            $table->unsignedBigInteger('termId')->nullable();
            $table->string('grade')->nullable();
            $table->string('grade')->nullable();
            
            $table->timestamps();

            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->foreign('academicYearId')->references('academicYearId')->on('academic_years')->onDelete('cascade');
            $table->foreign('termId')->references('termId')->on('terms')->onDelete('cascade');
            $table->foreign('subjectId')->references('subjectId')->on('subjects')->onDelete('cascade');
            $table->foreign('classId')->references('classId')->on('classes')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_scores');
    }
};
