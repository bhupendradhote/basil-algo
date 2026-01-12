<?php

namespace App\Http\Controllers;

use App\Services\AngelOneService;
use App\Services\MarketDataService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketDataController extends Controller
{
    /**
     * Get Gainers or Losers
     * * Endpoint: /api/market/gainers-losers
     * Query Params: ?datatype=gainers&expirytype=NEAR
     */
    public function gainersLosers(
        Request $request, 
        MarketDataService $marketData, 
        AngelOneService $auth
    ): JsonResponse
    {
        // 1. Validation
        $request->validate([
            'datatype' => 'required|in:gainers,losers',
            'expirytype' => 'nullable|in:NEAR,NEXT,FAR',
        ]);

        $datatype = $request->query('datatype');
        $expirytype = $request->query('expirytype', 'NEAR');

        // 2. Auth Check (Re-login if session expired)
        if (!session('jwt')) {
            try {
                $auth->login();
            } catch (\Exception $e) {
                return response()->json([
                    'status' => false,
                    'message' => 'Login failed: ' . $e->getMessage()
                ], 401);
            }
        }

        // 3. Call Service
        $res = $marketData->getGainersLosers($datatype, $expirytype);

        // 4. Response
        if (!$res['status']) {
            return response()->json($res, 400);
        }

        return response()->json([
            'status'  => true,
            'message' => $res['message'],
            'data'    => $res['data'],
        ]);
    }
}