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
        Schema::table('stores', function (Blueprint $table) {
            $table->text('magento_credentials')->nullable()->after('magento_url');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('magento_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->text('magento_token')->nullable()->after('magento_url');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('magento_credentials');
        });
    }
};
