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
        Schema::create('students', function (Blueprint $table) {
            $table->id('studentId');
            $table->unsignedBigInteger('schoolId')->nullable();
            $table->unsignedBigInteger('userId')->nullable();
            $table->string('admissionNumber')->nullable();
            $table->string('schoolAssignedAdmissionNumber')->nullable();
            $table->string('bloodGroup')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('dateOfBirth')->nullable();
            $table->unsignedBigInteger('parentId')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->foreign('userId')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('parentId')->references('id')->on('users')->onDelete('cascade');

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
