# ContractPeriodService Usage Examples

## Overview

The `ContractPeriodService` provides a **reusable, centralized way** to calculate contract periods and implement the **"current period by default, full data on demand"** pattern across your application.

## Why Use This Service?

✅ **DRY Principle**: Write period calculation logic once, use everywhere  
✅ **Consistent UX**: Same "current by default" pattern across all views  
✅ **Easy Filtering**: Simple API for dashboards, analytics, and monitoring  
✅ **Performance**: Efficient period grouping with caching support  

---

## 1. Controller Usage (Blade Views)

### Example: Lot Details Page

```php
// app/Http/Controllers/LotPageController.php
use App\Services\ContractPeriodService;

public function show(Lot $lot): View
{
    $lot->load(['contracts.tenant', 'contracts.paymentSchedules']);
    $activeContract = $lot->contracts->where('holat', 'faol')->first();

    if ($activeContract) {
        // Initialize service
        $periodService = ContractPeriodService::forContract($activeContract);
        
        // Get data for view
        $contractYearPeriods = $periodService->getAllPeriods();
        $currentPeriodNum = $periodService->getCurrentPeriodNum();
        $grandTotals = $periodService->getGrandTotals();
        $isContractExpired = $periodService->isContractExpired();
        $currentMonthYear = $periodService->getCurrentMonthYear();
        
        return view('lots.show', compact(
            'lot', 
            'activeContract',
            'contractYearPeriods',
            'currentPeriodNum',
            'grandTotals',
            'isContractExpired',
            'currentMonthYear'
        ));
    }
    
    return view('lots.show', compact('lot', 'activeContract'));
}
```

### Example: Contract Details Page

```php
// app/Http/Controllers/ContractPageController.php
use App\Services\ContractPeriodService;

public function show(Contract $contract): View
{
    $contract->load(['lot', 'tenant', 'paymentSchedules', 'payments']);

    // Use service for period calculations
    $periodService = ContractPeriodService::forContract($contract);
    $contractYearPeriods = $periodService->getAllPeriods();
    $currentPeriodNum = $periodService->getCurrentPeriodNum();
    // ... etc
    
    return view('contracts.show', compact('contract', ...));
}
```

---

## 2. API Usage (AJAX/JSON)

### Endpoint: `/api/contracts/{contract}/periods`

#### Get Current Period Only (Default)

```javascript
// Dashboard - show current period summary
fetch('/api/contracts/123/periods')
    .then(res => res.json())
    .then(data => {
        const current = data.data.current_period;
        console.log('Current period:', current.num);
        console.log('Period stats:', current.stats);
        console.log('Schedules:', current.schedules);
    });
```

**Response:**
```json
{
    "success": true,
    "data": {
        "current_period": {
            "num": 2,
            "start": "2025-10-08",
            "end": "2026-10-07",
            "is_current": true,
            "schedules": [...],
            "stats": {
                "total": 139382554,
                "paid": 50000000,
                "debt": 89382554,
                "penalty": 1500000,
                "percent": 35.9
            }
        },
        "current_period_num": 2,
        "meta": {
            "contract_id": 123,
            "contract_number": "SH-71",
            "is_expired": false,
            "current_month_year": {"month": 2, "year": 2026}
        }
    }
}
```

#### Get All Periods

```javascript
// Analytics - full historical data
fetch('/api/contracts/123/periods?period=all')
    .then(res => res.json())
    .then(data => {
        const periods = data.data.periods;
        const totals = data.data.grand_totals;
        
        console.log('All periods:', periods.length);
        console.log('Grand total:', totals.total);
        console.log('Total paid:', totals.paid);
    });
```

#### Get Specific Period

```javascript
// Filter by period number
fetch('/api/contracts/123/periods?period=1')
    .then(res => res.json())
    .then(data => {
        const period1 = data.data.period;
        console.log('Period 1 stats:', period1.stats);
    });
```

#### Get Detailed Data

```javascript
// Full service data including all calculations
fetch('/api/contracts/123/periods?period=all&format=detailed')
    .then(res => res.json())
    .then(data => {
        console.log('Full data:', data.data.full_data);
    });
```

---

## 3. Direct Service Usage (Advanced)

### In Custom Commands/Jobs

```php
use App\Services\ContractPeriodService;
use App\Models\Contract;

// Get contract
$contract = Contract::with('paymentSchedules')->find(123);

// Initialize service
$periodService = ContractPeriodService::forContract($contract);

// Get current period schedules only
$currentSchedules = $periodService->getCurrentPeriodSchedules();

// Process only current period
foreach ($currentSchedules as $schedule) {
    // Send reminders, calculate penalties, etc.
}

// Get grand totals for reporting
$totals = $periodService->getGrandTotals();
echo "Total debt: {$totals['debt']}";
echo "Overdue amount: {$totals['overdue']}";
```

### With Custom Reference Date

```php
use Carbon\Carbon;

// Analyze contract as of specific date
$historicalDate = Carbon::parse('2025-12-31');
$periodService = ContractPeriodService::forContract($contract, $historicalDate);

// Get what was current period on that date
$periodOnDate = $periodService->getCurrentPeriod();
```

### Export to Array (Serialization)

