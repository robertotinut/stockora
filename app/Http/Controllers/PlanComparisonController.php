<?php

namespace App\Http\Controllers;

use App\Services\SubscriptionPlanService;

class PlanComparisonController extends Controller
{
    public function index(SubscriptionPlanService $planService)
    {
        $comparison = $planService->getComparisonData(true);

        return view('plans.index', [
            'plans' => $comparison['plans'],
            'groupedFeatures' => $comparison['groupedFeatures'],
            'matrix' => $comparison['matrix'],
            'support' => config('stockora.support', []),
        ]);
    }
}
