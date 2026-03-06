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
        Schema::create('pin_cost', function (Blueprint $table) {
            $table->id();
            $table->string('minQuantity')->nullable();
            $table->string('maxQuantity')->nullable();
            $table->decimal('costPerPin', 8, 2)->default(0);
            $table->unsignedBigInteger('schoolId');
            $table->foreign('schoolId')->references('schoolId')->on('schools')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pin_cost');
    }
};
