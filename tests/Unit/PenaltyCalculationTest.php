<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\Lot;
use App\Models\Payment;
use App\Models\PaymentSchedule;
use App\Models\Tenant;
use App\Services\PaymentApplicator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penya hisob-kitobi va saqlanishi uchun unit testlar.
 *
 * Testlar quyidagi ishlab chiqarish qoidalarini tekshiradi:
 *   1. Stavka: kuniga 0.4% (`PENYA_RATE = 0.004`).
 *   2. Chegara: fakt tushumning 50% (`MAX_PENYA_RATE = 0.5` × fakt).
 *   3. Penya faqat muddat o'tgan bo'lsa hisoblanadi (`tolovSanasi > oxirgi_muddat`).
 *   4. `penya_summasi` har qayta hisobda joriy formula natijasi bilan almashtiriladi.
 *   5. To'liq to'langan grafiklar uchun penya muzlatiladi (qayta hisoblanmaydi).
 *   6. `PaymentApplicator` faqat principal qarzga yo'naltiradi, penya yechmaydi.
 */
class PenaltyCalculationTest extends TestCase
{
    use RefreshDatabase;

    private function createContract(array $overrides = []): Contract
    {
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

        return Contract::create(array_merge([
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
            'tugash_sanasi' => '2030-12-31',
            'tolov_kuni' => 10,
            'penya_muddati' => 10,
            'holat' => 'faol',
            'joriy_yil' => 2025,
        ], $overrides));
    }

    private function createSchedule(array $overrides = []): PaymentSchedule
    {
        $contract = $this->createContract();

        return PaymentSchedule::create(array_merge([
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
            'holat' => 'kutilmoqda',
        ], $overrides));
    }

    // =========================================================================
    // 1. PENYA HISOBI — STAVKA, CHEGARA, MUDDAT
    // =========================================================================

    /** @test */
    public function early_or_on_time_check_does_not_create_penalty(): void
    {
        $schedule = $this->createSchedule(['oxirgi_muddat' => '2025-01-20']);

        $penya = $schedule->calculatePenyaAtDate(Carbon::parse('2025-01-15'), false);

        $this->assertSame(0.0, (float) $penya);
    }

