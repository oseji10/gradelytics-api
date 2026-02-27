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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id('assessmentId');
            $table->string('assessmentName')->nullable();
            $table->decimal('maxScore', 15, 2)->nullable();
            $table->decimal('weight', 15, 2)->nullable();
            $table->unsignedBigInteger('schoolId')->nullable();
            $table->timestamps();

            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
