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
         
            $table->foreignId('sucursale_id')->nullable()->constrained('sucursales')->onDelete('cascade');
            $table->foreignId('encargado_id')->nullable()->constrained('encargados')->onDelete('cascade');
            $table->foreignId('cartero_id')->nullable()->constrained('carteros')->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('sucursale_id');
            $table->dropForeign(['encargado_id']);
            $table->dropForeign(['cartero_id']);
            $table->dropColumn(['encargado_id', 'cartero_id']);
        });    }
};
