<?php

namespace App\Http\Controllers;

use App\Models\Lot;
use Illuminate\View\View;

class LotPageController extends Controller
{
    public function show(Lot $lot): View
    {
        $lot->load(['contracts.tenant', 'contracts.paymentSchedules']);

        $activeContract = $lot->contracts->where('holat', 'faol')->first();

        return view('lots.show', compact('lot', 'activeContract'));
    }
}
