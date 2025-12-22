<?php

namespace App\Services\Report;

use App\Models\FlightSearch;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppReportService
{
    private string $provider;

    public function __construct()
    {
        $this->provider = config('reports.whatsapp.provider', 'callmebot');
    }

    /**
     * Envia relatório executivo por WhatsApp
     */
    public function sendExecutiveReport(FlightSearch $search): bool
    {
        try {
            // Gerar relatório no formato WhatsApp
            $generator = new ExecutiveReportGenerator();
            $message = $generator->generateForWhatsApp($search);

            // Enviar baseado no provider configurado
            return $this->send($message);
        } catch (\Exception $e) {
            Log::error("Erro ao enviar relatório por WhatsApp", [
                'search_id' => $search->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Envia mensagem por WhatsApp baseado no provider configurado
     */
    private function send(string $message): bool
    {
        return match($this->provider) {
            'twilio' => $this->sendViaTwilio($message),
            'callmebot' => $this->sendViaCallmebot($message),
            'evolution' => $this->sendViaEvolution($message),
            default => throw new \Exception("Provider WhatsApp não configurado: {$this->provider}"),
        };
    }

    /**
     * Envia via Twilio API
     */
    private function sendViaTwilio(string $message): bool
    {
        $sid = config('reports.whatsapp.twilio.sid');
        $token = config('reports.whatsapp.twilio.token');
        $from = config('reports.whatsapp.twilio.from');
        $to = config('reports.whatsapp.to');

        if (empty($sid) || empty($token) || empty($from) || empty($to)) {
            Log::warning('Credenciais Twilio não configuradas');
            return false;
        }

        $response = Http::withBasicAuth($sid, $token)
            ->asForm()
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'From' => "whatsapp:{$from}",
                'To' => "whatsapp:{$to}",
                'Body' => $message,
            ]);

        if ($response->successful()) {
            Log::info("Mensagem enviada via Twilio", [
                'to' => $to,
                'sid' => $response->json('sid'),
            ]);

            return true;
        }

        Log::error("Erro ao enviar via Twilio", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    /**
     * Envia via Callmebot API (grátis, limitado)
     */
    private function sendViaCallmebot(string $message): bool
    {
        $apiKey = config('reports.whatsapp.callmebot.api_key');
        $phone = config('reports.whatsapp.to'); // Formato: 5511999999999

        if (empty($apiKey) || empty($phone)) {
            Log::warning('Credenciais Callmebot não configuradas');
            return false;
        }

        // Limpar número (remover +, espaços, traços)
        $phone = preg_replace('/[^0-9]/', '', $phone);

        $response = Http::get('https://api.callmebot.com/whatsapp.php', [
            'phone' => $phone,
            'text' => $message,
            'apikey' => $apiKey,
        ]);

        if ($response->successful()) {
            Log::info("Mensagem enviada via Callmebot", [
                'to' => $phone,
            ]);

            return true;
        }

        Log::error("Erro ao enviar via Callmebot", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }

    /**
     * Envia via Evolution API (self-hosted)
     */
    private function sendViaEvolution(string $message): bool
    {
        $apiUrl = config('reports.whatsapp.evolution.api_url');
        $apiKey = config('reports.whatsapp.evolution.api_key');
        $instance = config('reports.whatsapp.evolution.instance');
        $to = config('reports.whatsapp.to'); // Formato: 5511999999999

        if (empty($apiUrl) || empty($apiKey) || empty($instance) || empty($to)) {
            Log::warning('Credenciais Evolution não configuradas');
            return false;
        }

        // Limpar número
        $phone = preg_replace('/[^0-9]/', '', $to);
        $phone = "{$phone}@s.whatsapp.net";

        $response = Http::withHeaders([
            'apikey' => $apiKey,
        ])->post("{$apiUrl}/message/sendText/{$instance}", [
            'number' => $phone,
            'text' => $message,
        ]);

        if ($response->successful()) {
            Log::info("Mensagem enviada via Evolution", [
                'to' => $to,
            ]);

            return true;
        }

        Log::error("Erro ao enviar via Evolution", [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        return false;
    }
}