<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInventoryLogsTable extends Migration
{
    public function up()
    {
        Schema::create('inventory_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('product_detail_id');
            $table->integer('change'); // +qty hoặc -qty
            $table->integer('quantity_before');
            $table->integer('quantity_after');
            $table->string('type'); // 'receipt', 'sale', 'adjustment', ...
            $table->unsignedBigInteger('related_id')->nullable(); // receipt id or order id
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->foreign('product_detail_id')->references('id')->on('product_details')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('inventory_logs');
    }
}
