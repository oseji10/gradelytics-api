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
        Schema::create('cbt_questions', function (Blueprint $table) {
            $table->id('questionId');
            $table->timestamps();

            $table->unsignedBigInteger('schoolId');
            $table->unsignedBigInteger('subjectId');
            $table->unsignedBigInteger('topicId')->nullable();
            $table->string('difficulty')->default('medium');
            $table->string('type'); // single_choice, multi_choice, theory
            $table->text('questionText');
            $table->string('imageUrl')->nullable();
            $table->integer('mark')->default(1);
            $table->unsignedBigInteger('createdBy');

            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->foreign('subjectId')->references('subjectId')->on('subjects')->onDelete('cascade');
            $table->foreign('topicId')->references('topicId')->on('topics')->onDelete('cascade');
            $table->foreign('createdBy')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_questions');
    }
};

