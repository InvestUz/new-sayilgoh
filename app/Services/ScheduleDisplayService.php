<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\PaymentSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for calculating schedule display data
 *
 * ALL penalty, overdue, and payment logic centralized here
 * Blade views should ONLY display the returned data
 */
class ScheduleDisplayService
{
    /**
     * Get complete schedule display data for a contract
     *
     * @param Contract $contract
     * @param array|null $periodDates Filter by period dates ['start' => Carbon, 'end' => Carbon]
     * @param Carbon|null $referenceDate
     * @return array
     */
    public function getScheduleDisplayData(Contract $contract, ?array $periodDates = null, ?Carbon $referenceDate = null): array
    {
        $today = $referenceDate ?? Carbon::today();
        $isContractExpired = Carbon::parse($contract->tugash_sanasi)->lt($today);

        $query = $contract->paymentSchedules()
            ->orderBy('yil')
            ->orderBy('oy');

        // Filter by period date range if specified
        if ($periodDates && isset($periodDates['start']) && isset($periodDates['end'])) {
            $startDate = $periodDates['start'];
            $endDate = $periodDates['end'];

            // Filter schedules within the period date range
            $query->where(function($q) use ($startDate, $endDate) {
                $q->whereRaw('CONCAT(yil, "-", LPAD(oy, 2, "0"), "-01") >= ?', [$startDate->format('Y-m-01')])
                  ->whereRaw('CONCAT(yil, "-", LPAD(oy, 2, "0"), "-01") <= ?', [$endDate->format('Y-m-01')]);
            });
        }

        $schedules = $query->get();

        $displaySchedules = [];

        foreach ($schedules as $schedule) {
            $displaySchedules[] = $this->calculateScheduleDisplay($schedule, $contract, $today, $isContractExpired);
        }

        return [
            'schedules' => $displaySchedules,
            'is_contract_expired' => $isContractExpired,
            'reference_date' => $today->format('Y-m-d'),
        ];
    }

