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
            $table->string('servicio')->nullable();
            // $table->string('tiempo')->nullable();
            $table->string('servicioprov')->nullable();
            // $table->string('tiempoprov')->nullable();
            $table->string('servicioexpress')->nullable();
            // $table->string('tiempoexpress')->nullable();

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
