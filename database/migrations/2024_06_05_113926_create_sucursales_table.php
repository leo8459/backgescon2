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
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('origen')->nullable();
            $table->string('fin_vigencia')->nullable();
            $table->string('limite')->nullable();
            $table->string('cobertura')->nullable();
            $table->string('ini_vigencia')->nullable();
            $table->string('direccion')->nullable();
            $table->string('contacto_administrativo')->nullable();
            $table->string('acuerdos')->nullable();
            $table->integer('estado')->default(1);
            $table->foreignId('empresa_id')->nullable()->constrained('empresas');
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
        Schema::dropIfExists('sucursales');
    }
};
