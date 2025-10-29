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
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->enum('type', [
                'deposit',
                'withdraw',
                'lock',
                'unlock',
                'credit_grant',
                'credit_revoke',
                'credit_repay',
                'interest_charge'
            ])->index();
            $table->decimal('amount', 15);
            $table->string('description');
            $table->string('reference')->nullable();
            $table->json('meta')->nullable();
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'reversed'
            ])->default('pending');
            $table->timestamps();

            $table->index('status');
            $table->index('reference');
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