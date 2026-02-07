<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penalty Notifications (Bildirg'inoma) - Audit log for penalty notifications
 * 
 * Contract clause 8.2 compliance tracking:
 * - Stores all generated penalty notifications
 * - Immutable after generation (for legal audit)
 * - Contains full calculation details for verification
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penalty_notifications', function (Blueprint $table) {
            $table->id();
            
            // References
            $table->foreignId('contract_id')->constrained('contracts')->onDelete('cascade');
            $table->foreignId('payment_schedule_id')->nullable()->constrained('payment_schedules')->onDelete('set null');
            $table->foreignId('payment_id')->nullable()->constrained('payments')->onDelete('set null');
            
            // Notification details
            $table->string('notification_number')->unique(); // Bildirg'inoma raqami
            $table->date('notification_date');              // Generation date
            
            // Contract reference data (snapshot at time of generation)
            $table->string('contract_number');              // Shartnoma raqami
            $table->string('tenant_name');                  // Ijarachi nomi
            $table->string('lot_number');                   // Lot raqami
            
            // Calculation inputs (snapshot)
            $table->date('due_date');                       // Oxirgi muddat
            $table->date('payment_date');                   // To'lov sanasi (actual or calculated as of)
            $table->decimal('overdue_amount', 15, 2);       // Qarz summasi
            $table->integer('overdue_days');                // Kechikish kunlari
            
            // Calculation outputs
            $table->decimal('penalty_rate', 5, 4)->default(0.004); // 0.4% = 0.004
            $table->decimal('calculated_penalty', 15, 2);   // Hisoblangan penya
            $table->decimal('max_penalty', 15, 2);          // Maksimal penya (50%)
            $table->boolean('cap_applied')->default(false); // 50% cap qo'llanganmi
            $table->decimal('final_penalty', 15, 2);        // Yakuniy penya
            
            // Formula (stored for verification)
            $table->text('formula_text');                   // "9,568,029 × 0.4% × 5 = 191,361 UZS"
            $table->text('legal_basis');                    // "Shartnomaning 8.2-bandi asosida"
            
            // Full notification text
            $table->text('notification_text_uz');           // O'zbekcha matni
            
            // Audit
            $table->string('generated_by')->nullable();     // Kim tomonidan yaratilgan
            $table->string('pdf_path')->nullable();         // PDF fayl manzili
            $table->timestamp('pdf_generated_at')->nullable();
            
            // Status
            $table->enum('status', [
                'generated',   // Yaratilgan
                'sent',        // Yuborilgan
                'acknowledged' // Qabul qilingan
            ])->default('generated');
            
            // Verification (calculator vs system match)
            $table->boolean('system_match')->default(true); // Tizim bilan mos keladi
            $table->decimal('system_penalty', 15, 2)->nullable(); // Tizimdagi penya
            $table->text('mismatch_reason')->nullable();    // Nomuvofiqlik sababi
            
            $table->timestamps();
            
            // Indexes
            $table->index(['contract_id', 'notification_date']);
            $table->index('notification_number');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_notifications');
    }
};
