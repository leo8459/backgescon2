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
        Schema::create('tarifas', function (Blueprint $table) {
            $table->id();
            $table->string('departamento')->nullable();
            $table->string('provincia')->nullable();
            $table->string('servicio')->nullable();
            $table->string('precio')->nullable();
            $table->string('precio_extra')->nullable();
            $table->string('retencion')->nullable();
            $table->integer('descuento')->nullable();
            $table->string('dias_entrega')->nullable(); // Nueva columna para fecha y hora de entrega

            $table->foreignId('sucursale_id')->nullable()->constrained('sucursales')->onDelete('cascade');
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
        Schema::dropIfExists('tarifas');
    }
};
