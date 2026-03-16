<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('eventos', 'usercartero')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->string('usercartero')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eventos', 'usercartero')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->dropColumn('usercartero');
            });
        }
    }
};
