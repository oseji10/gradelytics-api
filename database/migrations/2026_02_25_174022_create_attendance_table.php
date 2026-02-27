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
        Schema::create('attendance', function (Blueprint $table) {
            $table->id('attendanceId');

            $table->unsignedBigInteger('classId')->nullable();
            $table->unsignedBigInteger('studentId')->nullable();
            $table->unsignedBigInteger('teacherId')->nullable();
            $table->unsignedBigInteger('schoolId')->nullable();
            $table->date('attendanceDate')->nullable();
            $table->unsignedBigInteger('termId')->nullable();
            $table->unsignedBigInteger('academicYearId')->nullable();
            $table->enum('status', ['present', 'absent', 'late'])->default('absent');
            $table->boolean('attendanceStatus')->default(0);
            $table->string('notes')->nullable();


            $table->timestamps();
             $table->softDeletes();

            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->foreign('classId')->references('classId')->on('classes')->onDelete('cascade');
            $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade');
            $table->foreign('teacherId')->references('teacherId')->on('teachers')->onDelete('cascade');
            $table->foreign('termId')->references('termId')->on('terms')->onDelete('cascade');
            $table->foreign('academicYearId')->references('academicYearId')->on('academic_years')->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance');
    }
};
