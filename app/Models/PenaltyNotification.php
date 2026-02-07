<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penalty Notification (Bildirg'inoma) model
 * 
 * Stores immutable penalty notification records for audit compliance.
 * Based on contract clause 8.2:
 * - Penalty rate: 0.4% per day
 * - Penalty cap: 50% of overdue amount
 */
class PenaltyNotification extends Model
{
    protected $fillable = [
        'contract_id',
        'payment_schedule_id',
        'payment_id',
        'notification_number',
        'notification_date',
        'contract_number',
        'tenant_name',
        'lot_number',
        'due_date',
        'payment_date',
        'overdue_amount',
        'overdue_days',
        'penalty_rate',
        'calculated_penalty',
        'max_penalty',
        'cap_applied',
        'final_penalty',
        'formula_text',
        'legal_basis',
        'notification_text_uz',
        'generated_by',
        'pdf_path',
        'pdf_generated_at',
        'status',
        'system_match',
        'system_penalty',
        'mismatch_reason',
    ];

    protected $casts = [
        'notification_date' => 'date',
        'due_date' => 'date',
        'payment_date' => 'date',
        'pdf_generated_at' => 'datetime',
        'overdue_amount' => 'decimal:2',
        'penalty_rate' => 'decimal:4',
        'calculated_penalty' => 'decimal:2',
        'max_penalty' => 'decimal:2',
        'final_penalty' => 'decimal:2',
        'system_penalty' => 'decimal:2',
        'cap_applied' => 'boolean',
        'system_match' => 'boolean',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    // ============================================
    // ACCESSORS
    // ============================================

    /**
     * Status name in Uzbek
     */
    public function getStatusNomiAttribute(): string
    {
        return match($this->status) {
            'generated' => 'Yaratilgan',
            'sent' => 'Yuborilgan',
            'acknowledged' => 'Qabul qilingan',
            default => $this->status
        };
    }

    /**
     * Status badge color
     */
    public function getStatusRangiAttribute(): string
    {
        return match($this->status) {
            'generated' => 'bg-blue-100 text-blue-700',
            'sent' => 'bg-amber-100 text-amber-700',
            'acknowledged' => 'bg-green-100 text-green-700',
            default => 'bg-gray-100 text-gray-700'
        };
    }

    /**
     * Formatted penalty amount
     */
    public function getFormattedPenaltyAttribute(): string
    {
        return number_format($this->final_penalty, 0, '.', ' ') . ' UZS';
    }

    /**
     * Formatted overdue amount
     */
    public function getFormattedOverdueAmountAttribute(): string
    {
        return number_format($this->overdue_amount, 0, '.', ' ') . ' UZS';
    }

    // ============================================
    // METHODS
    // ============================================

    /**
     * Generate unique notification number
     */
    public static function generateNotificationNumber(): string
    {
        $prefix = 'BN'; // Bildirg'inoma
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;
        
        return sprintf('%s-%d-%05d', $prefix, $year, $count);
    }

    /**
     * Check if notification matches system calculation
     */
    public function verifySystemMatch(float $systemPenalty): bool
    {
        $tolerance = 0.01; // 1 tiyin tolerance
        $match = abs($this->final_penalty - $systemPenalty) <= $tolerance;
        
        $this->system_match = $match;
        $this->system_penalty = $systemPenalty;
        
        if (!$match) {
            $this->mismatch_reason = sprintf(
                'Kalkulyator: %s UZS, Tizim: %s UZS',
                number_format($this->final_penalty, 2),
                number_format($systemPenalty, 2)
            );
        }
        
        return $match;
    }

    // ============================================
    // SCOPES
    // ============================================

    public function scopeGenerated($query)
    {
        return $query->where('status', 'generated');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeMismatched($query)
    {
        return $query->where('system_match', false);
    }

    public function scopeForContract($query, int $contractId)
    {
        return $query->where('contract_id', $contractId);
    }
}
