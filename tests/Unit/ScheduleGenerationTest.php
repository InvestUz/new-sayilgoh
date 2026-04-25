<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\Lot;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * To'lov grafigini yaratish (`Contract::generatePaymentSchedule`) testlari.
 *
 * Asosiy ishlab chiqarish qoidalari:
 *  1. Oyning 1-chi kunidan boshlanmagan shartnoma uchun BIRINCHI grafik
 *     pro-rata bo'yicha hisoblanadi (faol kunlar / oy kunlari).
 *  2. Birinchi grafikning tejovi qolgan oylarga teng taqsimlanadi.
 *  3. Birinchi grafikning `oxirgi_muddat = boshlanish_sanasi`.
 *  4. Oyning 1-chi kunidan boshlanmasa, hech qanday pro-rata bo'lmaydi.
 *  5. `birinchi_tolov_sanasi` qo'lda kiritilsa pro-rata QO'LLANILMAYDI.
 */
class ScheduleGenerationTest extends TestCase
{
    use RefreshDatabase;

    private function makeContract(array $overrides = []): Contract
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

        $defaults = [
            'lot_id' => $lot->id,
            'tenant_id' => $tenant->id,
            'shartnoma_raqami' => 'SH-' . uniqid(),
            'shartnoma_sanasi' => '2025-07-24',
            'auksion_sanasi' => '2025-07-01',
            'shartnoma_summasi' => 141536724,
            'yillik_ijara_haqi' => 141536724,
            'oylik_tolovi' => 11794727,
            'shartnoma_muddati' => 12,
            'boshlanish_sanasi' => '2025-07-24',
            'tugash_sanasi' => '2026-07-23',
            'birinchi_tolov_sanasi' => '2025-07-24',
            'tolov_kuni' => 10,
            'penya_muddati' => 0,
            'holat' => 'faol',
            'joriy_yil' => 2025,
        ];

        return Contract::create(array_merge($defaults, $overrides));
    }

    /** @test */
    public function pro_rata_first_schedule_uses_active_days_in_start_month(): void
    {
        $contract = $this->makeContract();
        $contract->generatePaymentSchedule();

        $first = $contract->paymentSchedules()->orderBy('oy_raqami')->first();

        $monthlyFull = round(141536724 / 12, 2); // 11 794 727
        $expected = round($monthlyFull * 8 / 31, 2); // 3 043 800,52

        $this->assertEquals($expected, round($first->tolov_summasi, 2));
        $this->assertEquals('2025-07-24', Carbon::parse($first->tolov_sanasi)->format('Y-m-d'));
        $this->assertEquals('2025-07-24', Carbon::parse($first->oxirgi_muddat)->format('Y-m-d'));
    }

    /** @test */
    public function remaining_schedules_split_savings_equally(): void
    {
        $contract = $this->makeContract();
        $contract->generatePaymentSchedule();

        $monthlyFull = round(141536724 / 12, 2);
        $first = round($monthlyFull * 8 / 31, 2);
        $savings = round($monthlyFull - $first, 2);
        $expectedRemaining = round((12 * $monthlyFull - $savings) / 11, 2); // 12 071 436,14

        $remaining = $contract->paymentSchedules()
            ->where('oy_raqami', '>', 1)
            ->get();

        $this->assertCount(11, $remaining);
        foreach ($remaining as $sch) {
            $this->assertEquals(
                $expectedRemaining,
                round($sch->tolov_summasi, 2),
                "Schedule #{$sch->oy_raqami} should be {$expectedRemaining}"
            );
        }
    }

    /** @test */
    public function remaining_schedules_use_payment_day_and_grace(): void
    {
        $contract = $this->makeContract(['penya_muddati' => 10]);
        $contract->generatePaymentSchedule();

        $second = $contract->paymentSchedules()->where('oy_raqami', 2)->first();

        $this->assertEquals('2025-08-10', Carbon::parse($second->tolov_sanasi)->format('Y-m-d'));
        $this->assertEquals('2025-08-20', Carbon::parse($second->oxirgi_muddat)->format('Y-m-d'));
    }

    /** @test */
    public function start_on_first_day_of_month_skips_pro_rata(): void
    {
        $contract = $this->makeContract([
            'boshlanish_sanasi' => '2025-08-01',
            'tugash_sanasi' => '2026-07-31',
            'birinchi_tolov_sanasi' => '2025-08-01',
        ]);
        $contract->generatePaymentSchedule();

        $monthlyFull = round(141536724 / 12, 2);

        $contract->paymentSchedules->each(function ($sch) use ($monthlyFull) {
            $this->assertEquals($monthlyFull, round($sch->tolov_summasi, 2));
        });
    }

    /** @test */
    public function birinchi_tolov_sanasi_field_is_ignored_for_schedule_generation(): void
    {
        // Legacy `birinchi_tolov_sanasi` maydoni grafik yaratishda hisobga olinmaydi —
        // birinchi grafik har doim `boshlanish_sanasi` bo'yicha hisoblanadi.
        $contract = $this->makeContract([
            'birinchi_tolov_sanasi' => '2025-08-03', // legacy: 10 ish kuni keyin
        ]);
        $contract->generatePaymentSchedule();

        $first = $contract->paymentSchedules()->orderBy('oy_raqami')->first();
        $monthlyFull = round(141536724 / 12, 2);
        $expected = round($monthlyFull * 8 / 31, 2);

        $this->assertEquals($expected, round($first->tolov_summasi, 2));
        $this->assertEquals('2025-07-24', Carbon::parse($first->tolov_sanasi)->format('Y-m-d'));
    }

    /** @test */
    public function total_amount_matches_user_formula(): void
    {
        $contract = $this->makeContract();
        $contract->generatePaymentSchedule();

        // Foydalanuvchi formulasi: jami = N × oylik − tejov
        // = 12 × 11 794 727 − 8 750 926,48 = 132 785 797,52 + birinchi 3 043 800,52 = ...
        // Aslida: (N × monthly_full − savings) bu QOLGAN summasi (N−1 grafik uchun)
        // jami = first + (N−1) × remaining = 3 043 800,52 + 11 × 12 071 436,14 = 135 829 598,06

        $total = $contract->paymentSchedules->sum('tolov_summasi');
        $this->assertEqualsWithDelta(135829598.06, round($total, 2), 0.5);
    }
}
