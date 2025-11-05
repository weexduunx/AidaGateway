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
        $tableName = config('aida-gateway.transaction.table_name', 'aida_transactions');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique()->index();
            $table->string('external_id')->nullable()->index();
            $table->string('gateway')->index();
            $table->enum('status', ['pending', 'success', 'failed', 'cancelled', 'refunded'])->default('pending')->index();
            $table->string('phone_number')->nullable();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('XOF');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->json('raw_response')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // Foreign key constraint (optional, commented out by default)
            // $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Index for performance
            $table->index(['gateway', 'status']);
            $table->index(['transaction_id', 'gateway']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('aida-gateway.transaction.table_name', 'aida_transactions');
        Schema::dropIfExists($tableName);
    }
};
