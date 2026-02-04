<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * To'lov (Payment) modeli
 */
class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contract_id',
        'payment_schedule_id',
        'tolov_raqami',
        'tolov_sanasi',
        'summa',
        'asosiy_qarz_uchun',
        'penya_uchun',
        'auksion_uchun',
        'avans',
        'tolov_usuli',
        'hujjat_raqami',
        'hujjat_fayl',
        'holat',
        'izoh',
        'tasdiqlagan_id',
        'tasdiqlangan_sana',
    ];

    protected $casts = [
        'tolov_sanasi' => 'date',
        'tasdiqlangan_sana' => 'datetime',
        'summa' => 'decimal:2',
        'asosiy_qarz_uchun' => 'decimal:2',
        'penya_uchun' => 'decimal:2',
        'auksion_uchun' => 'decimal:2',
        'avans' => 'decimal:2',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function paymentSchedule(): BelongsTo
    {
        return $this->belongsTo(PaymentSchedule::class);
    }

    public function tasdiqlagan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'tasdiqlagan_id');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * To'lov usuli nomi (O'zbekcha)
     */
    public function getTolovUsuliNomiAttribute(): string
    {
        return match($this->tolov_usuli) {
            'bank_otkazmasi' => 'Bank o\'tkazmasi',
            'naqd' => 'Naqd pul',
            'karta' => 'Plastik karta',
            'onlayn' => 'Onlayn to\'lov',
            default => $this->tolov_usuli
        };
    }

    /**
     * Holat nomi (O'zbekcha)
     */
    public function getHolatNomiAttribute(): string
    {
        return match($this->holat) {
            'kutilmoqda' => 'Tasdiqlash kutilmoqda',
            'tasdiqlangan' => 'Tasdiqlangan',
            'rad_etilgan' => 'Rad etilgan',
            'qaytarilgan' => 'Qaytarilgan',
            default => $this->holat
        };
    }

    /**
     * Holat rangi (CSS class)
     */
    public function getHolatRangiAttribute(): string
    {
        return match($this->holat) {
            'kutilmoqda' => 'bg-amber-100 text-amber-700',
            'tasdiqlangan' => 'bg-green-100 text-green-700',
            'rad_etilgan' => 'bg-red-100 text-red-700',
            'qaytarilgan' => 'bg-gray-100 text-gray-700',
            default => 'bg-gray-100 text-gray-700'
        };
    }

    /**
     * Formatlangan summa
     */
    public function getFormattedSummaAttribute(): string
    {
        return number_format($this->summa, 0, '.', ' ') . ' UZS';
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * To'lovni qo'llash - FIFO tartibida eng eski qarzga
     */
    public function applyToContract(): void
    {
        if ($this->holat !== 'tasdiqlangan') {
            return;
        }

        $contract = $this->contract;
        $qoldiqSumma = $this->summa;

        // Eng eski to'lanmagan oylardan boshlab to'lash
        $schedules = $contract->paymentSchedules()
            ->tolanmagan()
            ->orderBy('oy_raqami')
            ->get();

        $totalPenyaTolov = 0;
        $totalAsosiyTolov = 0;

        foreach ($schedules as $schedule) {
            if ($qoldiqSumma <= 0) break;

            // Avval penyani hisoblash
            $schedule->calculatePenya();

            // To'lovni qo'llash
            $result = $schedule->applyPayment($qoldiqSumma);

            $totalPenyaTolov += $result['penya_tolangan'];
            $totalAsosiyTolov += $result['asosiy_tolangan'];
            $qoldiqSumma = $result['qoldiq'];
        }

        // To'lov taqsimotini saqlash
        $this->penya_uchun = $totalPenyaTolov;
        $this->asosiy_qarz_uchun = $totalAsosiyTolov;
        $this->save();
    }

    /**
     * To'lovni yangi raqam bilan yaratish
     */
    public static function generateTolovRaqami(): string
    {
        $prefix = 'TLV';
        $year = date('Y');

        // Use microseconds + random for guaranteed uniqueness in bulk operations
        $micro = substr(str_replace('.', '', microtime(true)), -10);
        $random = str_pad(mt_rand(0, 99), 2, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$micro}{$random}";
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeTasdiqlangan($query)
    {
        return $query->where('holat', 'tasdiqlangan');
    }

    public function scopeKutilmoqda($query)
    {
        return $query->where('holat', 'kutilmoqda');
    }
}
