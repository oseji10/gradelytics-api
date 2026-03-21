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
        Schema::create('cbt_exam_attempts', function (Blueprint $table) {
            $table->id('attemptId');
            $table->unsignedBigInteger('examId');
            $table->unsignedBigInteger('studentId');
            $table->unsignedBigInteger('schoolId');
            $table->dateTime('startedAt');
            $table->dateTime('endsAt')->nullable();
            $table->dateTime('submittedAt')->nullable();
            $table->timestamp('pausedAt')->nullable();
            $table->timestamp('lastHeartbeatAt')->nullable();
            $table->integer('durationMinutes')->default(0);
            $table->integer('timeRemainingSeconds')->default(0);
            $table->integer('totalPausedSeconds')->default(0);
            $table->integer('pauseCount')->default(0)->nullable();

   
            $table->enum('status', ['in_progress', 'paused', 'completed', 'expired']); 
            $table->integer('score')->default(0);
            $table->integer('totalQuestions')->default(0);
            $table->decimal('percentage', 5, 2)->default(0.00);
            $table->string('grade')->nullable();
            $table->integer('timeSpentSeconds')->default(0);
            $table->timestamps();

            $table->foreign('examId')->references('examId')->on('cbt_exams')->onDelete('cascade');
            $table->foreign('studentId')->references('studentId')->on('students')->onDelete('cascade');
            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cbt_exam_attempts');
    }
};
