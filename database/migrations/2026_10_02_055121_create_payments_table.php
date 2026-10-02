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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 16)->unique();
            $table->unsignedBigInteger('amount');
            $table->string('status');
            $table->string('token', 512)->nullable();
            $table->text('message')->nullable();
            $table->string('res_code')->nullable();
            $table->string('retrieval_ref_no')->nullable();
            $table->string('system_trace_no')->nullable();
            $table->string('transaction_date')->nullable();
            $table->string('card_holder_full_name')->nullable();
            $table->json('request_response')->nullable();
            $table->json('callback_response')->nullable();
            $table->json('verify_response')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
