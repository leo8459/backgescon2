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
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursale_id')->nullable()->constrained('sucursales')->onDelete('cascade');
            $table->foreignId('tarifa_id')->nullable()->constrained('tarifas')->onDelete('cascade');
            $table->foreignId('cartero_recogida_id')->nullable()->constrained('carteros');
            $table->foreignId('cartero_entrega_id')->nullable()->constrained('carteros');
            $table->string('guia')->nullable();
            $table->text('codigo_barras')->nullable(); // Almacena la imagen del código de barras
            $table->string('peso_o')->nullable();
            $table->string('peso_v')->nullable();
            $table->string('remitente')->nullable();
            $table->string('direccion')->nullable();
            $table->string('direccion_especifica')->nullable();
            $table->string('zona_r')->nullable();
            $table->string('telefono')->nullable();
            $table->string('contenido')->nullable();
            $table->date('fecha')->nullable();
            $table->string('destinatario')->nullable();
            $table->string('telefono_d')->nullable();
            $table->string('direccion_d')->nullable();
            $table->string('direccion_especifica_d')->nullable();
            $table->string('zona_d')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('nombre_d')->nullable();
            $table->string('ci_d')->nullable();
            $table->string('fecha_d')->nullable();
            $table->integer('estado')->default(1);
            $table->string('observacion')->nullable();
            $table->text('firma_o')->nullable();
            $table->text('firma_d')->nullable();
            $table->text('imagen')->nullable();

            
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
        Schema::dropIfExists('solicitudes');
    }
};
