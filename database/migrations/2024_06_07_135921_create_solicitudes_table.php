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
            $table->foreignId('direccion_id')->nullable()->constrained('direcciones');
            $table->foreignId('encargado_id')->nullable()->constrained('encargados');
            $table->foreignId('encargado_regional_id')->nullable()->constrained('encargados');

            $table->string('guia')->nullable();
            $table->integer('estado')->default(1);
            $table->string('peso_o')->nullable();
            $table->string('peso_v')->nullable();
            $table->string('peso_r')->nullable();
            $table->string('remitente')->nullable();
            $table->string('telefono')->nullable();
            $table->string('contenido')->nullable();
            $table->string('destinatario')->nullable();
            $table->string('telefono_d')->nullable();
            $table->string('direccion_d')->nullable();
            $table->string('direccion_especifica_d')->nullable();
            $table->string('zona_d')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('nombre_d')->nullable();
            $table->string('fecha')->nullable();
            $table->string('fecha_d')->nullable();
            $table->string('fecha_recojo_c')->nullable();
            $table->string('fecha_devolucion')->nullable();
            $table->string('fecha_envio_regional')->nullable();

            $table->string('observacion')->nullable();
            $table->string('justificacion')->nullable();
            $table->text('imagen')->nullable();
            $table->text('imagen_devolucion')->nullable();
            $table->text('imagen_justificacion')->nullable();
            $table->text('firma_o')->nullable();
            $table->text('firma_d')->nullable();
            $table->text('codigo_barras')->nullable(); // Almacena la imagen del código de barras


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
