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
        Schema::create('best_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('search_rule_id')->constrained()->cascadeOnDelete();

            // Combinação única
            $table->string('origin');
            $table->string('destination');
            $table->date('departure_date');
            $table->date('return_date');
            $table->unsignedTinyInteger('nights');

            // Melhor preço encontrado
            $table->decimal('best_price_per_person', 10, 2);
            $table->decimal('best_price_total', 10, 2);
            $table->string('currency', 3)->default('BRL');

            // Fonte do melhor preço
            $table->enum('source', ['skyscanner', 'google_flights', 'other'])->default('skyscanner');
            $table->foreignId('flight_price_id')->nullable()->constrained()->cascadeOnDelete();

            // Tracking
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedInteger('times_found')->default(1);

            // Status
            $table->boolean('is_still_valid')->default(true);
            $table->timestamp('valid_until')->nullable();

            $table->timestamps();

            // Índice único para evitar duplicatas
            $table->unique(['search_rule_id', 'origin', 'destination', 'departure_date', 'return_date'], 'unique_combination');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('best_prices');
    }
};
