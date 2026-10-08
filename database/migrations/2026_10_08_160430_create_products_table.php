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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('magento_id');
            $table->string('sku');
            $table->string('name');
            $table->decimal('price', 12, 4);
            $table->integer('qty')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('attributes')->nullable();
            $table->timestamp('magento_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'sku']);
            $table->unique(['store_id', 'magento_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
