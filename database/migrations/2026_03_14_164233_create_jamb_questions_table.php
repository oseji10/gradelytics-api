<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jamb_questions', function (Blueprint $table) {
            $table->bigIncrements('questionId');
            $table->unsignedBigInteger('subjectId');
            $table->unsignedBigInteger('topicId')->nullable();

            $table->year('year')->nullable();
            $table->longText('questionText');
            $table->string('questionImage')->nullable();
            $table->longText('passageText')->nullable();

            $table->enum('optionType', ['single_choice'])->default('single_choice');
            $table->enum('correctOption', ['A', 'B', 'C', 'D']);
            $table->longText('explanation')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');

            $table->unsignedBigInteger('createdBy')->nullable();
            // $table->unsignedBigInteger('schoolId')->nullable();

            $table->timestamps();

            $table->foreign('subjectId')
                ->references('subjectId')
                ->on('jamb_subjects')
                ->cascadeOnDelete();

            $table->foreign('topicId')
                ->references('topicId')
                ->on('jamb_topics')
                ->nullOnDelete();

            $table->index(['subjectId', 'topicId', 'status']);
            $table->index(['subjectId', 'year']);
            $table->index(['subjectId', 'difficulty']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jamb_questions');
    }
};