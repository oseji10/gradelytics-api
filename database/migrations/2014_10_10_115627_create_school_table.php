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
        Schema::create('schools', function (Blueprint $table) {
            $table->id('schoolId');
            $table->string('schoolName')->nullable();
            $table->string('schoolEmail')->nullable();
            $table->string('schoolPhone')->nullable();
            $table->string('schoolLogo')->nullable();
            $table->string('schoolAddress')->nullable();
            
            $table->unsignedBigInteger('addedBy')->nullable();
            $table->boolean('isDefault')->nullable()->default(1);
            $table->string('status')->nullable()->default('active');
            $table->unsignedBigInteger('currentPlan')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('addedBy')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('currentPlan')->references('planId')->on('plans')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
