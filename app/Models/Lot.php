<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lot (Obyekt) modeli
 */
class Lot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lot_raqami',
        'obyekt_nomi',
        'manzil',
        'tuman',
        'kocha',
        'uy_raqami',
        'maydon',
        'tavsif',
        'obyekt_turi',
        'latitude',
        'longitude',
        'map_url',
        'rasmlar',
        'main_image_index',
        'has_elektr',
        'has_gaz',
        'has_suv',
        'has_kanalizatsiya',
        'has_internet',
        'has_isitish',
        'has_konditsioner',
        'xonalar_soni',
        'qavat',
        'qavatlar_soni',
        'kadastr_raqami',
        'boshlangich_narx',
        'holat',
        'is_active',
    ];

    protected $casts = [
        'maydon' => 'decimal:2',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'boshlangich_narx' => 'decimal:2',
        'rasmlar' => 'array',
        'main_image_index' => 'integer',
        'is_active' => 'boolean',
        'has_elektr' => 'boolean',
        'has_gaz' => 'boolean',
        'has_suv' => 'boolean',
        'has_kanalizatsiya' => 'boolean',
        'has_internet' => 'boolean',
        'has_isitish' => 'boolean',
        'has_konditsioner' => 'boolean',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function activeContract(): ?Contract
    {
        return $this->contracts()->where('holat', 'faol')->first();
    }

    /**
     * Hozirgi ijarachi (faol shartnoma orqali)
     */
    public function currentTenant(): ?Tenant
    {
        $contract = $this->activeContract();
        return $contract ? $contract->tenant : null;
    }

    /**
     * Hozirgi ijarachi ID (eager loading uchun)
     */
    public function getCurrentTenantIdAttribute(): ?int
    {
        return $this->contracts()
            ->where('holat', 'faol')
            ->value('tenant_id');
    }

    /**
     * Hozirgi ijarachi nomi
     */
    public function getCurrentTenantNameAttribute(): ?string
    {
        $contract = $this->contracts()
            ->where('holat', 'faol')
            ->with('tenant:id,name')
            ->first();
        return $contract?->tenant?->name;
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * To'liq manzil
     */
    public function getToliqManzilAttribute(): string
    {
        $parts = array_filter([
            $this->tuman,
            $this->kocha,
            $this->uy_raqami ? "№{$this->uy_raqami}" : null
        ]);
        return implode(', ', $parts) ?: $this->manzil;
    }

    /**
     * Formatlangan maydon
     */
    public function getFormattedMaydonAttribute(): string
    {
        return number_format($this->maydon, 2) . ' kv.m';
    }

    /**
     * Obyekt turi nomi (O'zbekcha)
     */
    public function getObyektTuriNomiAttribute(): string
    {
        return match($this->obyekt_turi) {
            'savdo' => 'Savdo obyekti',
            'xizmat' => 'Xizmat ko\'rsatish',
            'ishlab_chiqarish' => 'Ishlab chiqarish',
            'ombor' => 'Ombor',
            'ofis' => 'Ofis',
            'boshqa' => 'Boshqa',
            default => $this->obyekt_turi
        };
    }

    /**
     * Holat nomi (O'zbekcha)
     */
    public function getHolatNomiAttribute(): string
    {
        return match($this->holat) {
            'bosh' => 'Bo\'sh',
            'ijarada' => 'Ijarada',
            'band' => 'Band',
            'tamirlashda' => 'Ta\'mirlashda',
            default => $this->holat
        };
    }

    /**
     * Asosiy rasm URL
     */
    public function getMainImageAttribute(): ?string
    {
        $images = $this->rasmlar ?? [];
        $index = $this->main_image_index ?? 0;
        return isset($images[$index]) ? $images[$index] : ($images[0] ?? null);
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBosh($query)
    {
        return $query->where('holat', 'bosh');
    }

    public function scopeIjarada($query)
    {
        return $query->where('holat', 'ijarada');
    }
}
