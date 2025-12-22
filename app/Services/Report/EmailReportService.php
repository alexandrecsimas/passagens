<?php

namespace App\Services\Report;

use App\Mail\FlightReportMail;
use App\Models\FlightSearch;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailReportService
{
    /**
     * Envia relatório executivo por e-mail
     */
    public function sendExecutiveReport(FlightSearch $search, ?string $customRecipient = null): bool
    {
        try {
            // Gerar relatório executivo
            $generator = new ExecutiveReportGenerator();
            $reportContent = $generator->generate($search);

            // Gerar arquivo TXT para anexo
            $reportPath = null;
            if (config('reports.email.attach_file', true)) {
                $reportPath = $this->generateReportFile($search, $reportContent);
            }

            // Destinatários
            $to = $customRecipient ?? config('reports.email.to');
            $cc = config('reports.email.cc');

            if (empty($to)) {
                Log::warning('Nenhum destinatário configurado para e-mail de relatório');
                return false;
            }

            // Enviar e-mail
            Mail::to($to)
                ->cc($cc)
                ->send(new FlightReportMail($search, $reportContent, $reportPath));

            Log::info("Relatório enviado por e-mail", [
                'search_id' => $search->id,
                'to' => $to,
                'subject' => "Relatório de Passagens - {$search->searchRule->name}",
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Erro ao enviar relatório por e-mail", [
                'search_id' => $search->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Gera arquivo temporário do relatório
     */
    private function generateReportFile(FlightSearch $search, string $content): string
    {
        $filename = "relatorio_executivo_{$search->id}_" . now()->format('Ymd_His') . ".txt";
        $path = storage_path("reports/{$filename}");

        // Criar diretório se não existir
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Envia relatório completo por e-mail
     */
    public function sendFullReport(FlightSearch $search, ?string $customRecipient = null): bool
    {
        try {
            // Gerar relatório completo
            $generator = new TextReportGenerator();
            $reportContent = $generator->generate($search);

            // Usar arquivo já gerado
            $reportPath = storage_path("reports/search_{$search->id}_*.txt");
            $matchingFiles = glob($reportPath);

            if (empty($matchingFiles)) {
                // Gerar novo se não existir
                $reportPath = $generator->save($search);
            } else {
                $reportPath = $matchingFiles[0];
            }

            // Destinatários
            $to = $customRecipient ?? config('reports.email.to');
            $cc = config('reports.email.cc');

            if (empty($to)) {
                Log::warning('Nenhum destinatário configurado para e-mail de relatório');
                return false;
            }

            // Enviar e-mail
            Mail::to($to)
                ->cc($cc)
                ->send(new FlightReportMail($search, $reportContent, $reportPath));

            Log::info("Relatório completo enviado por e-mail", [
                'search_id' => $search->id,
                'to' => $to,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Erro ao enviar relatório completo por e-mail", [
                'search_id' => $search->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}