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
        Schema::create('parent_access', function (Blueprint $table) {
            $table->id('parentAccessId');
            $table->unsignedBigInteger('parentId')->nullable();
            $table->unsignedBigInteger('schoolId')->nullable();
            $table->string('phoneNumber')->nullable();
            $table->unsignedBigInteger('academicYearId')->nullable();
            $table->unsignedBigInteger('termId')->nullable();
            $table->string('pinHash')->nullable();
            $table->string('pinLast4')->nullable();
            $table->string('paymentMethod')->nullable();
            $table->decimal('amountPaid', 8, 2)->nullable();
            $table->timestamp('expiresAt')->nullable();
            $table->boolean('isActive')->default(true);
            $table->integer('failedAttempts')->default(0);
            $table->timestamp('lockedUntil')->nullable();
            $table->unsignedBigInteger('activatedBy')->nullable();

                $table->foreign('parentId')->references('parentId')->on('parents')->onDelete('cascade');
                $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
                $table->foreign('academicYearId')->references('academicYearId')->on('academic_years')->onDelete('cascade');
                $table->foreign('termId')->references('termId')->on('terms')->onDelete('cascade');
                $table->foreign('activatedBy')->references('id')->on('users')->onDelete('set null');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_access');
    }
};
