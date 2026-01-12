<?php

namespace App\Http\Controllers;

use App\Services\AngelOneService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AngelController extends Controller
{
    public function login(Request $request, AngelOneService $angel): JsonResponse
    {
        $data = $angel->login();

        $symbol = $request->query('symbol', config('services.angel.default_symbol', '99926000'));

        return response()->json([
            'jwt'    => session('jwt'),
            'feed'   => session('feed'),
            'symbol' => $symbol,
        ]);
    }

    public function history(Request $request, AngelOneService $angel): JsonResponse
    {
        $symbol = $request->query('symbol', config('services.angel.default_symbol', '99926000'));
        $interval = strtoupper($request->query('interval', 'FIFTEEN_MINUTE'));
        $from = $request->query('from'); // optional
        $to = $request->query('to');     // optional

        if (!session('jwt')) {
            $angel->login();
        }

        if (empty($from) || empty($to)) {
            $maxDays = $angel->getMaxDaysForInterval($interval);
            $tz = new \DateTimeZone('Asia/Kolkata');
            $toDt = new \DateTime('now', $tz);
            $fromDt = (clone $toDt)->modify("-{$maxDays} days");
            $fromDt->setTime(9, 15, 0);
            $from = $fromDt->format('Y-m-d H:i');
            $to = $toDt->format('Y-m-d H:i');
        }

        $res = $angel->historical($symbol, $interval, $from, $to);

        if (empty($res['status']) || !$res['status']) {
            return response()->json($res, 400);
        }

        return response()->json([
            'status' => true,
            'message' => 'OK',
            'data' => $res['data'],
        ]);
    }

    public function dashboard()
    {
        return view('dashboard');
    }

    public function wsToken(AngelOneService $angel): JsonResponse
    {
        if (!session()->has('jwt') || !session()->has('feed')) {
            $angel->login(); // force login
        }

        return response()->json([
            'jwt'         => session('jwt'),
            'feed'        => session('feed'),
            'client_code' => config('services.angel.client_code'),
            'api_key'     => config('services.angel.api_key'),
        ]);
    }

    public function quote(Request $request, AngelOneService $angel): JsonResponse
    {
        $single = $request->query('symbol');
        $multi = $request->query('symbols');

        $mode = $request->query('mode', 'FULL');

        $symbols = [];

        if (!empty($multi)) {
            if (is_array($multi)) {
                $symbols = $multi;
            } else {
                $symbols = array_filter(array_map('trim', explode(',', (string)$multi)));
            }
        } elseif (!empty($single)) {
            $symbols = [trim((string)$single)];
        } else {
            // fallback to default single symbol from config
            $symbols = [config('services.angel.default_symbol', '99926000')];
        }

        if (!session('jwt')) {
            $angel->login();
        }

        $res = $angel->quote($symbols, $mode);

        if (empty($res['status']) || !$res['status']) {
            $statusCode = 400;
            return response()->json($res, $statusCode);
        }

        return response()->json([
            'status' => true,
            'message' => $res['message'] ?? 'OK',
            'data' => $res['data'],
        ]);
    }
}
