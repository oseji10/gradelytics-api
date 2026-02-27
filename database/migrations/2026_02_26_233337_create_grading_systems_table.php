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
    Schema::create('grading_systems', function (Blueprint $table) {
        $table->id('gradingId');
        $table->unsignedBigInteger('schoolId');
        // $table->unsignedBigInteger('academicYearId')->nullable();

        $table->integer('minScore');
        $table->integer('maxScore');

        $table->string('grade');
        $table->string('remark');
        $table->decimal('gradePoint', 4, 2)->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grading_systems');
    }
};
