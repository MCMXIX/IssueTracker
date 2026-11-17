<?php

use App\Enums\IssueLogAction;
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
        Schema::create('issue_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('issues')->nullable();
            $table->foreignId('user_id')->constrained('users')->nullable();
            $table->enum('action', IssueLogAction::cases());
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issue_logs');
    }
};
