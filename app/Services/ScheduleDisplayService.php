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
        $paymentDue10th = Carbon::create($schedule->yil, $schedule->oy, 10);
        $deadline = $schedule->custom_oxirgi_muddat
            ? Carbon::parse($schedule->custom_oxirgi_muddat)
            : Carbon::parse($schedule->oxirgi_muddat);

        $currentMonth = $today->month;
        $currentYear = $today->year;
        $isCurrentMonth = ($schedule->oy == $currentMonth && $schedule->yil == $currentYear);

        // Pro-rata aniqlash (qisman birinchi oy)
        $proRata = $this->detectProRata($schedule, $contract);

        // Calculate days and overdue status
        $daysData = $this->calculateDaysAndOverdue(
            $schedule,
            $paymentDue10th,
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
            'qoldiq_summa' => $schedule->qoldiq_summa,

            // Dates
            'tolov_sanasi' => $schedule->tolov_sanasi,
            'oxirgi_muddat' => $schedule->oxirgi_muddat,
            'custom_oxirgi_muddat' => $schedule->custom_oxirgi_muddat,
            'effective_deadline' => $deadline->format('Y-m-d'),
            'payment_date' => $daysData['payment_date'],

            // Days and overdue
            'days_left' => $daysData['days_left'],
            'overdue_days' => $daysData['overdue_days'],
            'is_overdue' => $daysData['is_overdue'],

            // Penalty
            'penya_summasi' => $penaltyData['penya_summasi'],
            'tolangan_penya' => $schedule->tolangan_penya ?? 0,
            'qoldiq_penya' => $penaltyData['qoldiq_penya'],
            'penya_rate' => $daysData['is_overdue'] ? '0,4%' : null,

            // Status
            'holat' => $schedule->holat,
            'can_delete' => $schedule->tolangan_summa <= 0,
            'has_custom_deadline' => !empty($schedule->custom_oxirgi_muddat),
            'muddat_ozgarish_izoh' => $schedule->muddat_ozgarish_izoh,

            // Pro-rata
            'is_pro_rata' => $proRata['is_pro_rata'],
            'pro_rata_tooltip' => $proRata['tooltip'],
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
        $tolovSanasi = Carbon::parse($schedule->tolov_sanasi);

        if (!$tolovSanasi->isSameDay($boshlanish) || $boshlanish->day === 1) {
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
     */
    private function calculateDaysAndOverdue(
        PaymentSchedule $schedule,
        Carbon $paymentDue10th,
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

        // Calculate overdue status
        if ($isPaid) {
            // Fully paid schedule
            if (!$hasDebt) {
                // Fully paid - only show days if we found a payment in this month
                if ($paymentDate && $paymentDate->gt($paymentDue10th) && !$isCurrentMonth) {
                    // Paid late in this month - show delay
                    return [
                        'is_overdue' => true,
                        'overdue_days' => $paymentDue10th->diffInDays($paymentDate),
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
            if ($today->gt($paymentDue10th)) {
                return [
                    'is_overdue' => true,
                    'overdue_days' => $paymentDue10th->diffInDays($today),
                    'days_left' => 0,
                    'payment_date' => $paymentDate ? $paymentDate->format('d.m.Y') : null,
                ];
            }
        }

        // Unpaid schedule
        if ($hasDebt && $today->gt($paymentDue10th)) {
            // Overdue unpaid - show days from 10th to today
            return [
                'is_overdue' => true,
                'overdue_days' => $paymentDue10th->diffInDays($today),
                'days_left' => 0,
                'payment_date' => null,
            ];
        }

        // Future schedule or not yet due
        $daysUntilDeadline = $today->diffInDays($deadline, false);
        return [
            'is_overdue' => false,
            'overdue_days' => 0,
            'days_left' => max(0, $daysUntilDeadline),
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
