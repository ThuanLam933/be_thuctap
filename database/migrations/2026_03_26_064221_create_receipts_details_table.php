<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    
    public function up(): void
    {
        Schema::create('receipt_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_detail_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('receipt_id')
                ->constrained()
                ->onDelete('cascade');
            $table->integer('quantity');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('subtotal');
            $table->timestamps();
        });
    }

   
    public function down(): void
    {
        Schema::dropIfExists('receipt_details');
    }
};
