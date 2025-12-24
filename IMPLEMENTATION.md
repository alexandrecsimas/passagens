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
  - Currently supports: mock, skyscanner, google_flights (see below)

- **SkyscannerScraper**: Real-world scraper implementation (BLOCKED by anti-bot)
  - Implemented with Guzzle HTTP and retry logic
  - **STATUS**: Non-functional due to PerimeterX/Cloudflare protection
  - Skyscanner uses advanced bot detection with CAPTCHA
  - Returns HTTP 307 redirect to `/sttc/px/captcha-v2/index.html`
  - Requires alternative approach (official API or headless browser)

- **GoogleFlightsScraper**: Headless browser scraper using Playwright for PHP
  - **STATUS**: ✅ TESTED AND WORKING - Successfully extracting prices
  - Uses Playwright PHP package (playwright-php/playwright ^1.1)
  - Launches headless Chromium browser
  - Handles JavaScript rendering and dynamic content
  - CSS selectors for price extraction (.yR1fYc, .YMlIz.FpEdX span)
  - Anti-detection features:
    - Realistic user agent rotation
    - Brazilian locale and timezone (America/Sao_Paulo)
    - Random delays between requests (slowMo: 100ms)
  - Price extraction with Unicode support:
    - Handles Brazilian price format (R$ 4.342)
    - Removes R$ symbol and Unicode non-breaking spaces (U+00A0)
    - Removes thousand separators (.)
    - Converts to float for database storage
    - **Critical fix**: Uses `/u` modifier in regex for proper Unicode whitespace handling
  - URL format: `https://www.google.com/travel/flights?q=Flights+from+{ORIGIN}+to+{DEST}&curr=BRL&departure={DATE}&return={DATE}`
  - Extracts: price, airline, connections, baggage info, flight URL
  - **Installation**: `composer require playwright-php/playwright` (already in composer.json)
  - **Docker**: Chromium and dependencies installed in Dockerfile
  - **Test result**: Successfully extracted R$ 4.342 for GRU → CDG route (July 2026)

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
- `app/Services/Scraping/SkyscannerScraper.php`
- `app/Services/Scraping/GoogleFlightsScraper.php` (NEW)
- `app/Services/Scraping/ScraperFactory.php` (UPDATED)
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

### Web Scraping Challenges

#### Skyscanner Anti-Bot Protection
- **Protection**: PerimeterX/HUMAN bot detection
- **Symptom**: HTTP 307 redirect to CAPTCHA page (`/sttc/px/captcha-v2/index.html`)
- **Response**: Sets `_pxhd` cookie for tracking
- **Impact**: Simple HTTP requests are blocked and require CAPTCHA solving

#### Alternative Approaches for Real Data

1. **Official APIs** (Recommended for production)
   - **Amadeus API**: https://developers.amadeus.com/
     - Flight search, pricing, booking
     - Free tier available (test environment)
     - Production requires approval

   - **Duffel API**: https://www.duffel.com/
     - Modern API for flights
     - Good free tier for development
     - Simple pricing model

   - **Skyscanner API**: https://developers.skyscanner.net/
     - Official partner API
     - Requires registration and approval
     - May have usage limits

2. **Third-Party Scraping Services**
   - **Apify**: https://apify.com/
     - Ready-made Skyscanner scrapers
     - Handles CAPTCHA and proxies
     - Pay-per-use pricing

   - **Oxylabs**: https://oxylabs.io/
     - Web scraping solutions
     - Residential proxies included
     - Enterprise pricing

   - **Piloterr**: https://www.piloterr.com/
     - Skyscanner Search Scraper API
     - Free trial available
     - Simple REST API

3. **Headless Browsers** (COMPLETED - GoogleFlightsScraper)
   - ✅ **GoogleFlightsScraper**: Implemented with Playwright for PHP
   - Launches Chromium headless browser
   - Handles JavaScript rendering
   - CSS selectors for data extraction
   - Anti-detection features (user agent, locale, delays)
   - **Status**: Implemented, awaiting testing
   - Requires: `composer install` (playwright-php package already added)

   **Note**: SkyscannerScraper remains blocked, but Google Flights may have lighter protection

#### Current Status
- **MockScraper**: ✅ Working - used for testing and development
- **SkyscannerScraper**: ❌ Blocked by anti-bot protection (kept for future attempts)
- **GoogleFlightsScraper**: ✅ **FULLY FUNCTIONAL** - Successfully extracting real prices from Google Flights
- **API Integration**: 🔮 Future consideration

### Future Enhancements
- [x] Try Google Flights scraper (may have less protection) ✅ COMPLETED
- [x] Test GoogleFlightsScraper with real searches ✅ COMPLETED
- [ ] Run full search with all combinations using GoogleFlightsScraper
- [ ] Evaluate API integration (Amadeus, Duffel)
- [ ] Consider third-party scraping service for production
- [ ] Async job processing with queues
- [ ] PDF report generation
- [ ] Scheduler for automatic searches every 6 hours
- [ ] Filament admin interface
- [ ] Email notifications for price drops
- [ ] Price history tracking
- [ ] Multi-source comparison
- [ ] Currency conversion