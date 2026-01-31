<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * To'lov Grafigi (Payment Schedule) modeli
 */
class PaymentSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'oy_raqami',
        'yil',
        'oy',
        'tolov_sanasi',
        'oxirgi_muddat',
        'tolov_summasi',
        'tolangan_summa',
        'qoldiq_summa',
        'penya_summasi',
        'tolangan_penya',
        'kechikish_kunlari',
        'holat',
    ];

    protected $casts = [
        'tolov_sanasi' => 'date',
        'oxirgi_muddat' => 'date',
        'tolov_summasi' => 'decimal:2',
        'tolangan_summa' => 'decimal:2',
        'qoldiq_summa' => 'decimal:2',
        'penya_summasi' => 'decimal:2',
        'tolangan_penya' => 'decimal:2',
    ];

    // Penya foizi (kunlik)
    const PENYA_FOIZI = 0.4; // 0.4% per day
    const MAX_PENYA_FOIZI = 50; // Maximum 50% of debt

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Oy nomi (O'zbekcha)
     */
    public function getOyNomiAttribute(): string
    {
        $oylar = [
            1 => 'Yanvar', 2 => 'Fevral', 3 => 'Mart',
            4 => 'Aprel', 5 => 'May', 6 => 'Iyun',
            7 => 'Iyul', 8 => 'Avgust', 9 => 'Sentabr',
            10 => 'Oktabr', 11 => 'Noyabr', 12 => 'Dekabr'
        ];
        return $oylar[$this->oy] ?? '';
    }

    /**
     * To'liq davr nomi
     */
    public function getDavrNomiAttribute(): string
    {
        return "{$this->oy_nomi} {$this->yil}";
    }

    /**
     * Holat nomi (O'zbekcha)
     */
    public function getHolatNomiAttribute(): string
    {
        return match($this->holat) {
            'kutilmoqda' => 'Kutilmoqda',
            'tolanmagan' => 'To\'lanmagan',
            'qisman_tolangan' => 'Qisman to\'langan',
            'tolangan' => 'To\'langan',
            default => $this->holat
        };
    }

    /**
     * Holat rangi (CSS class)
     */
    public function getHolatRangiAttribute(): string
    {
        return match($this->holat) {
            'kutilmoqda' => 'bg-blue-100 text-blue-700',
            'tolanmagan' => 'bg-red-100 text-red-700',
            'qisman_tolangan' => 'bg-amber-100 text-amber-700',
            'tolangan' => 'bg-green-100 text-green-700',
            default => 'bg-gray-100 text-gray-700'
        };
    }

    /**
     * Muddati o'tganmi?
     */
    public function getMuddatiOtganAttribute(): bool
    {
        return Carbon::parse($this->oxirgi_muddat)->isPast() &&
               $this->holat !== 'tolangan';
    }

    /**
     * Qolgan penya (to'lanmagan)
     */
    public function getQoldiqPenyaAttribute(): float
    {
        return $this->penya_summasi - $this->tolangan_penya;
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * Penyani hisoblash
     * Qoida: Har kuni 0.4%, lekin 50% dan oshmasligi kerak
     * @param bool $save - Natijani saqlash kerakmi
     */
    public function calculatePenya(bool $save = true): float
    {
        if ($this->holat === 'tolangan') {
            return 0;
        }

        $oxirgiMuddat = Carbon::parse($this->oxirgi_muddat);
        $bugun = Carbon::today();

        if ($bugun->lte($oxirgiMuddat)) {
            return 0;
        }

        $kechikishKunlari = $oxirgiMuddat->diffInDays($bugun);
        $this->kechikish_kunlari = $kechikishKunlari;

        // Penya hisoblash: qoldiq_summa * 0.4% * kunlar
        $penya = $this->qoldiq_summa * (self::PENYA_FOIZI / 100) * $kechikishKunlari;

        // Maximum 50% dan oshmasligi kerak
        $maxPenya = $this->qoldiq_summa * (self::MAX_PENYA_FOIZI / 100);
        $penya = min($penya, $maxPenya);

        $this->penya_summasi = $penya;

        if ($save) {
            $this->save();
        }

        return $penya;
    }

    /**
     * Penyani BELGILANGAN SANAGA nisbatan hisoblash
     * To'lov sanasida penya qancha bo'lganini aniqlash uchun
     * @param Carbon $tolovSanasi - To'lov qilingan sana
     * @param bool $save - Natijani saqlash kerakmi
     */
    public function calculatePenyaAtDate(Carbon $tolovSanasi, bool $save = true): float
    {
        if ($this->holat === 'tolangan') {
            return 0;
        }

        $oxirgiMuddat = Carbon::parse($this->oxirgi_muddat);

        // Agar to'lov sanasi oxirgi muddatdan oldin bo'lsa - penya yo'q
        if ($tolovSanasi->lte($oxirgiMuddat)) {
            return 0;
        }

        // To'lov sanasigacha bo'lgan kechikish kunlari
        $kechikishKunlari = $oxirgiMuddat->diffInDays($tolovSanasi);
        $this->kechikish_kunlari = $kechikishKunlari;

        // Penya hisoblash: qoldiq_summa * 0.4% * kunlar
        $penya = $this->qoldiq_summa * (self::PENYA_FOIZI / 100) * $kechikishKunlari;

        // Maximum 50% dan oshmasligi kerak
        $maxPenya = $this->qoldiq_summa * (self::MAX_PENYA_FOIZI / 100);
        $penya = min($penya, $maxPenya);

        $this->penya_summasi = $penya;

        if ($save) {
            $this->save();
        }

        return $penya;
    }

    /**
     * To'lovni qo'llash (FIFO - eng eski qarzdan boshlab)
     */
    public function applyPayment(float $amount): array
    {
        $result = [
            'penya_tolangan' => 0,
            'asosiy_tolangan' => 0,
            'qoldiq' => $amount
        ];

        // Avval penyani to'lash
        if ($this->qoldiq_penya > 0 && $result['qoldiq'] > 0) {
            $penyaTolov = min($this->qoldiq_penya, $result['qoldiq']);
            $this->tolangan_penya += $penyaTolov;
            $result['penya_tolangan'] = $penyaTolov;
            $result['qoldiq'] -= $penyaTolov;
        }

        // Keyin asosiy qarzni to'lash
        if ($this->qoldiq_summa > 0 && $result['qoldiq'] > 0) {
            $asosiyTolov = min($this->qoldiq_summa, $result['qoldiq']);
            $this->tolangan_summa += $asosiyTolov;
            $this->qoldiq_summa -= $asosiyTolov;
            $result['asosiy_tolangan'] = $asosiyTolov;
            $result['qoldiq'] -= $asosiyTolov;
        }

        // Holatni yangilash
        $this->updateStatus();
        $this->save();

        return $result;
    }

    /**
     * Holatni yangilash
     */
    public function updateStatus(): void
    {
        if ($this->qoldiq_summa <= 0) {
            $this->holat = 'tolangan';
        } elseif ($this->tolangan_summa > 0) {
            $this->holat = 'qisman_tolangan';
        } elseif (Carbon::parse($this->tolov_sanasi)->isPast()) {
            $this->holat = 'tolanmagan';
        } else {
            $this->holat = 'kutilmoqda';
        }
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeTolanmagan($query)
    {
        return $query->whereIn('holat', ['tolanmagan', 'qisman_tolangan']);
    }

    public function scopeMuddatiOtgan($query)
    {
        return $query->where('oxirgi_muddat', '<', now())
                     ->whereIn('holat', ['tolanmagan', 'qisman_tolangan']);
    }

    public function scopeKutilmoqda($query)
    {
        return $query->where('holat', 'kutilmoqda');
    }
}