    /**
     * Calculate display data for a single schedule
     */
    private function calculateScheduleDisplay(
        PaymentSchedule $schedule,
        Contract $contract,
        Carbon $today,
        bool $isContractExpired
    ): array {
        $boshlanishSanasi = Carbon::parse($contract->boshlanish_sanasi);
        $deadline = $schedule->custom_oxirgi_muddat
            ? Carbon::parse($schedule->custom_oxirgi_muddat)
            : Carbon::parse($schedule->oxirgi_muddat);

        // 1-grafik: reja muddati va ko'rsatiladigan muddat shartnoma boshlanish sanasidan
        // (qolgan oylar: DB dagi tolov/oxirgi_muddat, odatda to'lov kuni 10).
        if (!$schedule->custom_oxirgi_muddat && (int) $schedule->oy_raqami === 1) {
            $deadline = $boshlanishSanasi->copy();
        }

        $planPaymentDate = (!$schedule->custom_oxirgi_muddat && (int) $schedule->oy_raqami === 1)
            ? $boshlanishSanasi->copy()
            : Carbon::parse($schedule->tolov_sanasi);

        $currentMonth = $today->month;
        $currentYear = $today->year;
        $isCurrentMonth = ($schedule->oy == $currentMonth && $schedule->yil == $currentYear);

        // Pro-rata aniqlash (qisman birinchi oy)
        $proRata = $this->detectProRata($schedule, $contract);

        // Calculate days and overdue status
        $daysData = $this->calculateDaysAndOverdue(
            $schedule,
            $deadline,
            $today,
            $isCurrentMonth,
            $contract
        );

        // Calculate penalty
        $penaltyData = $this->calculatePenalty(
            $schedule,
            $daysData['overdue_days'],
            $daysData['is_overdue'],
            $isContractExpired
        );

        // Fakt tushgan: ushbu grafik oyida real naqd tushgan to'lovlar
        // (tolov_sanasi ayni shu yil-oyda bo'lgan tasdiqlangan to'lovlar yig'indisi).
        // Bu qiymat FIFO taqsimotidan mustaqil — tenant fakti qaysi oyda qancha
        // bergani shaffof ko'rinadi.
        $faktPayments = $contract->payments
            ->filter(function ($p) use ($schedule) {
                if ($p->holat !== 'tasdiqlangan') return false;
                $d = Carbon::parse($p->tolov_sanasi);
                return $d->month == $schedule->oy && $d->year == $schedule->yil;
            })
            ->sortBy('tolov_sanasi')
            ->values();
        $faktTushgan = (float) $faktPayments->sum('summa');
        $faktDocs = $faktPayments->map(function ($p) {
            return [
                'id' => $p->id,
                'sana' => Carbon::parse($p->tolov_sanasi)->format('d.m.Y'),
                'summa' => (float) $p->summa,
                'hujjat' => $p->hujjat_raqami,
                'tolov_raqami' => $p->tolov_raqami,
            ];
        })->all();

        $rowMeta = $this->buildJadvalRowMeta(
            $schedule,
            $daysData,
            $penaltyData,
            $deadline,
            $today,
            $isCurrentMonth,
            $faktTushgan
        );

        $qarzKo = $rowMeta['qarz_ko_rinishi'] ?? 'oddiy';
        $qoldiqForDisplay = (float) $schedule->qoldiq_summa;
        if ($qarzKo === 'qarzdor_fakt' && (float) $schedule->tolov_summasi > 0) {
            $qoldiqForDisplay = (float) $schedule->tolov_summasi;
        }
        if ($qarzKo === 'kutilayotgan') {
            $qoldiqForDisplay = 0.0;
            $rowMeta['qoldiq_hujayra_ochilishi'] = false;
            $rowMeta['kun_ko_rinishi'] = null;
            $rowMeta['kun_ko_rinishi_izoh'] = null;
            $rowMeta['kun_jami_akt'] = false;
            $rowMeta['qator_izoh'] = "Faqat reja. Oxirgi muddatgacha: qarz, penya, qolgan kun bu ustunlarda yig'ilmaydi (muddati kelayotganda alohida hisoblanadi).";
            $rowMeta['qoldiq_usti_title'] = null;
            $rowMeta['penya_rate'] = null;
        }
        $hasActiveDebtDisplay = $qoldiqForDisplay > 0.0001 && $daysData['is_overdue'];

        $penyaSummasi = $qarzKo === 'kutilayotgan' ? 0.0 : (float) $penaltyData['penya_summasi'];
        $qoldiqPenya = $qarzKo === 'kutilayotgan' ? 0.0 : (float) $penaltyData['qoldiq_penya'];
        $tolanganPenya = $qarzKo === 'kutilayotgan' ? 0.0 : (float) ($schedule->tolangan_penya ?? 0);

        return [
            'id' => $schedule->id,
            'month' => $schedule->oy,
            'year' => $schedule->yil,
            'month_name' => $this->getMonthName($schedule->oy),
            'is_current_month' => $isCurrentMonth,

            // Amounts
            'tolov_summasi' => $schedule->tolov_summasi,
            'tolangan_summa' => $schedule->tolangan_summa,   // FIFO orqali asosiy qarzga yo'naltirilgan
            'fakt_tushgan' => $faktTushgan,                  // shu oyda real tushgan naqd (FIFO dan mustaqil)
            'fakt_payments' => $faktDocs,                    // tooltip uchun to'lovlar ro'yxati
            'qoldiq_summa' => $qoldiqForDisplay,

            // Dates
            'tolov_sanasi' => $planPaymentDate->format('Y-m-d'),
            'oxirgi_muddat' => $schedule->oxirgi_muddat,
            'custom_oxirgi_muddat' => $schedule->custom_oxirgi_muddat,
            'effective_deadline' => $deadline->format('Y-m-d'),
            'payment_date' => $daysData['payment_date'],

            // Days and overdue
            'days_left' => $qarzKo === 'kutilayotgan' ? 0 : $daysData['days_left'],
            'overdue_days' => $daysData['overdue_days'],
            'is_overdue' => $daysData['is_overdue'],

            // Jadval: Qoldiq/Kun/izoh (FIFO+penya tufayli "bo'sh" qatorlarni tushuntirish)
            'kechikish_kunlari' => $qarzKo === 'kutilayotgan' ? 0 : (int) ($schedule->kechikish_kunlari ?? 0),
            'kun_ko_rinishi' => $rowMeta['kun_ko_rinishi'],
            'kun_ko_rinishi_izoh' => $rowMeta['kun_ko_rinishi_izoh'],
            'kun_jami_akt' => $rowMeta['kun_jami_akt'],
            'qoldiq_hujayra_ochilishi' => $rowMeta['qoldiq_hujayra_ochilishi'],
            'qator_izoh' => $rowMeta['qator_izoh'],
            'qoldiq_usti_title' => $rowMeta['qoldiq_usti_title'] ?? null,
            'qarz_ko_rinishi' => $rowMeta['qarz_ko_rinishi'] ?? 'oddiy',

            // Penalty
            'penya_summasi' => $penyaSummasi,
            'tolangan_penya' => $tolanganPenya,
            'qoldiq_penya' => $qoldiqPenya,
            'penya_rate' => $rowMeta['penya_rate'],

            // Status
            'holat' => $schedule->holat,
            'can_delete' => $schedule->tolangan_summa <= 0,
            'has_custom_deadline' => !empty($schedule->custom_oxirgi_muddat),
            'muddat_ozgarish_izoh' => $schedule->muddat_ozgarish_izoh,

            // Pro-rata
            'is_pro_rata' => $proRata['is_pro_rata'],
            'pro_rata_tooltip' => $proRata['tooltip'],

            // Yoritish: faqat hozirgi aktiv qarz
            'highlight_active_debt' => $hasActiveDebtDisplay,
        ];
    }

