<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Tenant;
use App\Services\PenaltyCalculatorService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for penalty calculation following contract rules (Section 8.2)
 * 
 * Business Rules Tested:
 * 1. Penalty only if payment_date > due_date
 * 2. Formula: penalty = overdue_amount * 0.004 * overdue_days
 * 3. Cap: penalty <= overdue_amount * 0.5
 * 4. Each month calculated independently
 * 5. Allocation: penalty -> rent -> advance
 */
class PenaltyCalculationTest extends TestCase
{
    use RefreshDatabase;

    protected PenaltyCalculatorService $penaltyService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->penaltyService = new PenaltyCalculatorService();
    }

    /**
     * Helper to create test contract with required relations
     */
    private function createTestContract(): Contract
    {
        // Create minimal required data
        $lot = Lot::create([
            'lot_raqami' => 'TEST-' . uniqid(),
            'obyekt_nomi' => 'Test Lot',
            'manzil' => 'Test Address',
            'tuman' => 'Yashnobod',
            'maydon' => 100,
            'boshlangich_narx' => 1000000,
            'obyekt_turi' => 'savdo',
            'holat' => 'ijarada',
            'is_active' => true,
        ]);

        $tenant = Tenant::create([
            'name' => 'Test Tenant',
            'type' => 'yuridik',
            'inn' => uniqid() . rand(1000, 9999),
            'director_name' => 'Test Director',
            'phone' => '+998901234567',
            'address' => 'Test Address, Tashkent',
            'is_active' => true,
        ]);

        return Contract::create([
            'lot_id' => $lot->id,
            'tenant_id' => $tenant->id,
            'shartnoma_raqami' => 'SH-TEST-' . uniqid(),
            'shartnoma_sanasi' => '2025-01-01',
            'auksion_sanasi' => '2024-12-25',
            'birinchi_tolov_sanasi' => '2025-01-15',
            'shartnoma_summasi' => 12000000,
            'oylik_tolovi' => 1000000,
            'shartnoma_muddati' => 12,
            'boshlanish_sanasi' => '2025-01-01',
            'tugash_sanasi' => '2025-12-31',
            'tolov_kuni' => 10,
            'penya_muddati' => 10,
            'holat' => 'faol',
            'joriy_yil' => 2025,
        ]);
    }

    /**
     * Helper to create a test schedule
     */
    private function createTestSchedule(array $attributes = []): PaymentSchedule
    {
        $contract = $this->createTestContract();
        
        return PaymentSchedule::create(array_merge([
            'contract_id' => $contract->id,
            'oy_raqami' => 1,
            'yil' => 2025,
            'oy' => 1,
            'tolov_sanasi' => '2025-01-01',
            'oxirgi_muddat' => '2025-01-10', // Due date (deadline for penalty-free payment)
            'tolov_summasi' => 1000000, // 1 million UZS
            'tolangan_summa' => 0,
            'qoldiq_summa' => 1000000,
            'penya_summasi' => 0,
            'tolangan_penya' => 0,
            'kechikish_kunlari' => 0,
            'holat' => 'kutilmoqda',
        ], $attributes));
    }

    // =========================================================================
    // TEST RULE 1: Penalty only if payment_date > due_date
    // =========================================================================

    /** @test */
    public function early_payment_generates_no_penalty(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-20',
            'qoldiq_summa' => 1000000,
        ]);

        // Payment 5 days BEFORE due date
        $paymentDate = Carbon::parse('2025-01-15');
        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        $this->assertEquals(0, $result['overdue_days']);
        $this->assertEquals(0, $result['calculated_penalty']);
        $this->assertEquals(0.4, $result['penalty_rate']); // Rate is always shown
    }

    /** @test */
    public function on_time_payment_generates_no_penalty(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-20',
            'qoldiq_summa' => 1000000,
        ]);

        // Payment exactly ON due date
        $paymentDate = Carbon::parse('2025-01-20');
        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        $this->assertEquals(0, $result['overdue_days']);
        $this->assertEquals(0, $result['calculated_penalty']);
    }

    /** @test */
    public function late_payment_generates_penalty(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-20',
            'qoldiq_summa' => 1000000,
        ]);

        // Payment 10 days AFTER due date
        $paymentDate = Carbon::parse('2025-01-30');
        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        $this->assertEquals(10, $result['overdue_days']);
        $this->assertGreaterThan(0, $result['calculated_penalty']);
    }

    // =========================================================================
    // TEST RULE 2: penalty = overdue_amount * 0.004 * overdue_days
    // =========================================================================

    /** @test */
    public function penalty_formula_is_correct(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 1000000, // 1 million UZS
        ]);

        // Payment 10 days late
        $paymentDate = Carbon::parse('2025-01-20');
        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        // Expected: 1,000,000 * 0.004 * 10 = 40,000 UZS
        $expectedPenalty = 1000000 * 0.004 * 10;
        
        $this->assertEquals(10, $result['overdue_days']);
        $this->assertEquals($expectedPenalty, $result['calculated_penalty']);
    }

    /** @test */
    public function penalty_formula_with_different_amounts(): void
    {
        // Test with 5 million UZS, 15 days late
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 5000000,
        ]);

        $paymentDate = Carbon::parse('2025-01-25'); // 15 days late
        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        // Expected: 5,000,000 * 0.004 * 15 = 300,000 UZS
        $expectedPenalty = 5000000 * 0.004 * 15;
        
        $this->assertEquals(15, $result['overdue_days']);
        $this->assertEquals($expectedPenalty, $result['calculated_penalty']);
    }

    // =========================================================================
    // TEST RULE 3: penalty <= overdue_amount * 0.5 (cap at 50%)
    // =========================================================================

    /** @test */
    public function penalty_is_capped_at_50_percent(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 1000000,
        ]);

        // Payment 200 days late (would exceed 50% without cap)
        // Without cap: 1,000,000 * 0.004 * 200 = 800,000 (80%)
        // With cap: 1,000,000 * 0.5 = 500,000 (50%)
        $paymentDate = Carbon::parse('2025-07-29'); // ~200 days later
        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        $maxPenalty = 1000000 * 0.5; // 500,000
        
        $this->assertLessThanOrEqual($maxPenalty, $result['calculated_penalty']);
        $this->assertEquals($maxPenalty, $result['calculated_penalty']);
        $this->assertTrue($result['penalty_cap_applied']);
    }

    /** @test */
    public function penalty_cap_exactly_at_125_days(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 1000000,
        ]);

        // At 125 days: 1,000,000 * 0.004 * 125 = 500,000 = exactly 50%
        $paymentDate = Carbon::parse('2025-01-10')->addDays(125);
        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        // Should be exactly at cap
        $this->assertEquals(500000, $result['calculated_penalty']);
    }

    // =========================================================================
    // TEST RULE 4: overdue_days = max(0, payment_date - due_date)
    // =========================================================================

    /** @test */
    public function overdue_days_calculation_is_correct(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
        ]);

        // Test various dates
        $testCases = [
            ['date' => '2025-01-05', 'expected_days' => 0], // 5 days early
            ['date' => '2025-01-10', 'expected_days' => 0], // On time
            ['date' => '2025-01-11', 'expected_days' => 1], // 1 day late
            ['date' => '2025-01-20', 'expected_days' => 10], // 10 days late
            ['date' => '2025-02-10', 'expected_days' => 31], // 31 days late
        ];

        foreach ($testCases as $case) {
            $paymentDate = Carbon::parse($case['date']);
            $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);
            
            $this->assertEquals(
                $case['expected_days'], 
                $result['overdue_days'],
                "Failed for date {$case['date']}"
            );
        }
    }

    // =========================================================================
    // TEST RULE 5: Each month calculated independently
    // =========================================================================

    /** @test */
    public function each_month_penalty_is_independent(): void
    {
        $contract = $this->createTestContract();

        // Create two months with different due dates
        $month1 = PaymentSchedule::create([
            'contract_id' => $contract->id,
            'oy_raqami' => 1,
            'yil' => 2025,
            'oy' => 1,
            'tolov_sanasi' => '2025-01-01',
            'oxirgi_muddat' => '2025-01-10',
            'tolov_summasi' => 1000000,
            'tolangan_summa' => 0,
            'qoldiq_summa' => 1000000,
            'penya_summasi' => 0,
            'tolangan_penya' => 0,
            'kechikish_kunlari' => 0,
            'holat' => 'tolanmagan',
        ]);

        $month2 = PaymentSchedule::create([
            'contract_id' => $contract->id,
            'oy_raqami' => 2,
            'yil' => 2025,
            'oy' => 2,
            'tolov_sanasi' => '2025-02-01',
            'oxirgi_muddat' => '2025-02-10',
            'tolov_summasi' => 1000000,
            'tolangan_summa' => 0,
            'qoldiq_summa' => 1000000,
            'penya_summasi' => 0,
            'tolangan_penya' => 0,
            'kechikish_kunlari' => 0,
            'holat' => 'kutilmoqda',
        ]);

        // Pay on January 20 - month1 is 10 days late, month2 is early
        $paymentDate = Carbon::parse('2025-01-20');

        $result1 = $this->penaltyService->calculatePenaltyForSchedule($month1, $paymentDate);
        $result2 = $this->penaltyService->calculatePenaltyForSchedule($month2, $paymentDate);

        // Month 1: 10 days late, should have penalty
        $this->assertEquals(10, $result1['overdue_days']);
        $this->assertEquals(40000, $result1['calculated_penalty']); // 1M * 0.004 * 10

        // Month 2: Early (due Feb 10), should have NO penalty
        $this->assertEquals(0, $result2['overdue_days']);
        $this->assertEquals(0, $result2['calculated_penalty']);
    }

    // =========================================================================
    // TEST RULE 7: Monthly details must show overdue_days, penalty_rate, calculated_penalty
    // =========================================================================

    /** @test */
    public function monthly_details_never_null(): void
    {
        $schedule = $this->createTestSchedule();
        $paymentDate = Carbon::parse('2025-01-05'); // Early payment

        $result = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        // Rule 7: No empty or NULL fields allowed
        $this->assertIsInt($result['overdue_days']);
        $this->assertIsFloat($result['penalty_rate']);
        $this->assertIsFloat($result['calculated_penalty']);
        
        $this->assertNotNull($result['overdue_days']);
        $this->assertNotNull($result['penalty_rate']);
        $this->assertNotNull($result['calculated_penalty']);
    }

    /** @test */
    public function penalty_details_method_returns_required_fields(): void
    {
        $schedule = $this->createTestSchedule();
        
        $details = $schedule->getPenaltyDetails(Carbon::parse('2025-01-15'));

        // Verify all required fields exist and are not null
        $this->assertArrayHasKey('overdue_days', $details);
        $this->assertArrayHasKey('penalty_rate', $details);
        $this->assertArrayHasKey('calculated_penalty', $details);
        
        $this->assertIsInt($details['overdue_days']);
        $this->assertIsFloat($details['penalty_rate']);
        $this->assertIsNumeric($details['calculated_penalty']);
    }

    // =========================================================================
    // TEST PAYMENT ALLOCATION
    // =========================================================================

    /** @test */
    public function payment_allocation_skips_penalty_when_zero(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-20',
            'qoldiq_summa' => 1000000,
        ]);

        // Pay early - no penalty
        $paymentDate = Carbon::parse('2025-01-15');
        $result = $schedule->applyPayment(1000000, $paymentDate);

        // All should go to principal, none to penalty
        $this->assertEquals(0, $result['penya_tolangan']);
        $this->assertEquals(1000000, $result['asosiy_tolangan']);
        $this->assertEquals(0, $result['qoldiq']);
    }

    /** @test */
    public function payment_allocation_order_penalty_then_principal(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 1000000,
        ]);

        // Pay 10 days late
        $paymentDate = Carbon::parse('2025-01-20');
        
        // Pay full amount + penalty
        $penalty = 1000000 * 0.004 * 10; // 40,000
        $totalDue = 1000000 + $penalty;
        
        $result = $schedule->applyPayment($totalDue, $paymentDate);

        // Penalty should be paid first
        $this->assertEquals($penalty, $result['penya_tolangan']);
        $this->assertEquals(1000000, $result['asosiy_tolangan']);
        $this->assertEquals(0, $result['qoldiq']);
    }

    /** @test */
    public function partial_payment_pays_penalty_first(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 1000000,
        ]);

        // Pay 10 days late, but only pay 50,000
        $paymentDate = Carbon::parse('2025-01-20');
        $penalty = 1000000 * 0.004 * 10; // 40,000
        
        $result = $schedule->applyPayment(50000, $paymentDate);

        // Should pay all penalty (40,000) then 10,000 to principal
        $this->assertEquals(40000, $result['penya_tolangan']);
        $this->assertEquals(10000, $result['asosiy_tolangan']);
        $this->assertEquals(0, $result['qoldiq']);
    }

    /** @test */
    public function overpayment_returns_remaining_for_advance(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-20',
            'qoldiq_summa' => 500000,
        ]);

        // Pay early, more than due
        $paymentDate = Carbon::parse('2025-01-15');
        $result = $schedule->applyPayment(700000, $paymentDate);

        // Should pay all principal, return extra
        $this->assertEquals(0, $result['penya_tolangan']);
        $this->assertEquals(500000, $result['asosiy_tolangan']);
        $this->assertEquals(200000, $result['qoldiq']); // Goes to advance
    }

    // =========================================================================
    // TEST EDGE CASES
    // =========================================================================

    /** @test */
    public function zero_amount_payment_does_nothing(): void
    {
        $schedule = $this->createTestSchedule([
            'qoldiq_summa' => 1000000,
        ]);

        $result = $schedule->applyPayment(0, Carbon::today());

        $this->assertEquals(0, $result['penya_tolangan']);
        $this->assertEquals(0, $result['asosiy_tolangan']);
        $this->assertEquals(0, $result['qoldiq']);
    }

    /** @test */
    public function already_paid_schedule_has_no_new_penalty(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 0, // Already paid
            'tolangan_summa' => 1000000,
            'holat' => 'tolangan',
        ]);

        $paymentDate = Carbon::parse('2025-01-20'); // Would be late
        $penalty = $schedule->calculatePenyaAtDate($paymentDate, false);

        // Already paid schedules keep their existing penalty
        $this->assertEquals($schedule->penya_summasi, $penalty);
    }

    /** @test */
    public function penalty_calculation_is_deterministic(): void
    {
        $schedule = $this->createTestSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 1000000,
        ]);

        $paymentDate = Carbon::parse('2025-01-20');

        // Calculate multiple times
        $result1 = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);
        $result2 = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);
        $result3 = $this->penaltyService->calculatePenaltyForSchedule($schedule, $paymentDate);

        // All should be identical
        $this->assertEquals($result1['calculated_penalty'], $result2['calculated_penalty']);
        $this->assertEquals($result2['calculated_penalty'], $result3['calculated_penalty']);
        $this->assertEquals($result1['overdue_days'], $result2['overdue_days']);
    }
}
