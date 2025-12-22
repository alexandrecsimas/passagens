# IMPLEMENTATION.md

## Flight Search System Implementation

### Overview
Complete flight search system for 9 people traveling from Brazil to Europe (Paris, London, Rome) with flexible dates and multi-source scraping.

### Features Implemented

#### 1. Database Structure
- **search_rules**: Configurable search rules with date ranges, origins, destinations
- **flight_searches**: Search execution records with status tracking
- **flight_prices**: Individual flight price results with detailed metadata
- **best_prices**: Table for storing best price combinations (future use)

#### 2. CombinatorService
- Generates all valid date combinations (13-16 nights)
- Creates traditional routes (round-trip from same airport)
- Creates open-jaw routes (depart from one city, return to another)
- Validates combinations against search rules
- Total combinations: 96 (8 date combos × 12 routes)

#### 3. Scraping Architecture
- **BaseScraper**: Abstract base class with common functionality
  - Price formatting
  - Nights calculation
  - Validation methods
  - User agent rotation
  - Random delays

- **MockScraper**: Test scraper with realistic price generation
  - Base price: R$ 7.500 per person
  - Destination multipliers: Paris +10%, London +15%, Rome +5%
  - Weekend variations
  - Airline selection based on destination

- **ScraperFactory**: Factory pattern for instantiating scrapers
  - Currently supports: mock
  - Prepared for: skyscanner, google_flights

#### 4. Job Processing
- **ProcessFlightSearchJob**: Queue job for processing individual searches
  - Configured for retry (3 attempts)
  - 120 second timeout
  - Unique ID based on combination + source

#### 5. FlightSearchService
- Orchestrates complete search workflow
- Generates combinations
- Dispatches jobs (synchronous for now)
- Updates final statistics
- Generates reports

#### 6. Report Generation
- **TextReportGenerator**: Creates detailed TXT reports
  - Top 20 best prices with medals
  - Statistics (average, min, max, variation)
  - Breakdown by origin and destination
  - Reports saved to storage/reports/

- **ExecutiveReportGenerator**: Creates executive summary reports
  - Top 5 best prices
  - Key statistics only
  - Optimized for email/WhatsApp

#### 7. Report Delivery System
- **EmailReportService**: Sends reports via email
  - Uses Laravel Mail
  - HTML email template with styling
  - TXT report attachment
  - Configurable recipients

- **WhatsAppReportService**: Sends reports via WhatsApp
  - Multiple providers supported:
    - **Callmebot**: Free, limited (default)
    - **Twilio**: Paid, reliable
    - **Evolution API**: Self-hosted, free
  - Compact message format (top 3 prices)
  - Markdown formatting for better readability

#### 8. Console Commands
- **flights:search**: Execute flight searches
  - Options: --rule-id, --source (mock|skyscanner|google_flights|all), --async
  - Displays search summary with best prices
  - Progress bar during processing

- **flights:report:send**: Send reports via email/WhatsApp
  - Options: --id, --email, --whatsapp, --to, --full
  - Manual report delivery
  - Executive or full report format

### Files Created/Modified

#### Services
- `app/Services/CombinatorService.php`
- `app/Services/FlightSearchService.php`
- `app/Services/Scraping/BaseScraper.php`
- `app/Services/Scraping/MockScraper.php`
- `app/Services/Scraping/ScraperFactory.php`
- `app/Services/Report/TextReportGenerator.php`
- `app/Services/Report/ExecutiveReportGenerator.php`
- `app/Services/Report/EmailReportService.php`
- `app/Services/Report/WhatsAppReportService.php`

#### Jobs
- `app/Jobs/ProcessFlightSearchJob.php`

#### Commands
- `app/Console/Commands/FlightsSearch.php`
- `app/Console/Commands/FlightsCombinations.php`
- `app/Console/Commands/FlightsReportSend.php`

#### Mail
- `app/Mail/FlightReportMail.php`

#### Views
- `resources/views/emails/flight-report.blade.php`

#### Config
- `config/reports.php`

#### DTOs
- `app/DTOs/FlightCombination.php`