    /**
     * Qarz, kun va penya o'rtasidagi farqni tushuntirish: asosiy 0 (FIFO) bo'lganda
     * "Kun" va "Qoldiq" ustunlari bo'sh qolmasin; "jami kechikish" (DB) va izoh.
     *
     * @return array{
     *   kun_ko_rinishi: ?int,
     *   kun_ko_rinishi_izoh: ?string,
     *   qoldiq_hujayra_ochilishi: bool,
     *   qator_izoh: ?string,
     *   qoldiq_usti_title: ?string,
     *   qarz_ko_rinishi: 'qarzdor_fakt'|'kutilayotgan'|'oddiy',
     *   penya_rate: ?string
     * }
     */
    private function buildJadvalRowMeta(
        PaymentSchedule $schedule,
        array $daysData,
        array $penaltyData,
        Carbon $deadline,
        Carbon $today,
        bool $isCurrentMonth,
        float $faktTushgan
    ): array {
        $kArxiv = (int) ($schedule->kechikish_kunlari ?? 0);
        $q = (float) $schedule->qoldiq_summa;
        $pen = (float) ($schedule->penya_summasi ?? 0);
        $penTol = (float) ($schedule->tolangan_penya ?? 0);
        $qPen = (float) ($penaltyData['qoldiq_penya'] ?? 0);
        $tol = (float) ($schedule->tolangan_summa ?? 0);

        $deadlineOtkan = $deadline->copy()->startOfDay()->lt($today->copy()->startOfDay());

        $anyPenyaGraf = $pen > 0.0001 || $penTol > 0.0001 || $qPen > 0.0001;

        $penyaRate = ($anyPenyaGraf || $daysData['is_overdue']) ? '0,4%' : null;

        $kunK = null;
        $kunT = null;
        $kunJami = false;
        if ($daysData['is_overdue'] && (int) $daysData['overdue_days'] > 0) {
            $kunK = (int) $daysData['overdue_days'];
            $q > 0.0001
                ? $kunT = "Joriy kechikish: muddat o'tgach qoldiqqa nisbatan (kunlar)."
                : $kunT = "Joriy kechikish: muddat o'tgach to'lov (asosiy 0 yoki to'langan).";
        } elseif ($daysData['days_left'] > 0) {
            $kunK = (int) $daysData['days_left'];
            $kunT = "Oxirgi muddatgacha qolgan kunlar.";
        } elseif ($kArxiv > 0 && $deadlineOtkan) {
            $kunK = $kArxiv;
            $kunJami = true;
            $kunT = 'Jami kechikish: DB, penya hisobida. Joriy kechikish emas.';
        }

        $qoldiqOch = $daysData['is_overdue']
            || $isCurrentMonth
            || $tol > 0.0001
            || $faktTushgan > 0.0001
            || $q > 0.0001
            || ($anyPenyaGraf && $deadlineOtkan)
            || ($kArxiv > 0 && $deadlineOtkan);

        $qatorIz = null;
        $qoldiqUstiTitle = null;
        if ($q <= 0.0001 && $anyPenyaGraf && $deadlineOtkan) {
            if ($kArxiv > 0) {
                if ($faktTushgan > 0.0001) {
                    $qatorIz = sprintf(
                        "Muddati o'tgan: jami %d kun kechikish (penya asosi), shu oydan tushim bor.",
                        $kArxiv
                    );
                } else {
                    $qatorIz = sprintf(
                        "Muddati o'tgan: jami %d kun kechikish (penya asosi), shu kalendar oyda tushim yo'q; qarz reja (grafik) bo'yicha.",
                        $kArxiv
                    );
                }
            } else {
                $qatorIz = "Penya/kechikish jadval bo'yicha.";
            }
        } elseif ($q > 0.0001 && $daysData['is_overdue']) {
            $qatorIz = "Aktiv qarz, muddati o'tgan, penya qoldiqqa.";
        } elseif ($q > 0.0001 && !$daysData['is_overdue'] && $daysData['days_left'] > 0) {
            $qatorIz = "Muddatgacha to'lash.";
        }

        // Qarz "nomlari": fakt=0 va muddat o'tgan oylar kalendar bo'yicha qarzdor; kelajak — qarz emas, kutilayotgan.
        $qarzKoRinishi = 'oddiy';
        if (! $deadlineOtkan && (int) $daysData['days_left'] > 0) {
            $qarzKoRinishi = 'kutilayotgan';
            $qatorIz = "Hali oxirgi muddat kelmadi; hozir 'qarzdor' emas, faqat reja (kutilayotgan to'lov).";
        } elseif ($deadlineOtkan && $faktTushgan <= 0.0001) {
            $qarzKoRinishi = 'qarzdor_fakt';
            if (empty($qatorIz)) {
                $qatorIz = "Qarz: shu oydan kalendar bo'yicha tushim yo'q, grafik reja bo'yicha hisoblanadi.";
            } elseif (! str_contains($qatorIz, "tushim yo'q") && ! str_contains($qatorIz, 'Qarz:')) {
                $qatorIz .= " Qarz: shu oydan kalendar bo'yicha tushim yo'q, grafik reja bo'yicha hisoblanadi.";
            }
        }

        return [
            'kun_ko_rinishi' => $kunK,
            'kun_ko_rinishi_izoh' => $kunT,
            'kun_jami_akt' => $kunJami,
            'qoldiq_hujayra_ochilishi' => $qoldiqOch,
            'qator_izoh' => $qatorIz,
            'qoldiq_usti_title' => $qoldiqUstiTitle,
            'qarz_ko_rinishi' => $qarzKoRinishi,
            'penya_rate' => $penyaRate,
        ];
    }