```php
// Get full data as array for JSON export or caching
$data = $periodService->toArray();

// Cache for 1 hour
Cache::put("contract_{$contract->id}_periods", $data, 3600);
```

---

## 4. Blade View Usage

### Show Current Period by Default

```blade
@if($currentPeriod = collect($contractYearPeriods)->firstWhere('num', $currentPeriodNum))
    <h3>To'lov jadvali (Joriy davr - {{ $currentPeriod['num'] }})</h3>
    <table>
        @foreach($currentPeriod['schedules'] as $schedule)
            <tr>
                <td>{{ $schedule->oy_nomi }}</td>
                <td>{{ number_format($schedule->tolov_summasi) }}</td>
                <!-- ... -->
            </tr>
        @endforeach
    </table>
@endif
```

### Collapsible Full History

```blade
<div x-data="{ showAll: false }">
    <!-- Current period shown above -->
    
    <button @click="showAll = !showAll">
        Barcha davrlar
    </button>
    
    <div x-show="showAll" x-collapse>
        @foreach($contractYearPeriods as $period)
            <h4>Davr {{ $period['num'] }}</h4>
            <p>Total: {{ number_format($period['stats']['total']) }}</p>
            <p>Paid: {{ number_format($period['stats']['paid']) }}</p>
            <!-- ... -->
        @endforeach
        
        <h4>JAMI</h4>
        <p>{{ number_format($grandTotals['total']) }}</p>
    </div>
</div>
```

---

## 5. Available Methods

### Core Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getAllPeriods()` | `array` | All contract periods with schedules & stats |
| `getCurrentPeriod()` | `?array` | Current period (or first if none current) |
| `getCurrentPeriodNum()` | `?int` | Current period number (1-based) |
| `getOtherPeriods()` | `array` | All periods except current |
| `getGrandTotals()` | `array` | Total across all periods |
| `getCurrentPeriodSchedules()` | `Collection` | Schedules for current period only |
| `isContractExpired()` | `bool` | Whether contract has ended |
| `getCurrentMonthYear()` | `array` | `['month' => 2, 'year' => 2026]` |
| `toArray()` | `array` | Full service data for JSON/caching |

### Factory Method

```php
// Static constructor
$service = ContractPeriodService::forContract($contract);

// With custom date
$service = ContractPeriodService::forContract($contract, $customDate);

// Or direct instantiation
$service = new ContractPeriodService($contract, $customDate);
```

---

## 6. Use Cases

### ✅ Dashboard (Current Monitoring)

Show only current period with option to expand:

```php
// Controller
$periodService = ContractPeriodService::forContract($contract);
$currentPeriod = $periodService->getCurrentPeriod();

// View: Show $currentPeriod by default, all periods in collapsible section
```

### ✅ Analytics (Period Comparison)

Compare performance across periods:

```php
$periodService = ContractPeriodService::forContract($contract);
$allPeriods = $periodService->getAllPeriods();

foreach ($allPeriods as $period) {
    echo "Period {$period['num']}: {$period['stats']['percent']}% paid\n";
}
```

### ✅ Reporting (Filtered Data)

Generate reports for specific periods:

```php
// Get period 1 data only
$period1 = collect($periodService->getAllPeriods())->firstWhere('num', 1);
$period1Schedules = $period1['schedules'];

// Generate report for period 1
```

### ✅ Notifications (Current Due Payments)

Send reminders only for current period:

```php
$currentSchedules = $periodService->getCurrentPeriodSchedules();
$overdueSchedules = $currentSchedules->filter(function($s) {
    return $s->qoldiq_summa > 0 && Carbon::parse($s->oxirgi_muddat)->isPast();
});

// Send reminders for overdue schedules
```

---

## 7. Period Stats Structure

Each period includes comprehensive statistics:

```php
$period = [
    'num' => 1,                          // Period number (1-based)
    'start' => Carbon::instance(),       // Period start date
    'end' => Carbon::instance(),         // Period end date
    'is_current' => true,                // Whether this is current period
    'schedules' => Collection::instance(), // Payment schedules
    'stats' => [
        'total' => 139382554,            // Total scheduled
        'paid' => 50000000,              // Amount paid
        'debt' => 89382554,              // Remaining debt
        'penalty' => 1500000,            // Unpaid penalty
        'overdue_count' => 3,            // Number of overdue schedules
        'paid_count' => 9,               // Number of paid schedules
        'total_count' => 12,             // Total schedules in period
        'percent' => 35.9,               // Payment completion %
    ]
];
```

---

## Best Practices

1. **Initialize once per request**: Create service instance in controller, pass data to view
2. **Cache when appropriate**: For heavy analytics, cache `toArray()` output
3. **Use current by default**: Always show current period first, full data on demand
4. **Leverage collections**: Service returns Collections for easy filtering/mapping
5. **Custom dates for audits**: Use custom reference date for historical analysis

---

## Summary

The `ContractPeriodService` provides:

- ✅ **Reusable period calculation logic**
- ✅ **"Current by default" pattern** for better UX
- ✅ **Flexible API** for various use cases
- ✅ **Consistent data structure** across application
- ✅ **Easy filtering** (current, all, specific period)
- ✅ **Works in controllers, APIs, commands, and jobs**

Use it anywhere you need contract period data! 🚀
