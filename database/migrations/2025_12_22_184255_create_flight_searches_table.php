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
        Schema::create('flight_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_rule_id')->constrained()->cascadeOnDelete();

            // Status da busca
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');

            // Timing
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // Fontes consultadas
            $table->json('sources_used'); // ['skyscanner', 'google_flights']

            // Estatísticas
            $table->unsignedInteger('combinations_tested')->default(0);
            $table->unsignedInteger('results_found')->default(0);
            $table->unsignedInteger('errors_count')->default(0);

            // Resultados
            $table->decimal('lowest_price_found', 10, 2)->nullable();
            $table->text('best_combination')->nullable(); // JSON da melhor combinação

            // Erros
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_searches');
    }
};
