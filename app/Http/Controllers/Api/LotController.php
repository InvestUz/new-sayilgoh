<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lot;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

/**
 * Lot (Obyekt) Controller - CRUD operatsiyalari
 */
class LotController extends Controller
{
    /**
     * Barcha lotlar ro'yxati
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Lot::query();

            // Qidiruv
            if ($search = $request->get('search')) {
                $query->where(function($q) use ($search) {
                    $q->where('lot_raqami', 'like', "%{$search}%")
                      ->orWhere('obyekt_nomi', 'like', "%{$search}%")
                      ->orWhere('manzil', 'like', "%{$search}%")
                      ->orWhere('tuman', 'like', "%{$search}%");
                });
            }

            // Holat bo'yicha filter
            if ($holat = $request->get('holat')) {
                $query->where('holat', $holat);
            }

            $perPage = $request->get('per_page', 50);
            $lots = $query->latest()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $lots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ma\'lumotlarni yuklashda xatolik: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Yangi lot yaratish
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'lot_raqami' => 'required|string|unique:lots,lot_raqami',
                'obyekt_nomi' => 'required|string|max:255',
                'manzil' => 'nullable|string',
                'tuman' => 'nullable|string|max:100',
                'kocha' => 'nullable|string|max:255',
                'uy_raqami' => 'nullable|string|max:50',
                'maydon' => 'required|numeric|min:0',
                'tavsif' => 'nullable|string',
                'latitude' => 'nullable|numeric',
                'longitude' => 'nullable|numeric',
                'rasmlar' => 'nullable|array',
                'boshlangich_narx' => 'nullable|numeric|min:0',
            ]);

            $validated['obyekt_turi'] = 'savdo';
            $lot = Lot::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Lot muvaffaqiyatli yaratildi',
                'data' => $lot
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
     * Bitta lotni ko'rish
     */
    public function show(Lot $lot): JsonResponse
    {
        $lot->load(['contracts.tenant', 'contracts.paymentSchedules']);

        return response()->json([
            'success' => true,
            'data' => $lot,
            'formatted' => [
                'maydon' => $lot->formatted_maydon,
                'toliq_manzil' => $lot->toliq_manzil,
                'obyekt_turi' => $lot->obyekt_turi_nomi,
                'holat' => $lot->holat_nomi,
            ]
        ]);
    }

    /**
     * Lotni yangilash
     */
    public function update(Request $request, Lot $lot): JsonResponse
    {
        $validated = $request->validate([
            'lot_raqami' => ['sometimes', 'required', 'string', Rule::unique('lots')->ignore($lot->id)],
            'obyekt_nomi' => 'sometimes|required|string|max:255',
            'manzil' => 'sometimes|required|string',
            'tuman' => 'nullable|string|max:100',
            'kocha' => 'nullable|string|max:255',
            'uy_raqami' => 'nullable|string|max:50',
            'maydon' => 'sometimes|required|numeric|min:0',
            'tavsif' => 'nullable|string',
            'obyekt_turi' => 'sometimes|required|in:savdo,xizmat,ishlab_chiqarish,ombor,ofis,boshqa',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'rasmlar' => 'nullable|array',
            'boshlangich_narx' => 'nullable|numeric|min:0',
            'holat' => 'sometimes|in:bosh,ijarada,band,tamirlashda',
            'is_active' => 'sometimes|boolean',
        ]);

        $lot->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Lot muvaffaqiyatli yangilandi',
            'data' => $lot
        ]);
    }

    /**
     * Lotni o'chirish
     */
    public function destroy(Lot $lot): JsonResponse
    {
        if ($lot->holat === 'ijarada') {
            return response()->json([
                'success' => false,
                'message' => 'Ijarada bo\'lgan lotni o\'chirib bo\'lmaydi'
            ], 422);
        }

        $lot->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lot muvaffaqiyatli o\'chirildi'
        ]);
    }

    /**
     * Bo'sh lotlar ro'yxati (dropdown uchun)
     */
    public function available(): JsonResponse
    {
        try {
            $lots = Lot::where('is_active', true)
                ->where('holat', 'bosh')
                ->select('id', 'lot_raqami', 'obyekt_nomi', 'maydon', 'tuman', 'boshlangich_narx')
                ->orderBy('lot_raqami')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $lots
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Tumanlar ro'yxati (filter uchun)
     */
    public function districts(): JsonResponse
    {
        try {
            $districts = Lot::whereNotNull('tuman')
                ->where('tuman', '!=', '')
                ->distinct()
                ->pluck('tuman');

            return response()->json([
                'success' => true,
                'data' => $districts
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => true, 'data' => []]);
        }
    }
}
