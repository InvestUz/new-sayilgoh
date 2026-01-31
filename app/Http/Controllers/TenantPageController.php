<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TenantPageController extends Controller
{
    public function index(Request $request): View
    {
        $query = Tenant::with('activeContracts');

        // Search across all columns
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('inn', 'like', "%{$search}%")
                  ->orWhere('passport_serial', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('address', 'like', "%{$search}%")
                  ->orWhere('director_name', 'like', "%{$search}%")
                  ->orWhere('bank_name', 'like', "%{$search}%")
                  ->orWhere('bank_account', 'like', "%{$search}%")
                  ->orWhere('bank_mfo', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%");
            });
        }

        $tenants = $query->latest()->paginate(20)->withQueryString();

        return view('blade.tenants.index', compact('tenants'));
    }

    public function show(Tenant $tenant): View
    {
        $tenant->load(['contracts.lot', 'contracts.paymentSchedules']);

        // Ijarachining barcha lotlarini olish (faol shartnomalar orqali)
        $lots = Lot::whereHas('contracts', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)
              ->where('holat', 'faol');
        })->get();

        $stats = [
            'faol_lotlar' => $lots->count(),
            'jami_summa' => $tenant->contracts->sum('shartnoma_summasi'),
            'jami_qarz' => $tenant->contracts->sum(fn($c) => $c->paymentSchedules->sum('qoldiq_summa')),
        ];

        return view('tenants.show', compact('tenant', 'stats', 'lots'));
    }
}
