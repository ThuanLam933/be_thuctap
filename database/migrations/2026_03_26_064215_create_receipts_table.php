<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $table->foreignId('suppliers_id')
                ->constrained()
                ->onDelete('cascade');
            $table->string('note');
            $table->unsignedBigInteger('total_price');
            $table->date('import_date');
            $table->timestamps();
        });
    }

  
    public function down(): void
    {
        Schema::dropIfExists('receipts');
    }
};
