<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            // 👇 discount nullable ngay từ đầu
            $table->foreignId('discount_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->uuid('order_code');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('address');
            $table->text('note')->nullable();

            $table->decimal('total_price', 12, 2)->unsigned();

            $table->tinyInteger('status_stock')->default(1);

            // 👇 gộp thêm luôn
            $table->tinyInteger('status_method')->default(0);

            $table->enum('payment_method', ['Cash', 'Banking'])->default('Banking');

            $table->enum('status', [
                'pending',
                'confirmed',
                'shipping',
                'returned',
                'completed',
                'cancelled'
            ])->default('pending');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};