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
        Schema::create('topics', function (Blueprint $table) {
            $table->id('topicId');
            $table->unsignedBigInteger('schoolId');
            $table->unsignedBigInteger('subjectId');
            $table->string('topicName');
            $table->timestamps();

            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->foreign('subjectId')->references('subjectId')->on('subjects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('topics');
    }
};
