<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_account_id')->nullable();
            $table->unsignedBigInteger('recipient_account_id');
            $table->decimal('amount', 19, 3); // always positive
            $table->string('currency', 3);
            $table->string('type'); // 'transfer', 'add_funds'
            $table->string('status')->default('completed');
            $table->timestamps();

            $table->foreign('sender_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('recipient_account_id')->references('id')->on('accounts')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
