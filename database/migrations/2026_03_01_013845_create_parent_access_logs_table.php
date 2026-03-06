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
        Schema::create('parent_access_logs', function (Blueprint $table) {
            $table->id('logId');
            $table->unsignedBigInteger('parentAccessId');
            $table->timestamp('accessedAt');
            $table->string('ipAddress')->nullable();
            $table->string('userAgent')->nullable();
            $table->unsignedBigInteger('adminId')->nullable();
            $table->string('action')->nullable(); // activated, revoked

            $table->foreign('parentAccessId')->references('parentAccessId')->on('parent_access')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parent_access_logs');
    }
};
