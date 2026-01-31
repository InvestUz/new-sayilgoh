<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * Ijarachi (Tenant) Controller - CRUD operatsiyalari
 */
class TenantController extends Controller
{
    /**
     * Barcha ijarachilar ro'yxati
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Tenant::query();

            // Qidiruv
            if ($search = $request->get('search')) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('inn', 'like', "%{$search}%")
                      ->orWhere('director_name', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 50);
            $tenants = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $tenants
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => ['data' => []]]);
        }
    }

    /**
     * Yangi ijarachi yaratish
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'type' => 'sometimes|in:yuridik,jismoniy',
                'inn' => 'required|string|unique:tenants,inn',
                'director_name' => 'nullable|string|max:255',
                'passport_serial' => 'nullable|string|max:50',
                'phone' => 'required|string|max:50',
                'email' => 'nullable|email|max:255',
                'address' => 'nullable|string',
                'bank_name' => 'nullable|string|max:255',
                'bank_account' => 'nullable|string|max:50',
                'bank_mfo' => 'nullable|string|max:10',
                'oked' => 'nullable|string|max:20',
            ]);

            $validated['type'] = $validated['type'] ?? 'yuridik';
            $tenant = Tenant::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Ijarachi muvaffaqiyatli yaratildi',
                'data' => $tenant
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bitta ijarachini ko'rish
     */
    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->load(['contracts.lot', 'contracts.paymentSchedules']);

        return response()->json([
            'success' => true,
            'data' => $tenant,
            'statistics' => [
                'faol_shartnomalar' => $tenant->faol_shartnomalar_soni,
                'jami_qarzdorlik' => $tenant->jami_qarzdorlik,
            ]
        ]);
    }

    /**
     * Ijarachini yangilash
     */
    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:yuridik,jismoniy',
            'inn' => ['sometimes', 'required', 'string', Rule::unique('tenants')->ignore($tenant->id)],
            'director_name' => 'nullable|string|max:255',
            'passport_serial' => 'nullable|string|max:50',
            'phone' => 'sometimes|required|string|max:50',
            'email' => 'nullable|email|max:255',
            'address' => 'sometimes|required|string',
            'bank_name' => 'nullable|string|max:255',
            'bank_account' => 'nullable|string|max:50',
            'bank_mfo' => 'nullable|string|max:10',
            'oked' => 'nullable|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $tenant->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Ijarachi muvaffaqiyatli yangilandi',
            'data' => $tenant
        ]);
    }

    /**
     * Ijarachini o'chirish
     */
    public function destroy(Tenant $tenant): JsonResponse
    {
        // Faol shartnomasi bor-yo'qligini tekshirish
        if ($tenant->contracts()->where('holat', 'faol')->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Faol shartnomasi bor ijarachini o\'chirib bo\'lmaydi'
            ], 422);
        }

        $tenant->delete();

        return response()->json([
            'success' => true,
            'message' => 'Ijarachi muvaffaqiyatli o\'chirildi'
        ]);
    }

    /**
     * Dropdown uchun ijarachilar ro'yxati
     */
    public function dropdown(): JsonResponse
    {
        try {
            $tenants = Tenant::where('is_active', true)
                ->select('id', 'name', 'inn', 'type')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $tenants
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }
}