    /**
     * Birinchi qisman oyni aniqlash va tooltip hosil qilish.
     *
     * Pro-rata aniqlanadi: agar grafikning sanasi shartnoma boshlanish
     * sanasiga teng bo'lsa va summa to'liq oylikdan kichik bo'lsa.
     */
    private function detectProRata(PaymentSchedule $schedule, Contract $contract): array
    {
        $boshlanish = Carbon::parse($contract->boshlanish_sanasi);
        if ($boshlanish->day === 1) {
            return ['is_pro_rata' => false, 'tooltip' => null];
        }
        if ((int) $schedule->oy_raqami !== 1) {
            return ['is_pro_rata' => false, 'tooltip' => null];
        }

        $annualRent = (float) ($contract->yillik_ijara_haqi ?? $contract->shartnoma_summasi);
        $monthlyFull = round($annualRent / 12, 2);
        $scheduleAmount = (float) $schedule->tolov_summasi;

        // To'liq oylikdan farqli bo'lsa — pro-rata
        if (abs($scheduleAmount - $monthlyFull) < 0.5) {
            return ['is_pro_rata' => false, 'tooltip' => null];
        }

        $daysInMonth = $boshlanish->daysInMonth;
        $activeDays = $daysInMonth - $boshlanish->day + 1;

        $tooltip = sprintf(
            "Qisman birinchi oy (%d kun / %d): %s × %d/%d = %s",
            $activeDays,
            $daysInMonth,
            number_format($monthlyFull, 2, ',', ' '),
            $activeDays,
            $daysInMonth,
            number_format($scheduleAmount, 2, ',', ' ')
        );

        return ['is_pro_rata' => true, 'tooltip' => $tooltip];
    }

