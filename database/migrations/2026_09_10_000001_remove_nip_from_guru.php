<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->dropUnique('guru_nip_key');
            $table->dropColumn('nip');
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->string('nip')->unique()->after('userId');
        });
    }
};
