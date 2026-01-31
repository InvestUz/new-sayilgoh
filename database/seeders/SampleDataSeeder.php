<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant;
use App\Models\Lot;
use App\Models\Contract;
use App\Models\PaymentSchedule;
use App\Models\Payment;
use Carbon\Carbon;

/**
 * Professional Sample Data Seeder
 *
 * QARZ HISOBLASH FORMULASI:
 * ═══════════════════════════════════════════════════════════════════════
 * 1. JAMI TO'LANGAN = SUM(tolangan_summa) - Haqiqatda to'langan pul
 * 2. MUDDATI O'TGAN QARZ = SUM(qoldiq_summa) WHERE oxirgi_muddat < bugun
 *    - To'lov muddati o'tgan, lekin to'lanmagan summa (HAQIQIY QARZ)
 * 3. MUDDATI O'TMAGAN QARZ = SUM(qoldiq_summa) WHERE oxirgi_muddat >= bugun
 *    - Hali to'lov muddati kelmagan summa
 * 4. PENYA = qoldiq_summa × 0.4% × kechikish_kunlari (max 50%)
 * ═══════════════════════════════════════════════════════════════════════
 */
class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $bugun = Carbon::today();

        // ========================================
        // IJARACHI 1: 2 TA LOTGA EGA (YAXSHI TO'LOVCHI)
        // ========================================

        $tenant1 = Tenant::create([
            'name' => 'ASIA TRADE GROUP MCHJ',
            'type' => 'yuridik',
            'inn' => '309876543',
            'director_name' => 'Karimov Jasur Abdullayevich',
            'passport_serial' => 'AB 1234567',
            'phone' => '+998 90 123 45 67',
            'email' => 'info@asiatrade.uz',
            'address' => 'Toshkent shahar, Mirzo Ulug\'bek tumani, Buyuk Ipak Yo\'li ko\'chasi, 15-uy',
            'bank_name' => 'KAPITALBANK ATB',
            'bank_account' => '20208000100123456001',
            'bank_mfo' => '01058',
            'oked' => '47190',
            'is_active' => true,
        ]);

        // Lot 20 - Birinchi savdo do'koni
        $lot20 = Lot::create([
            'lot_raqami' => '20',
            'obyekt_nomi' => 'Savdo do\'koni №20',
            'manzil' => 'Toshkent shahar, Yashnobod tumani, Ohangaron shoh ko\'chasi, 40/20-uy',
            'tuman' => 'Yashnobod tumani',
            'kocha' => 'Ohangaron shoh ko\'chasi',
            'uy_raqami' => '40/20',
            'maydon' => 85.50,
            'tavsif' => 'Yaxshi holatda, markaziy joyda joylashgan savdo do\'koni.',
            'obyekt_turi' => 'savdo',
            'latitude' => 41.311081,
            'longitude' => 69.279737,
            'boshlangich_narx' => 50000000,
            'holat' => 'ijarada',
            'is_active' => true,
        ]);

        // Lot 35 - Ikkinchi savdo do'koni
        $lot35 = Lot::create([
            'lot_raqami' => '35',
            'obyekt_nomi' => 'Savdo do\'koni №35',
            'manzil' => 'Toshkent shahar, Yashnobod tumani, Ohangaron shoh ko\'chasi, 40/35-uy',
            'tuman' => 'Yashnobod tumani',
            'kocha' => 'Ohangaron shoh ko\'chasi',
            'uy_raqami' => '40/35',
            'maydon' => 120.00,
            'tavsif' => 'Katta maydoni bor, vitrina oynalari bilan.',
            'obyekt_turi' => 'savdo',
            'latitude' => 41.312000,
            'longitude' => 69.280500,
            'boshlangich_narx' => 75000000,
            'holat' => 'ijarada',
            'is_active' => true,
        ]);

        // Shartnoma 1 - 2024 yil iyuldan boshlangan (18 oy o'tdi)
        $contract1 = Contract::create([
            'lot_id' => $lot20->id,
            'tenant_id' => $tenant1->id,
            'shartnoma_raqami' => 'SH-2024-020',
            'shartnoma_sanasi' => Carbon::parse('2024-07-15'),
            'auksion_sanasi' => Carbon::parse('2024-07-10'),
            'auksion_bayonnoma_raqami' => 'AUK-2024-0020',
            'auksion_xarajati' => 600000,
            'shartnoma_summasi' => 60000000,
            'oylik_tolovi' => 1000000,
            'shartnoma_muddati' => 60,
            'boshlanish_sanasi' => Carbon::parse('2024-08-01'),
            'tugash_sanasi' => Carbon::parse('2029-07-31'),
            'birinchi_tolov_sanasi' => Carbon::parse('2024-07-29'),
            'dalolatnoma_raqami' => 'DAL-2024-020',
            'dalolatnoma_sanasi' => Carbon::parse('2024-07-28'),
            'dalolatnoma_holati' => 'topshirilgan',
            'holat' => 'faol',
            'izoh' => 'Lot 20 - ASIA TRADE kompaniyasi',
        ]);

        // Shartnoma 2 - 2024 yil avgustdan boshlangan
        $contract2 = Contract::create([
            'lot_id' => $lot35->id,
            'tenant_id' => $tenant1->id,
            'shartnoma_raqami' => 'SH-2024-035',
            'shartnoma_sanasi' => Carbon::parse('2024-08-01'),
            'auksion_sanasi' => Carbon::parse('2024-07-25'),
            'auksion_bayonnoma_raqami' => 'AUK-2024-0035',
            'auksion_xarajati' => 900000,
            'shartnoma_summasi' => 90000000,
            'oylik_tolovi' => 1500000,
            'shartnoma_muddati' => 60,
            'boshlanish_sanasi' => Carbon::parse('2024-09-01'),
            'tugash_sanasi' => Carbon::parse('2029-08-31'),
            'birinchi_tolov_sanasi' => Carbon::parse('2024-08-15'),
            'dalolatnoma_raqami' => 'DAL-2024-035',
            'dalolatnoma_sanasi' => Carbon::parse('2024-08-14'),
            'dalolatnoma_holati' => 'topshirilgan',
            'holat' => 'faol',
            'izoh' => 'Lot 35 - ASIA TRADE kompaniyasi (ikkinchi lot)',
        ]);

        // ========================================
        // IJARACHI 2: QARZDOR (1 TA LOT)
        // ========================================

        $tenant2 = Tenant::create([
            'name' => 'GOLDEN MARKET XUSUSIY KORXONASI',
            'type' => 'yuridik',
            'inn' => '308765432',
            'director_name' => 'Rahimov Bobur Toshpulatovich',
            'passport_serial' => 'AC 9876543',
            'phone' => '+998 91 987 65 43',
            'email' => 'goldenmarket@mail.uz',
            'address' => 'Toshkent shahar, Chilonzor tumani, Qatortol ko\'chasi, 88-uy',
            'bank_name' => 'ASAKABANK ATB',
            'bank_account' => '20208000200765432001',
            'bank_mfo' => '00873',
            'oked' => '47110',
            'is_active' => true,
        ]);

        // Lot 42 - GOLDEN MARKET uchun
        $lot42 = Lot::create([
            'lot_raqami' => '42',
            'obyekt_nomi' => 'Savdo markazi do\'koni №42',
            'manzil' => 'Toshkent shahar, Yashnobod tumani, Ohangaron shoh ko\'chasi, 40/42-uy',
            'tuman' => 'Yashnobod tumani',
            'kocha' => 'Ohangaron shoh ko\'chasi',
            'uy_raqami' => '40/42',
            'maydon' => 177.45,
            'tavsif' => 'Ikki qavatli savdo maydoni.',
            'obyekt_turi' => 'savdo',
            'latitude' => 41.312500,
            'longitude' => 69.281200,
            'boshlangich_narx' => 100000000,
            'holat' => 'ijarada',
            'is_active' => true,
        ]);

        // Shartnoma 3 - QARZDOR shartnoma
        $contract3 = Contract::create([
            'lot_id' => $lot42->id,
            'tenant_id' => $tenant2->id,
            'shartnoma_raqami' => 'SH-2024-042',
            'shartnoma_sanasi' => Carbon::parse('2024-08-19'),
            'auksion_sanasi' => Carbon::parse('2024-08-15'),
            'auksion_bayonnoma_raqami' => 'AUK-2024-0042',
            'auksion_xarajati' => 1800000,
            'shartnoma_summasi' => 180000000,
            'oylik_tolovi' => 3000000,
            'shartnoma_muddati' => 60,
            'boshlanish_sanasi' => Carbon::parse('2024-09-01'),
            'tugash_sanasi' => Carbon::parse('2029-08-31'),
            'birinchi_tolov_sanasi' => Carbon::parse('2024-09-02'),
            'dalolatnoma_raqami' => 'DAL-2024-042',
            'dalolatnoma_sanasi' => Carbon::parse('2024-08-30'),
            'dalolatnoma_holati' => 'topshirilgan',
            'holat' => 'faol',
            'izoh' => 'E\'tibor! Ijara haqi to\'lovida kechikishlar mavjud.',
        ]);

        // To'lov grafiklarini yaratish
        // Contract 1: 17 oydan 15 tasi to'langan (yaxshi to'lovchi)
        $this->createPaymentSchedule($contract1, 15, $bugun);
        // Contract 2: 16 oydan 14 tasi to'langan
        $this->createPaymentSchedule($contract2, 14, $bugun);
        // Contract 3: QARZDOR - faqat 5 oy to'langan, 11 oy qarz
        $this->createPaymentScheduleWithDebt($contract3, $bugun);

        // Natijalarni chiqarish
        $this->command->info('');
        $this->command->info('✅ Namuna ma\'lumotlar muvaffaqiyatli yaratildi!');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════');
        $this->command->info('📊 IJARACHI: ASIA TRADE GROUP MCHJ');
        $this->command->info('   ⭐ 2 TA LOTGA EGA (YAXSHI TO\'LOVCHI)');
        $this->command->info('═══════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('📊 IJARACHI: GOLDEN MARKET XK');
        $this->command->info('   ⚠️  QARZDOR - PENYA HISOBLANADI!');
        $this->command->info('═══════════════════════════════════════════════════');
    }

    /**
     * To'lov grafigini yaratish (yaxshi to'lovchilar uchun)
     *
     * @param Contract $contract
     * @param int $paidMonths - nechta oy to'langan
     * @param Carbon $bugun - bugungi sana
     */
    private function createPaymentSchedule(Contract $contract, int $paidMonths, Carbon $bugun): void
    {
        $boshlanish = Carbon::parse($contract->boshlanish_sanasi);
        $oylikTolov = $contract->oylik_tolovi;

        // Faqat kerakli oylar uchun grafik yaratish
        $monthsToCreate = min(20, $contract->shartnoma_muddati);

        for ($i = 1; $i <= $monthsToCreate; $i++) {
            if ($i === 1) {
                $tolovSanasi = Carbon::parse($contract->birinchi_tolov_sanasi);
            } else {
                $tolovSanasi = $boshlanish->copy()->addMonths($i - 1)->day(10);
            }

            $oxirgiMuddat = $tolovSanasi->copy()->addDays(10);
            $tolangan = $i <= $paidMonths;

            // Holatni to'g'ri aniqlash
            if ($tolangan) {
                $holat = 'tolangan';
            } elseif ($oxirgiMuddat->lt($bugun)) {
                $holat = 'tolanmagan'; // Muddati o'tgan
            } else {
                $holat = 'kutilmoqda'; // Hali muddati kelmagan
            }

            $ps = PaymentSchedule::create([
                'contract_id' => $contract->id,
                'oy_raqami' => $i,
                'yil' => $tolovSanasi->year,
                'oy' => $tolovSanasi->month,
                'tolov_sanasi' => $tolovSanasi,
                'oxirgi_muddat' => $oxirgiMuddat,
                'tolov_summasi' => $oylikTolov,
                'tolangan_summa' => $tolangan ? $oylikTolov : 0,
                'qoldiq_summa' => $tolangan ? 0 : $oylikTolov,
                'holat' => $holat,
            ]);

            // To'lov yaratish (agar to'langan bo'lsa)
            if ($tolangan) {
                Payment::create([
                    'contract_id' => $contract->id,
                    'payment_schedule_id' => $ps->id,
                    'tolov_raqami' => 'TLV-' . $contract->id . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'tolov_sanasi' => $tolovSanasi->copy()->addDays(rand(0, 5)),
                    'summa' => $oylikTolov,
                    'asosiy_qarz_uchun' => $oylikTolov,
                    'penya_uchun' => 0,
                    'auksion_uchun' => 0,
                    'tolov_usuli' => 'bank_otkazmasi',
                    'hujjat_raqami' => 'PL-' . rand(100000, 999999),
                    'holat' => 'tasdiqlangan',
                    'tasdiqlangan_sana' => $tolovSanasi,
                ]);
            }
        }
    }

    /**
     * Qarzdor shartnoma uchun to'lov grafigi
     *
     * PENYA HISOBLASH:
     * - Kunlik penya: 0.4% (qoldiq_summa * 0.004 * kechikish_kunlari)
     * - Maksimal penya: qoldiq_summa * 50%
     *
     * @param Contract $contract
     * @param Carbon $bugun - bugungi sana
     */
    private function createPaymentScheduleWithDebt(Contract $contract, Carbon $bugun): void
    {
        $boshlanish = Carbon::parse($contract->boshlanish_sanasi);
        $oylikTolov = $contract->oylik_tolovi;

        // Faqat 5 oy to'langan, qolgani qarz
        $paidMonths = 5;

        for ($i = 1; $i <= 17; $i++) {
            if ($i === 1) {
                $tolovSanasi = Carbon::parse($contract->birinchi_tolov_sanasi);
            } else {
                $tolovSanasi = $boshlanish->copy()->addMonths($i - 1)->day(10);
            }

            $oxirgiMuddat = $tolovSanasi->copy()->addDays(10);
            $tolangan = $i <= $paidMonths;
            $penya = 0;
            $kechikishKunlari = 0;

            // Holatni aniqlash
            if ($tolangan) {
                $holat = 'tolangan';
            } elseif ($oxirgiMuddat->lt($bugun)) {
                // Muddati o'tgan - PENYA hisoblanadi
                $holat = 'tolanmagan';
                $kechikishKunlari = $oxirgiMuddat->diffInDays($bugun);
                // Penya: 0.4% kuniga, maksimal 50%
                $penya = $oylikTolov * 0.004 * $kechikishKunlari;
                $maxPenya = $oylikTolov * 0.5;
                $penya = min($penya, $maxPenya);
            } else {
                // Hali muddati kelmagan
                $holat = 'kutilmoqda';
            }

            $ps = PaymentSchedule::create([
                'contract_id' => $contract->id,
                'oy_raqami' => $i,
                'yil' => $tolovSanasi->year,
                'oy' => $tolovSanasi->month,
                'tolov_sanasi' => $tolovSanasi,
                'oxirgi_muddat' => $oxirgiMuddat,
                'tolov_summasi' => $oylikTolov,
                'tolangan_summa' => $tolangan ? $oylikTolov : 0,
                'qoldiq_summa' => $tolangan ? 0 : $oylikTolov,
                'penya_summasi' => $penya,
                'tolangan_penya' => 0,
                'kechikish_kunlari' => $kechikishKunlari,
                'holat' => $holat,
            ]);

            if ($tolangan) {
                Payment::create([
                    'contract_id' => $contract->id,
                    'payment_schedule_id' => $ps->id,
                    'tolov_raqami' => 'TLV-' . $contract->id . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                    'tolov_sanasi' => $tolovSanasi->copy()->addDays(rand(0, 5)),
                    'summa' => $oylikTolov,
                    'asosiy_qarz_uchun' => $oylikTolov,
                    'penya_uchun' => 0,
                    'auksion_uchun' => 0,
                    'tolov_usuli' => 'bank_otkazmasi',
                    'hujjat_raqami' => 'PL-' . rand(100000, 999999),
                    'holat' => 'tasdiqlangan',
                    'tasdiqlangan_sana' => $tolovSanasi,
                ]);
            }
        }
    }
}