    /**
     * Calculate days left/overdue and payment date
     * (1-grafik uchun $deadline allaqachon boshlanish sanasiga moslangan.)
     */
    private function calculateDaysAndOverdue(
        PaymentSchedule $schedule,
        Carbon $deadline,
        Carbon $today,
        bool $isCurrentMonth,
        Contract $contract
    ): array {
        $isPaid = $schedule->tolangan_summa > 0;
        $hasDebt = $schedule->qoldiq_summa > 0;

        // Find payment date if paid - only if payment was made in the same month
        $paymentDate = null;
        if ($isPaid) {
            // Look for payments made in this schedule's month
            foreach ($contract->payments->sortBy('tolov_sanasi') as $pmt) {
                $pmtDate = Carbon::parse($pmt->tolov_sanasi);
                if ($pmtDate->month == $schedule->oy && $pmtDate->year == $schedule->yil) {
                    $paymentDate = $pmtDate;
                    break;
                }
            }
        }

        $todayDate = $today->copy()->startOfDay();
        $deadlineDay = $deadline->copy()->startOfDay();

        // Calculate overdue status
        if ($isPaid) {
            // Fully paid schedule
            if (!$hasDebt) {
                // Fully paid - only show days if we found a payment in this month
                if ($paymentDate && $paymentDate->copy()->startOfDay()->gt($deadlineDay) && !$isCurrentMonth) {
                    // Paid after oxirgi muddat — kechikish
                    $payDay = $paymentDate->copy()->startOfDay();
                    return [
                        'is_overdue' => true,
                        'overdue_days' => (int) $deadlineDay->diffInDays($payDay),
                        'days_left' => 0,
                        'payment_date' => $paymentDate->format('d.m.Y'),
                    ];
                }

                // Paid on time or by FIFO from another month - show "—"
                return [
                    'is_overdue' => false,
                    'overdue_days' => 0,
                    'days_left' => 0,
                    'payment_date' => $paymentDate ? $paymentDate->format('d.m.Y') : null,
                ];
            }

            // Partially paid - show ongoing debt days
            if ($todayDate->gt($deadlineDay)) {
                return [
                    'is_overdue' => true,
                    'overdue_days' => (int) $deadlineDay->diffInDays($todayDate),
                    'days_left' => 0,
                    'payment_date' => $paymentDate ? $paymentDate->format('d.m.Y') : null,
                ];
            }
        }

        // Unpaid schedule: kechikish oxirgi muddatdan keyin
        if ($hasDebt && $todayDate->gt($deadlineDay)) {
            return [
                'is_overdue' => true,
                'overdue_days' => (int) $deadlineDay->diffInDays($todayDate),
                'days_left' => 0,
                'payment_date' => null,
            ];
        }

        // Future schedule or not yet due (qolgan kunlar: oxirgi muddatgacha)
        $daysUntilDeadline = $todayDate->lte($deadlineDay)
            ? (int) $todayDate->diffInDays($deadlineDay)
            : 0;
        return [
            'is_overdue' => false,
            'overdue_days' => 0,
            'days_left' => $daysUntilDeadline,
            'payment_date' => null,
        ];
    }

    /**
     * Penyani ko'rsatish uchun tayyorlash.
     *
     * Manba: PaymentSchedule.penya_summasi (DBda saqlangan haqiqiy tarixiy
     * qiymat). Bu yerda QAYTA hisob qilmaymiz — yo'qotmaslik kafolati
     * `PaymentSchedule::calculatePenyaAtDate` orqali ta'minlanadi.
     */
    private function calculatePenalty(
        PaymentSchedule $schedule,
        int $overdueDays,
        bool $isOverdue,
        bool $isContractExpired
    ): array {
        $penyaSummasi = (float) ($schedule->penya_summasi ?? 0);
        $tolanganPenya = (float) ($schedule->tolangan_penya ?? 0);
        $qoldiqPenya = max(0.0, $penyaSummasi - $tolanganPenya);

        return [
            'penya_summasi' => round($penyaSummasi, 2),
            'qoldiq_penya' => round($qoldiqPenya, 2),
        ];
    }

    /**
     * Get month name in Uzbek
     */
    private function getMonthName(int $month): string
    {
        $months = [
            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart', 4 => 'Aprel',
            5 => 'May', 6 => 'Iyun', 7 => 'Iyul', 8 => 'Avg',
            9 => 'Sent', 10 => 'Okt', 11 => 'Noy', 12 => 'Dek'
        ];

        return $months[$month] ?? $month;
    }

    /**
     * Get period statistics
     */
    public function getPeriodStatistics(Collection $schedules, Carbon $today): array
    {
        $total = $schedules->sum('tolov_summasi');
        $paid = $schedules->sum('tolangan_summa');
        $debt = $schedules->sum('qoldiq_summa');

        // Overdue: only schedules where payment date < today AND has debt
        $overdue = $schedules->filter(function($s) use ($today) {
            if ($s->qoldiq_summa <= 0) return false;
            $paymentDate = Carbon::parse($s->tolov_sanasi);
            return $paymentDate->lt($today);
        })->sum('qoldiq_summa');

        // Penalty: only unpaid penalty
        $penalty = max(0, $schedules->sum('penya_summasi') - $schedules->sum('tolangan_penya'));

        $percent = $total > 0 ? round(($paid / $total) * 100, 1) : 0;

        return [
            'total' => $total,
            'paid' => $paid,
            'debt' => $debt,
            'overdue' => $overdue,
            'penalty' => $penalty,
            'percent' => $percent,
        ];
    }
}
