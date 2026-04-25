# Ijaraga olingan ob'ektlar (Sayilgoh) — boshqaruv tizimi

Laravel asosida qurilgan, lot/ijarachi/shartnoma/to'lov bo'yicha to'liq
hisob yurituvchi web-tizim. Penya hisob-kitobi shartnoma 8.2-bandiga to'liq
mos keladi va to'lovlar tarixi yo'qolmaydigan holatda saqlanadi.

## Asosiy imkoniyatlar

- **Lot/ijarachi/shartnoma** — yagona registry orqali CRUD.
- **To'lov grafigi** — har shartnoma uchun avtomatik oylik grafik.
- **FAKT to'lovlar** — `PaymentApplicator` orqali FIFO tartibida faqat
  asosiy qarzga (principal) yo'naltiriladi. Ortig'i — avans balansga.
- **Penya** — kuniga 0.4%, qarzning 50%i bilan cheklangan. Penya
  **MONOTON** o'sadi va to'liq to'langan oylar uchun **muzlatiladi**
  (yo'qolmaydi).
- **Penya to'lovi alohida** — `/api/penalty-payments` endpointi yoki
  UI'dagi "Penya to'lash" tugmasi orqali kiritiladi.
- **Joriy oy paneli** — dashboard va lot ko'rish sahifalarida shu oy
  uchun reja, to'langan, qarz va penya alohida ko'rsatiladi.
- **Penya kalkulyatori va bildirgnomalar** — PDF eksport bilan.
- **Calendar / Analytics** — to'lov yig'imi va statistikasi.

## Texnik to'plam

- PHP 8.2+, Laravel 11, MySQL/MariaDB
- Tailwind CSS, Alpine.js, Chart.js
- Vite (frontend build)
- PHPUnit (unit testlar)

## O'rnatish

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

## Asosiy katalog tuzilishi

| Yo'l | Vazifasi |
|------|----------|
| `app/Http/Controllers/WebController.php` | Blade sahifalar (registry, dashboard, lot show) |
| `app/Http/Controllers/Api/*` | RESTful API: tenants, lots, contracts, payments |
| `app/Http/Controllers/PenaltyController.php` | Penya kalkulyator, bildirgnoma, audit |
| `app/Services/PaymentApplicator.php` | To'lov taqsimotining YAGONA markazi |
| `app/Services/ScheduleDisplayService.php` | Grafik UI uchun ma'lumot tayyorlash |
| `app/Services/ContractPeriodService.php` | 12 oylik davrlarga bo'lish |
| `app/Services/PenaltyNotificationService.php` | Penya bildirgnomalar |
| `app/Models/PaymentSchedule.php` | Penya hisobi va saqlash mantiqi |
| `resources/views/blade/*` | Asosiy CRUD sahifalari |
| `resources/views/data-center.blade.php` | Bosh dashboard |

## Penya bo'yicha qoidalar (qisqa)

1. Penya faqat `oxirgi_muddat` o'tgandan keyin yig'iladi.
2. Formula: `qoldiq * 0.004 * kechikish_kunlari`, maksimum `qoldiq * 0.5`.
3. Yangi hisoblangan qiymat eskidan past bo'lsa — eski saqlanadi
   (monoton o'sish).
4. Asosiy qarz to'liq to'langanidan keyin penya **muzlatiladi** va
   faqat `/api/penalty-payments` orqali yopiladi.
5. Oddiy to'lov AVTOMATIK ravishda penyani yopmaydi — bu siyosat
   tasodifiy taqsimotning oldini oladi.

## Foydali artisan komandalar

```bash
php artisan penalties:recalculate              # Aktiv shartnomalar penyasini qayta hisoblash
php artisan penalties:recalculate --dry-run    # DBga yozmasdan ko'rib chiqish
php artisan penalties:recalculate --contract=5 # Faqat bitta shartnoma uchun
```

## Testlar

```bash
php artisan test --filter=PenaltyCalculationTest
```
