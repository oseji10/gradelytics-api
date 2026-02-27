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
        Schema::create('affective_domains', function (Blueprint $table) {
        $table->id('domainId');
        $table->string('domainName'); // e.g., "Teamwork"
        $table->decimal('maxScore', 5, 2)->default(10); // max score for this domain
        $table->decimal('weight', 5, 2)->default(10);   // weight towards total affective score
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
        Schema::dropIfExists('affective_domains');
    }
};
