<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jamb_topics', function (Blueprint $table) {
            $table->bigIncrements('topicId');
            $table->unsignedBigInteger('subjectId');
            $table->string('topicName', 150);
            $table->text('description')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamps();

            $table->foreign('subjectId')
                ->references('subjectId')
                ->on('jamb_subjects')
                ->cascadeOnDelete();

            $table->unique(['subjectId', 'topicName']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jamb_topics');
    }
};