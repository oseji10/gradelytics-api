<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up()
{
    Schema::create('results', function (Blueprint $table) {
        $table->id('resultId');

        $table->unsignedBigInteger('studentId');
        $table->unsignedBigInteger('classId');
        $table->unsignedBigInteger('subjectId');
        $table->unsignedBigInteger('termId');
        $table->unsignedBigInteger('academicYearId');
        $table->unsignedBigInteger('schoolId');

        $table->decimal('totalScore', 6, 2);
        $table->string('grade')->nullable();
        $table->string('remark')->nullable();
        $table->string('classTeacherComment')->nullable();
        $table->string('principalComment')->nullable();

         $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
         $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade');
         $table->foreign('subjectId')->references('subjectId')->on('subjects')->onDelete('cascade');
         $table->foreign('academicYearId')->references('academicYearId')->on('academic_years')->onDelete('cascade');
         $table->foreign('termId')->references('termId')->on('terms')->onDelete('cascade');
         $table->foreign('classId')->references('classId')->on('classes')->onDelete('cascade');
          

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('results');
    }
};
