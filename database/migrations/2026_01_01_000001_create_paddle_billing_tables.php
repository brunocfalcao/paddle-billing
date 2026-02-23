<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('paddle_price_id')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->comment('in cents');
            $table->string('currency', 3)->default('USD');
            $table->timestamps();
        });

        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('paddle_transaction_id')->unique();
            $table->string('status')->default('completed');
            $table->string('invoice_url')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_metadata');
        Schema::dropIfExists('purchases');
        Schema::dropIfExists('products');
    }
};
