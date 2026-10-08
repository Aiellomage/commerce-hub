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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('magento_id');
            $table->string('increment_id');
            $table->string('status');
            $table->string('customer_email');
            $table->string('customer_name')->nullable();
            $table->decimal('grand_total', 12, 4);
            $table->char('currency', 3);
            $table->timestamp('placed_at');
            $table->string('erp_status')->default('pending');
            $table->string('erp_reference')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'magento_id']);
            $table->unique(['store_id', 'increment_id']);
            $table->index(['erp_status', 'placed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
