<?php

namespace App\Console\Commands;

use App\Models\FlightSearch;
use App\Services\Report\EmailReportService;
use App\Services\Report\WhatsAppReportService;
use Illuminate\Console\Command;

class FlightsReportSend extends Command
{
    protected $signature = 'flights:report:send
                            {--id= : ID da FlightSearch (usa a última se não informado)}
                            {--email : Enviar por e-mail}
                            {--whatsapp : Enviar por WhatsApp}
                            {--to= : Destinatário customizado (e-mail ou telefone)}
                            {--full : Enviar relatório completo ao invés do executivo}';

    protected $description = 'Envia relatório de busca de passagens por e-mail/WhatsApp';

    public function __construct(
        private EmailReportService $emailService,
        private WhatsAppReportService $whatsappService
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $search = $this->getFlightSearch();

        if (!$search) {
            $this->error('❌ Nenhuma busca encontrada!');
            return Command::FAILURE;
        }

        $sendEmail = $this->option('email') ?? config('reports.email.enabled', false);
        $sendWhatsApp = $this->option('whatsapp') ?? config('reports.whatsapp.enabled', false);
        $sendFull = $this->option('full');
        $customRecipient = $this->option('to');

        // Se nenhum canal especificado, usar configurações padrão
        if (!$this->option('email') && !$this->option('whatsapp')) {
            $sendEmail = config('reports.email.enabled', false);
            $sendWhatsApp = config('reports.whatsapp.enabled', false);
        }

        // Se ainda assim nenhum canal, pedir para especificar
        if (!$sendEmail && !$sendWhatsApp) {
            $this->error('❌ Nenhum canal especificado! Use --email e/ou --whatsapp');
            $this->warn('Ou configure REPORTS_EMAIL_ENABLED=true ou REPORTS_WHATSAPP_ENABLED=true no .env');
            return Command::FAILURE;
        }

        $this->info("📊 Enviando relatório da busca #{$search->id}...");
        $this->info("📋 Regra: {$search->searchRule->name}");
        $this->info("📅 Data: " . $search->updated_at->format('d/m/Y H:i'));
        $this->newLine();

        $success = true;

        // Enviar por e-mail
        if ($sendEmail) {
            $this->info("📧 Enviando por e-mail...");

            try {
                if ($sendFull) {
                    $result = $this->emailService->sendFullReport($search, $customRecipient);
                } else {
                    $result = $this->emailService->sendExecutiveReport($search, $customRecipient);
                }

                if ($result) {
                    $this->info("✅ E-mail enviado com sucesso!");
                } else {
                    $this->warn("⚠️  Falha ao enviar e-mail (verifique os logs)");
                    $success = false;
                }
            } catch (\Exception $e) {
                $this->error("❌ Erro ao enviar e-mail: " . $e->getMessage());
                $success = false;
            }
        }

        // Enviar por WhatsApp
        if ($sendWhatsApp) {
            $this->info("📱 Enviando por WhatsApp...");

            try {
                // WhatsApp sempre usa formato executivo (limitado por caracteres)
                $result = $this->whatsappService->sendExecutiveReport($search);

                if ($result) {
                    $this->info("✅ WhatsApp enviado com sucesso!");
                } else {
                    $this->warn("⚠️  Falha ao enviar WhatsApp (verifique os logs)");
                    $success = false;
                }
            } catch (\Exception $e) {
                $this->error("❌ Erro ao enviar WhatsApp: " . $e->getMessage());
                $success = false;
            }
        }

        $this->newLine();

        if ($success) {
            $this->info("✅ Relatório enviado com sucesso!");
            return Command::SUCCESS;
        } else {
            $this->warn("⚠️  Relatório enviado com avisos. Verifique os logs para detalhes.");
            return Command::FAILURE;
        }
    }

    private function getFlightSearch(): ?FlightSearch
    {
        $id = $this->option('id');

        if ($id) {
            return FlightSearch::find($id);
        }

        // Busca a última busca completada
        return FlightSearch::where('status', 'completed')
            ->latest()
            ->first();
    }
}