<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Passagens</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0 0;
            opacity: 0.9;
        }
        .stats {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .stat-item {
            background: white;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 18px;
            font-weight: bold;
            color: #667eea;
        }
        .report-content {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            white-space: pre-line;
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .best-price {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 20px;
        }
        .best-price h2 {
            margin: 0 0 10px 0;
            font-size: 20px;
        }
        .best-price .price {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            padding: 20px 0;
            border-top: 1px solid #e0e0e0;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>✈️ Relatório de Busca de Passagens</h1>
        <p>{{ $search->searchRule->name }}</p>
    </div>

    @if($bestPrice)
    <div class="best-price">
        <h2>💰 Menor Preço Encontrado</h2>
        <div class="price">{{ $bestPrice->price_per_person_formatted }}</div>
        <p>
            <strong>{{ $bestPrice->route }}</strong><br>
            {{ $bestPrice->date_range }} | {{ $bestPrice->nights }} noites<br>
            Total: {{ $bestPrice->price_total_formatted }} ({{ $bestPrice->passengers }} pessoas)
        </p>
    </div>
    @endif

    <div class="stats">
        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-label">Data da Busca</div>
                <div class="stat-value">{{ $search->updated_at->format('d/m') }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Combinações</div>
                <div class="stat-value">{{ $search->combinations_tested }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Resultados</div>
                <div class="stat-value">{{ $search->results_found }}</div>
            </div>
            <div class="stat-item">
                <div class="stat-label">Duração</div>
                <div class="stat-value">{{ $search->duration_seconds }}s</div>
            </div>
        </div>
    </div>

    <div class="report-content">
        {!! nl2br(e($reportContent)) !!}
    </div>

    <div style="text-align: center; margin: 20px 0;">
        <p style="color: #666;">Relatório completo em anexo 📎</p>
    </div>

    <div class="footer">
        <p>Gerado em {{ $search->updated_at->format('d/m/Y H:i:s') }}</p>
        <p>Sistema de Busca de Passagens - Viagem Europa</p>
    </div>
</body>
</html>