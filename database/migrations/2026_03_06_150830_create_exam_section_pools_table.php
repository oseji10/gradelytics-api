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
        Schema::create('exam_section_pools', function (Blueprint $table) {
    $table->id('examSectionPoolId');
    $table->unsignedBigInteger('examSectionId')->nullable();
    $table->unsignedBigInteger('subjectId')->nullable();
    $table->unsignedBigInteger('topicId')->nullable();
    $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable();
    $table->enum('questionType', ['single_choice', 'multi_choice', 'theory'])->nullable();
    $table->integer('pickCount')->default(1);
    $table->integer('markPerQuestion')->default(1);
    $table->timestamps();
});
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_section_pools');
    }
};
