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
        Schema::table('students', function (Blueprint $table) {
        $table->unsignedBigInteger('clubId')->nullable();
        $table->unsignedBigInteger('houseId')->nullable();
        $table->foreign('clubId')->references('clubId')->on('clubs')->onDelete('cascade');
        $table->foreign('houseId')->references('houseId')->on('houses')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
