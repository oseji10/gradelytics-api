<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jamb_question_options', function (Blueprint $table) {
            $table->bigIncrements('optionId');
            $table->unsignedBigInteger('questionId');
            $table->enum('optionLabel', ['A', 'B', 'C', 'D']);
            $table->text('optionText');
            $table->boolean('isCorrect')->default(false);
            $table->timestamps();

            $table->foreign('questionId')
                ->references('questionId')
                ->on('jamb_questions')
                ->cascadeOnDelete();

            $table->unique(['questionId', 'optionLabel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jamb_question_options');
    }
};