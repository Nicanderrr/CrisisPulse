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
        Schema::create('monitored_messages', function (Blueprint $table) {
            $table->id();
            $table->string('source')->default('Manual Entry');
            $table->string('author')->nullable();
            $table->text('content');
            $table->string('sentiment')->default('neutral');
            $table->decimal('sentiment_score', 4, 2)->default(0);
            $table->string('crisis_level')->default('low');
            $table->json('crisis_keywords')->nullable();
            $table->text('recommended_response')->nullable();
            $table->text('summary')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['sentiment', 'created_at']);
            $table->index(['crisis_level', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monitored_messages');
    }
};
