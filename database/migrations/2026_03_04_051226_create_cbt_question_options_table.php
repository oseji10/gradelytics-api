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
        Schema::create('cbt_question_options', function (Blueprint $table) {
            $table->id('optionId');
            $table->unsignedBigInteger('questionId');
            $table->string('optionLabel')->nullable(); // A, B, C, D etc.
            $table->text('optionText');
            $table->boolean('isCorrect')->default(false);
            $table->timestamps();

            $table->foreign('questionId')->references('questionId')->on('cbt_questions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_question_options');
    }
};
