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
        Schema::create('search_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();

            // Janelas de data
            $table->date('departure_date_min');
            $table->date('departure_date_max');
            $table->date('return_date_min');
            $table->date('return_date_max');

            // Restrições de duração
            $table->unsignedTinyInteger('min_nights')->default(13);
            $table->unsignedTinyInteger('max_nights')->default(16);

            // Origens e destinos (JSON para múltiplas cidades)
            $table->json('origins'); // ['GRU', 'GIG']
            $table->json('destinations'); // ['CDG', 'LHR', 'FCO']

            // Passageiros e classe
            $table->unsignedTinyInteger('passengers')->default(9);
            $table->enum('cabin_class', ['economy', 'premium_economy', 'business', 'first'])->default('economy');

            // Preferências de voo
            $table->unsignedTinyInteger('max_connections')->default(1);
            $table->boolean('baggage_included')->default(true);

            // Controle
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('priority')->default(0);

            // Relacionamento
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('search_rules');
    }
};