#### Migrations
- `database/migrations/2025_12_22_184246_create_search_rules_table.php`
- `database/migrations/2025_12_22_184253_create_flight_searches_table.php`
- `database/migrations/2025_12_22_184303_create_flight_prices_table.php`
- `database/migrations/2025_12_22_184311_create_best_prices_table.php`

#### Models
- `app/Models/SearchRule.php`
- `app/Models/FlightSearch.php`
- `app/Models/FlightPrice.php`
- `app/Models/BestPrice.php`

#### Seeders
- `database/seeders/SearchRuleSeeder.php`

### Technical Details

#### Search Flow
1. Load SearchRule (configurable parameters)
2. Generate all valid combinations
3. For each combination and source:
   - Create job
   - Execute scraper
   - Save results
4. Update statistics
5. Generate report

#### Open-Jaw Routes
System supports open-jaw flights:
- Example: Depart GRU → Paris, return London → GRU
- Return origin tracked separately
- Correctly displayed in reports

#### Duration Calculation Fix
Fixed microsecond precision issue in SQLite:
- Uses timestamp comparison
- Absolute value to ensure positive duration
- Displayed in seconds

#### Synchronous Processing
Currently using synchronous job processing due to Laravel 12 limitations:
- Batchable trait not available
- Jobs processed in loop with progress bar
- Prepared for async migration when available

### Usage

```bash
# Run search with mock scraper
php artisan flights:search --source=mock

# Run search for specific rule
php artisan flights:search --rule-id=1 --source=mock

# View all combinations
php artisan flights:combinations

# View generated report
cat storage/reports/search_X_YYYYMMDD_HHMMSS.txt

# Send report via email
php artisan flights:report:send --email --latest

# Send report via WhatsApp
php artisan flights:report:send --whatsapp --latest

# Send via both email and WhatsApp
php artisan flights:report:send --email --whatsapp --latest

# Send full report (not executive summary)
php artisan flights:report:send --email --full --latest

# Send specific search
php artisan flights:report:send --id=5 --email
```

### Report Configuration

Configure email and WhatsApp settings in `.env`:

```bash
# Email Configuration
REPORTS_EMAIL_ENABLED=true
REPORTS_EMAIL_TO="your-email@example.com"
REPORTS_EMAIL_CC="cc@example.com"

# WhatsApp Configuration (Callmebot - Free)
REPORTS_WHATSAPP_ENABLED=true
REPORTS_WHATSAPP_TO="+5511999999999"
REPORTS_WHATSAPP_PROVIDER=callmebot
CALLMEBOT_API_KEY="your-api-key"

# WhatsApp Configuration (Twilio - Paid)
REPORTS_WHATSAPP_PROVIDER=twilio
TWILIO_ACCOUNT_SID="your-sid"
TWILIO_AUTH_TOKEN="your-token"
TWILIO_WHATSAPP_FROM="+14155238886"
```

### WhatsApp Providers

#### Callmebot (Free, Limited)
1. Visit https://callmebot.com/
2. Register your WhatsApp number
3. Get your API key
4. Limited to X messages per day

#### Twilio (Paid, Reliable)
1. Create account at https://www.twilio.com/
2. Get WhatsApp sandbox number
3. Configure credentials in `.env`
5. Cost: ~$0.005 per message

#### Evolution API (Self-Hosted, Free)
1. Docker-based WhatsApp API
2. Host on your own server
3. Unlimited messages
4. Requires setup and maintenance

### Database Seeding

```bash
# Seed search rules
php artisan db:seed --class=SearchRuleSeeder

# Run search
php artisan flights:search --source=mock
```

### Report Sample
Report includes:
- Search metadata (rule, date, duration, combinations, results)
- Top 20 prices with route details
- Statistics (average, min, max, variation %)
- Breakdown by origin (GRU, GIG)
- Breakdown by destination (Paris, London, Rome)

### Future Enhancements
- Async job processing with queues
- Real scrapers (Skyscanner, Google Flights)
- PDF report generation
- Scheduler for automatic searches every 6 hours
- Filament admin interface
- Email notifications for price drops
- Price history tracking
- Multi-source comparison
- Currency conversion
- Real-time API integration