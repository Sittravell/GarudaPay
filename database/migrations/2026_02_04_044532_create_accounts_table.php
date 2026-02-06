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
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('name');
            $table->decimal('balance', 19, 3)->default(0);
            $table->boolean('is_main')->default(false);
            $table->string('currency', 3)->default('USD');
            $table->timestamps();

            // validation ensures user_id exists in users table, but we add FK constraint if users table is in same DB
            // We'll wrap in try-catch or just check if table exists?? 
            // Standard migration assumes we can define FK. 
            // If the user table is in another DB (on another server), this fails. 
            // But prompt says "sharing db". So same DB instance.
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
