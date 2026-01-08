<?php

namespace App\Http\Controllers;

use App\Services\SymbolService;
use Illuminate\Http\Request;

class SymbolController extends Controller
{
    protected SymbolService $symbolService;

    public function __construct(SymbolService $symbolService)
    {
        $this->symbolService = $symbolService;
    }

    public function index(Request $request)
    {
        $q = $request->query('q');
        $exchange = $request->query('exchange');
        $segment = $request->query('segment');
        $token = $request->query('token');
        $exact = $request->boolean('exact', false);
        $limit = (int) $request->query('limit', 200);
        $refresh = $request->boolean('refresh', false);

        if ($refresh) {
            // force refresh cache
            $this->symbolService->getMaster(true);
        }

        $filters = [
            'exchange' => $exchange,
            'segment' => $segment,
            'token' => $token,
            'exact' => $exact,
            'limit' => $limit,
        ];

        $results = $this->symbolService->search($q, $filters);

        return response()->json([
            'status' => true,
            'count' => count($results),
            'data' => $results,
        ]);
    }
}
