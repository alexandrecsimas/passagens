<?php

namespace App\Mail;

use App\Models\FlightSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FlightReportMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public FlightSearch $search,
        public string $reportContent,
        public ?string $reportPath = null
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = "📊 Relatório de Passagens - {$this->search->searchRule->name}";

        // Adicionar indicador de preço ao subject
        if ($this->search->lowest_price_found) {
            $pricePerPerson = $this->search->lowest_price_found / $this->search->flightPrices()->first()?->passengers ?? 9;
            $subject .= " - A partir de R$ " . number_format($pricePerPerson, 2, ',', '.');
        }

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.flight-report',
            with: [
                'search' => $this->search,
                'reportContent' => $this->reportContent,
                'bestPrice' => $this->search->flightPrices()->orderBy('price_total')->first(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        $attachments = [];

        // Anexar arquivo TXT se existir
        if ($this->reportPath && file_exists($this->reportPath)) {
            $attachments[] = Attachment::fromPath($this->reportPath)
                ->as('relatorio-passagens-' . now()->format('Ymd-His') . '.txt')
                ->withMime('text/plain');
        }

        return $attachments;
    }
}