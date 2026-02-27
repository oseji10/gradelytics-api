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
        Schema::create('classes', function (Blueprint $table) {
            $table->id('classId');
            $table->unsignedBigInteger('schoolId')->nullable();
            $table->string('className')->nullable();
           
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
