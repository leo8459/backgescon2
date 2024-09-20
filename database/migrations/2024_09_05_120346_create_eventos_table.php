<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('accion')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('codigo')->nullable();
            $table->timestamp('fecha_hora')->nullable();
            $table->string('user_type')->nullable(); // Para almacenar el tipo de usuario (empresa, sucursal, etc.)
            $table->unsignedBigInteger('user_id')->nullable(); // Para almacenar el ID del usuario que hizo la acción


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
