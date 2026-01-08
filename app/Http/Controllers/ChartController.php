<?php

namespace App\Http\Controllers;

use App\Services\AngelOneService;
use Illuminate\Http\Request;

class ChartController extends Controller
{
public function login(Request $request, AngelOneService $angel)
    {
        $data = $angel->login();

        $symbol = $request->query('symbol', config('services.angel.default_symbol', '2885'));

        return response()->json([
            'jwt'    => session('jwt'),
            'feed'   => session('feed'),
            'symbol' => $symbol, // Correct key => value syntax
        ]);

        console.log('Login Data:', $data, 'symbol:', $symbol);
    }

    public function history(Request $request, AngelOneService $angel)
    {
        $symbol = $request->query('symbol', config('services.angel.default_symbol', '2885'));
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

    public function chart()
    {
        return view('chart');
    }

    public function wsToken(AngelOneService $angel)
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

}
