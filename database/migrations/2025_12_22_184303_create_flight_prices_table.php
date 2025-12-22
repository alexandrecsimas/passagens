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
        Schema::create('flight_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flight_search_id')->nullable()->constrained()->cascadeOnDelete();

            // Fonte da informação
            $table->enum('source', ['skyscanner', 'google_flights', 'mock', 'other'])->default('skyscanner');

            // Rota
            $table->string('origin'); // GRU, GIG
            $table->string('destination'); // CDG, LHR, FCO
            $table->string('return_origin'); // Para open-jaw
            $table->date('departure_date');
            $table->date('return_date');

            // Datas calculadas
            $table->unsignedTinyInteger('nights')->nullable();

            // Preços
            $table->decimal('price_per_person', 10, 2);
            $table->unsignedInteger('passengers');
            $table->decimal('price_total', 10, 2)->storedAs('price_per_person * passengers');
            $table->string('currency', 3)->default('BRL');

            // Detalhes do voo
            $table->string('airline')->nullable();
            $table->unsignedTinyInteger('connections')->default(0);
            $table->boolean('baggage_included')->default(false);
            $table->string('flight_url')->nullable();

            // Metadados
            $table->json('additional_data')->nullable(); // Dados extras da API

            $table->timestamps();
            $table->timestamp('expires_at')->nullable(); // Quando o preço expira

            // Índices para consultas rápidas
            $table->index(['departure_date', 'return_date']);
            $table->index(['origin', 'destination']);
            $table->index('price_total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flight_prices');
    }
};
