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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id('transactionId');
            $table->unsignedBigInteger('schoolId');
            $table->decimal('amount', 12, 2);
            $table->enum('type', ['credit', 'debit']);
           
            $table->decimal('balanceBefore', 12, 2);
            $table->decimal('balanceAfter', 12, 2);
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->unsignedBigInteger('createdBy')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
