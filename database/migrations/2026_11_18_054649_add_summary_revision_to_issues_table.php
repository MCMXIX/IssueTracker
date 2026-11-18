<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->unsignedInteger('summary_revision')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table): void {
            $table->dropColumn('summary_revision');
        });
    }
};
