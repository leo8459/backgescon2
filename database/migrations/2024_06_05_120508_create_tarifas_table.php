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
            $table->string('nacional_extra')->nullable();
            $table->string('servicioprov')->nullable();
            $table->string('prov_extra')->nullable();
            $table->string('servicioexpress')->nullable();
            $table->string('expres_extra')->nullable();

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
