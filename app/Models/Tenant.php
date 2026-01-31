<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Ijaraga oluvchi (Tenant) modeli
 */
class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'inn',
        'director_name',
        'passport_serial',
        'phone',
        'email',
        'address',
        'bank_name',
        'bank_account',
        'bank_mfo',
        'oked',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Barcha lotlar (shartnomalar orqali)
     * Bir ijarachi bir nechta lotga ega bo'lishi mumkin
     */
    public function lots(): HasManyThrough
    {
        return $this->hasManyThrough(
            Lot::class,
            Contract::class,
            'tenant_id',  // contracts.tenant_id
            'id',         // lots.id
            'id',         // tenants.id
            'lot_id'      // contracts.lot_id
        );
    }

    /**
     * Faol shartnomalar
     */
    public function activeContracts(): HasMany
    {
        return $this->hasMany(Contract::class)->where('holat', 'faol');
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * To'liq ma'lumot (nomi + INN)
     */
    public function getFullInfoAttribute(): string
    {
        return "{$this->name} (INN: {$this->inn})";
    }

    /**
     * Faol shartnomalar soni
     */
    public function getFaolShartnomalarSoniAttribute(): int
    {
        return $this->contracts()->where('holat', 'faol')->count();
    }

    /**
     * Faol lotlar soni
     */
    public function getFaolLotlarSoniAttribute(): int
    {
        return $this->activeContracts()->count();
    }

    /**
     * Faol lotlar ro'yxati (lot raqamlari)
     */
    public function getFaolLotlarAttribute(): array
    {
        return $this->activeContracts()
            ->with('lot:id,lot_raqami')
            ->get()
            ->pluck('lot.lot_raqami')
            ->toArray();
    }

    /**
     * Jami qarzdorlik
     */
    public function getJamiQarzdorlikAttribute(): float
    {
        return $this->contracts()
            ->where('holat', 'faol')
            ->with('paymentSchedules')
            ->get()
            ->sum(fn($contract) => $contract->paymentSchedules->sum('qoldiq_summa'));
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeYuridik($query)
    {
        return $query->where('type', 'yuridik');
    }

    public function scopeJismoniy($query)
    {
        return $query->where('type', 'jismoniy');
    }

    /**
     * Bir nechta lotga ega ijarachilar
     */
    public function scopeWithMultipleLots($query)
    {
        return $query->whereHas('contracts', function ($q) {
            $q->where('holat', 'faol');
        }, '>', 1);
    }
}
