<?php

use App\Enums\IssueCategory;
use App\Enums\IssuePriority;
use App\Enums\IssueStatus;
use App\Enums\IssueSummaryStatus;
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
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->longText('description');
            $table->enum('priority', IssuePriority::cases());
            $table->enum('category', IssueCategory::cases());
            $table->enum('status', IssueStatus::cases())->default(IssueStatus::Open);
            $table->string('summary', 500)->nullable();
            $table->longText('suggested_next_action')->nullable();
            $table->enum('summary_status', IssueSummaryStatus::cases())->default(IssueSummaryStatus::Pending);
            $table->boolean('needs_attention')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
