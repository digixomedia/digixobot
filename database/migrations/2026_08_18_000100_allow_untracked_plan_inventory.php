<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('stock')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        DB::table('plans')->whereNull('stock')->update(['stock' => 0]);

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('stock')->nullable(false)->default(0)->change();
        });
    }
};