    /** @test */
    public function late_check_creates_penalty_with_correct_formula(): void
    {
        $schedule = $this->createSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'tolangan_summa' => 1_000_000,
            'qoldiq_summa' => 1_000_000,
        ]);

        // 10 kun: fakt 1_000_000 * 0.004 * 10 = 40_000
        $penya = $schedule->calculatePenyaAtDate(Carbon::parse('2025-01-20'), false);

        $this->assertSame(40000.00, round((float) $penya, 2));
    }

    /** @test */
    public function no_fakt_means_zero_penalty_even_when_late(): void
    {
        $schedule = $this->createSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'tolangan_summa' => 0,
            'qoldiq_summa' => 1_000_000,
        ]);

        $penya = $schedule->calculatePenyaAtDate(Carbon::parse('2025-01-20'), false);

        $this->assertSame(0.0, (float) $penya);
    }

    /** @test */
    public function july_2025_example_uses_fakt_and_delay_days(): void
    {
        $schedule = $this->createSchedule([
            'oy' => 7,
            'yil' => 2025,
            'oy_raqami' => 7,
            'oxirgi_muddat' => '2025-07-24',
            'tolangan_summa' => 11_451_607,
            'qoldiq_summa' => 343_120,
        ]);

        // 4 kun: 11_451_607 * 4 * 0.004 = 183_225,712
        $penya = $schedule->calculatePenyaAtDate(Carbon::parse('2025-07-28'), false);

        $this->assertSame(183_225.71, round((float) $penya, 2));
    }

    /** @test */
    public function july_2025_penya_uses_payment_date_not_bugun(): void
    {
        $schedule = $this->createSchedule([
            'oy' => 7,
            'yil' => 2025,
            'oy_raqami' => 7,
            'oxirgi_muddat' => '2025-07-24',
            'tolangan_summa' => 11_451_607,
            'qoldiq_summa' => 343_120,
        ]);
        $contract = $schedule->contract;

        Payment::create([
            'contract_id' => $contract->id,
            'tolov_raqami' => Payment::generateTolovRaqami(),
            'tolov_sanasi' => '2025-07-28',
            'summa' => 11_451_607,
            'tolov_usuli' => 'bank_otkazmasi',
            'holat' => 'tasdiqlangan',
            'tasdiqlangan_sana' => now(),
        ]);

        $schedule->load('contract.payments');
        // 2026: hamon 4 kun (28.07 to'lov), bugun emas
        $penya = $schedule->calculatePenyaAtDate(Carbon::parse('2026-04-15'), false);

        $this->assertSame(183_225.71, round((float) $penya, 2));
    }

    /** @test */
    public function penalty_is_capped_at_50_percent_of_debt(): void
    {
        $schedule = $this->createSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'tolangan_summa' => 1_000_000,
            'qoldiq_summa' => 1_000_000,
        ]);

        // 200 kun: formula bo'yicha 800_000, lekin chegara 500_000
        $penya = $schedule->calculatePenyaAtDate(Carbon::parse('2025-01-10')->addDays(200), false);

        $this->assertSame(500000.00, round((float) $penya, 2));
    }

    // =========================================================================
    // 2. QAYTA HISOB — "ORQAGA" SANA
    // =========================================================================

    /** @test */
    public function penalty_recalc_as_of_earlier_date_uses_shorter_delay(): void
    {
        $schedule = $this->createSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'tolangan_summa' => 1_000_000,
            'qoldiq_summa' => 1_000_000,
        ]);

        $schedule->calculatePenyaAtDate(Carbon::parse('2025-02-09'), true);
        $this->assertSame(120000.00, round((float) $schedule->fresh()->penya_summasi, 2));

        // Eski sana: 5 kun kechikish → 20_000
        $schedule->calculatePenyaAtDate(Carbon::parse('2025-01-15'), true);
        $this->assertSame(20000.00, round((float) $schedule->fresh()->penya_summasi, 2));
    }

    /** @test */
    public function penalty_is_frozen_after_principal_is_fully_paid(): void
    {
        $schedule = $this->createSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'tolov_summasi' => 2_000_000,
            'tolangan_summa' => 1_000_000,
            'qoldiq_summa' => 1_000_000,
            'holat' => 'qisman_tolangan',
        ]);

        $schedule->calculatePenyaAtDate(Carbon::parse('2025-02-09'), true);
        $frozenPenya = (float) $schedule->fresh()->penya_summasi;
        $this->assertSame(120_000.0, round($frozenPenya, 2));

        $schedule->update(['tolangan_summa' => 2_000_000, 'qoldiq_summa' => 0, 'holat' => 'tolangan']);

        $schedule->calculatePenyaAtDate(Carbon::parse('2026-01-01'), true);
        $this->assertSame($frozenPenya, (float) $schedule->fresh()->penya_summasi);
    }

    /** @test */
    public function update_status_does_not_zero_out_penalty(): void
    {
        $schedule = $this->createSchedule([
            'oxirgi_muddat' => '2025-01-10',
            'qoldiq_summa' => 0,
            'tolangan_summa' => 1_000_000,
            'penya_summasi' => 50_000,
            'kechikish_kunlari' => 30,
        ]);

        $schedule->updateStatus();
        $schedule->save();

        $fresh = $schedule->fresh();
        $this->assertSame('tolangan', $fresh->holat);
        $this->assertSame(50000.00, round((float) $fresh->penya_summasi, 2));
        $this->assertSame(30, (int) $fresh->kechikish_kunlari);
    }

    // =========================================================================
    // 3. PAYMENT APPLICATOR — FAQAT PRINCIPAL
    // =========================================================================

    /** @test */
    public function applicator_routes_full_amount_to_principal_and_zero_to_penalty(): void
    {
        $contract = $this->createContract();

        PaymentSchedule::create([
            'contract_id' => $contract->id,
            'oy_raqami' => 1,
            'yil' => 2025,
            'oy' => 1,
            'tolov_sanasi' => '2025-01-01',
            'oxirgi_muddat' => '2025-01-10',
            'tolov_summasi' => 1_000_000,
            'tolangan_summa' => 0,
            'qoldiq_summa' => 1_000_000,
            'penya_summasi' => 0,
            'tolangan_penya' => 0,
            'kechikish_kunlari' => 0,
            'holat' => 'tolanmagan',
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'tolov_raqami' => Payment::generateTolovRaqami(),
            'tolov_sanasi' => '2025-02-09', // 30 kun kechikish
            'summa' => 1_000_000,
            'tolov_usuli' => 'bank_otkazmasi',
            'holat' => 'tasdiqlangan',
            'tasdiqlangan_sana' => now(),
        ]);

        $result = (new PaymentApplicator())->apply($payment, $contract);

        $this->assertSame(1000000.0, (float) $result['asosiy_qarz_uchun']);
        $this->assertSame(0.0, (float) $result['penya_uchun']);
        $this->assertSame(0.0, (float) $result['avans']);

        $schedule = $contract->paymentSchedules()->first()->fresh();
        $this->assertSame(0.0, (float) $schedule->tolangan_penya);
        $this->assertSame(1000000.0, (float) $schedule->tolangan_summa);
        $this->assertGreaterThan(0, (float) $schedule->penya_summasi);
    }

    /** @test */
    public function applicator_overpayment_goes_to_advance_balance(): void
    {
        $contract = $this->createContract();

        PaymentSchedule::create([
            'contract_id' => $contract->id,
            'oy_raqami' => 1,
            'yil' => 2025,
            'oy' => 1,
            'tolov_sanasi' => '2025-01-01',
            'oxirgi_muddat' => '2025-01-10',
            'tolov_summasi' => 500_000,
            'tolangan_summa' => 0,
            'qoldiq_summa' => 500_000,
            'penya_summasi' => 0,
            'tolangan_penya' => 0,
            'kechikish_kunlari' => 0,
            'holat' => 'kutilmoqda',
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'tolov_raqami' => Payment::generateTolovRaqami(),
            'tolov_sanasi' => '2025-01-05',
            'summa' => 700_000,
            'tolov_usuli' => 'bank_otkazmasi',
            'holat' => 'tasdiqlangan',
            'tasdiqlangan_sana' => now(),
        ]);

        $result = (new PaymentApplicator())->apply($payment, $contract);

        $this->assertSame(500000.0, (float) $result['asosiy_qarz_uchun']);
        $this->assertSame(0.0, (float) $result['penya_uchun']);
        $this->assertSame(200000.0, (float) $result['avans']);
        $this->assertSame(200000.0, (float) $contract->fresh()->avans_balans);
    }

    /** @test */
    public function applicator_is_idempotent_for_same_payment(): void
    {
        $contract = $this->createContract();

        PaymentSchedule::create([
            'contract_id' => $contract->id,
            'oy_raqami' => 1,
            'yil' => 2025,
            'oy' => 1,
            'tolov_sanasi' => '2025-01-01',
            'oxirgi_muddat' => '2025-01-10',
            'tolov_summasi' => 1_000_000,
            'tolangan_summa' => 0,
            'qoldiq_summa' => 1_000_000,
            'penya_summasi' => 0,
            'tolangan_penya' => 0,
            'kechikish_kunlari' => 0,
            'holat' => 'kutilmoqda',
        ]);

        $payment = Payment::create([
            'contract_id' => $contract->id,
            'tolov_raqami' => Payment::generateTolovRaqami(),
            'tolov_sanasi' => '2025-01-05',
            'summa' => 1_000_000,
            'tolov_usuli' => 'bank_otkazmasi',
            'holat' => 'tasdiqlangan',
            'tasdiqlangan_sana' => now(),
        ]);

        $applicator = new PaymentApplicator();
        $applicator->apply($payment, $contract);
        $applicator->apply($payment->fresh(), $contract);

        $schedule = $contract->paymentSchedules()->first()->fresh();
        $this->assertSame(1000000.0, (float) $schedule->tolangan_summa);
        $this->assertSame(0.0, (float) $schedule->qoldiq_summa);
    }

    // =========================================================================
    // 4. PENALTY DETAILS UCHUN UI
    // =========================================================================

    /** @test */
    public function penalty_details_returns_required_fields(): void
    {
        $schedule = $this->createSchedule();

        $details = $schedule->getPenaltyDetails(Carbon::parse('2025-01-15'));

        $this->assertArrayHasKey('overdue_days', $details);
        $this->assertArrayHasKey('penalty_rate', $details);
        $this->assertArrayHasKey('calculated_penalty', $details);
        $this->assertSame(0.4, $details['penalty_rate']);
        $this->assertIsInt($details['overdue_days']);
    }
}
