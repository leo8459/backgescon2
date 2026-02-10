<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transporte', function (Blueprint $table) {
            $table->id();
            $table->string('transportadora');
            $table->string('provincia');
            $table->foreignId('cartero_id')->constrained('carteros');
            $table->string('n_recibo')->nullable();
            $table->string('n_factura')->nullable();
            $table->decimal('precio_total', 12, 2)->default(0);
            $table->decimal('peso_total', 12, 2)->default(0);
            $table->json('guias')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transporte');
    }
};
