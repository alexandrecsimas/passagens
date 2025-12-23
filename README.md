# ✈️ Sistema de Busca de Passagens Aéreas

Sistema automatizado para busca, comparação e relatórios de preços de passagens aéreas com múltiplas fontes e notificações por e-mail.

## 📋 Índice

- [Funcionalidades](#-funcionalidades)
- [Demonstração](#-demonstração)
- [Requisitos](#-requisitos)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [Uso](#-uso)
- [Arquitetura](#-arquitetura)
- [Roadmap](#-roadmap)

## 🚀 Funcionalidades

- ✅ **Busca automatizada** de preços em múltiplas fontes
- ✅ **96 combinações** de voos (datas × rotas)
- ✅ **Suporte a open-jaw** (saida de uma cidade, retorno em outra)
- ✅ **Relatórios detalhados** em TXT
- ✅ **Relatórios executivos** (top 5 melhores preços)
- ✅ **Envio por e-mail** com HTML + anexo
- ✅ **Múltiplas fontes** (Mock, Skyscanner, Google Flights)
- ✅ **Interface Filament** para administração

## 📸 Demonstração

### Relatório Gerado

```
════════════════════════════════════════════════════════════
  RELATÓRIO DE BUSCA DE PASSAGENS
  Viagem Europa - 15 Anos da Clarice
════════════════════════════════════════════════════════════

Data: 22/12/2025 21:18
Buscas realizadas: 96
Resultados encontrados: 96
Fontes: mock
Duração: 13s

🥇 R$ 6.957,56 → GRU 20/07 → FCO 02/08 → GRU
   Total: R$ 62.618,04 (9 pessoas)
   Fonte: Mock | 13 noites | Alitalia
```

## 📦 Requisitos

- **PHP** >= 8.2
- **Composer** 2.x
- **Docker** & **Docker Compose**
- **MySQL** / **MariaDB** (ou SQLite)
- **Redis** (para filas)
- **Node.js** & **NPM** (para assets do Filament)

## 🔧 Instalação

### 1. Clone o repositório

```bash
git clone https://github.com/seu-usuario/passagens.git
cd passagens
```

### 2. Configure o ambiente

```bash
cp .env.example .env
```

Edite o `.env` com suas configurações (veja [Configuração](#configuração)).

### 3. Suba os containers Docker

```bash
docker compose up -d
```

### 4. Instale as dependências

```bash
docker compose exec app composer install
docker compose exec app npm install
```

### 5. Execute as migrações

```bash
docker compose exec app php artisan migrate
```

### 6. Seed os dados iniciais

```bash
docker compose exec app php artisan db:seed --class=SearchRuleSeeder
```

### 7. Compile os assets (opcional - para Filament)

```bash
docker compose exec app npm run build
```

## ⚙️ Configuração

### Configuração de E-mail (para relatórios)

No arquivo `.env`, configure o SMTP:

```bash
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=seu-email@gmail.com
MAIL_PASSWORD=sua-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="seu-email@gmail.com"

# Relatórios
REPORTS_EMAIL_ENABLED=true
REPORTS_EMAIL_TO="destinatario@example.com"
REPORTS_EMAIL_CC="copia@example.com"
```

**Importante**: Para Gmail, você precisa usar uma **App Password**:
1. Ative 2FA na conta Google
2. Acesse: https://myaccount.google.com/apppasswords
3. Crie uma senha de app
4. Use essa senha no `MAIL_PASSWORD`

### Configuração de Fontes de Dados

```bash
# Mock scraper (para testes)
REPORTS_MOCK_ENABLED=true

# Scrapers reais (futuro)
SKYSCANNER_ENABLED=false
GOOGLE_FLIGHTS_ENABLED=false
```

## 🎯 Uso

### Executar uma busca

```bash
# Busca com dados mock (teste)
docker compose exec app php artisan flights:search --source=mock

# Busca com fonte específica
docker compose exec app php artisan flights:search --source=skyscanner
```

### Enviar relatório por e-mail

```bash
# Enviar relatório executivo
docker compose exec app php artisan flights:report:send --email

# Enviar relatório completo
docker compose exec app php artisan flights:report:send --email --full
```

### Visualizar combinações

```bash
docker compose exec app php artisan flights:combinations
```

### Acessar o Filament Admin

```bash
# Acesse no navegador
http://localhost:8000/admin

# Ou gere um link de login
docker compose exec app php artisan filament:link
```

## 📊 Exemplos de Uso

### Cenário: Buscar passagens para 9 pessoas

```bash
# 1. Executar busca
docker compose exec app php artisan flights:search --source=mock

# Saída:
# ✅ Busca completada em 13.2 segundos
# 📊 96 combinações testadas
# 💰 Menor preço: R$ 6.957,56

# 2. Enviar relatório
docker compose exec app php artisan flights:report:send --email

# Resultado: E-mail enviado com top 5 melhores preços
```

### Cenário: Análise de estatísticas

```bash
# Visualizar relatório completo
cat storage/reports/search_X_YYYYMMDD_HHMMSS.txt

# Inclui:
# - Top 20 preços
# - Estatísticas (média, min, max)
# - Por origem (GRU, GIG)
# - Por destino (Paris, Londres, Roma)
```

## 🏗️ Arquitetura

```
passagens/
├── app/
│   ├── Services/
│   │   ├── Scraping/          # Scrapers (Mock, Skyscanner, etc)
│   │   ├── Report/            # Geradores de relatório
│   │   ├── CombinatorService  # Gera combinações de voos
│   │   └── FlightSearchService # Orquestra buscas
│   ├── Jobs/                  # Jobs de processamento
│   ├── Models/                # FlightSearch, FlightPrice, SearchRule
│   ├── DTOs/                  # FlightCombination
│   ├── Console/Commands/      # Commands artisan
│   └── Mail/                  # Email templates
├── database/
│   ├── migrations/            # Migrations do banco
│   └── seeders/               # Dados iniciais
├── resources/
│   └── views/emails/          # Templates de e-mail
├── storage/
│   └── reports/               # Relatórios gerados
└── docs/
    └── Init.md                # Documento inicial de requisitos
```

### Fluxo de Dados

```
SearchRule → CombinatorService → ProcessFlightSearchJob
                                      ↓
                              Scraper (Mock/Skyscanner/Google)
                                      ↓
                              FlightPrice (banco)
                                      ↓
                              TextReportGenerator → TXT
                                      ↓
                              FlightSearchService → E-mail
```

## 📚 Documentação Adicional

- **[IMPLEMENTATION.md](IMPLEMENTATION.md)** - Detalhes técnicos da implementação
- **[docs/Init.md](docs/Init.md)** - Requisitos originais do projeto

## 🗺️ Roadmap

### Concluído ✅
- [x] Estrutura do banco de dados
- [x] Geração de combinações (96 voos)
- [x] MockScraper para testes
- [x] Relatórios em TXT
- [x] Envio de e-mail (SMTP)
- [x] Interface Filament

### Em Progresso 🚧
- [ ] SkyscannerScraper (dados reais)
- [ ] GoogleFlightsScraper (dados reais)
- [ ] Sistema de notificações
- [ ] Comparação de preços entre fontes

### Futuro 🔮
- [ ] Integração com APIs de voo
- [ ] Agendamento automático (cada 6h)
- [ ] Alertas de queda de preço
- [ ] Interface mobile
- [ ] Exportação para PDF
- [ ] Histórico de preços
- [ ] Comparador de companhias aéreas

## 🤝 Contribuindo

Contribuições são bem-vindas! Sinta-se à vontade para:

1. Fazer um Fork do projeto
2. Criar uma branch para sua feature (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abrir um Pull Request

## 📄 Licença

Este projeto está sob a licença MIT. Veja o arquivo [LICENSE.md](LICENSE.md) para mais detalhes.

## 👥 Autores

- **Seu Nome** - *Trabalho inicial* - [seu-perfil](https://github.com/seu-usuario)

## 🙏 Agradecimentos

- **Laravel** - O framework PHP excelente
- **Filament** - Painel administrativo elegante
- **Skyscanner** - Inspiration para scraping
- **Google Flights** - Reference para interfaces

---

**Feito com ❤️ para planejamento de viagens inesquecíveis!** ✈️