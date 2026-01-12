<?php

namespace App\Services;

use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;

class MarketDataService
{
    private Client $client;
    private string $baseUrl = "https://apiconnect.angelone.in"; 

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);
    }

    /**
     * Fetch Top Gainers or Losers
     * @param string $datatype   'gainers', 'losers', 'oi_gainers', 'oi_losers'
     * @param string $expirytype 'NEAR', 'NEXT', 'FAR'
     * @return array
     */
    public function getGainersLosers(string $datatype, string $expirytype = 'NEAR'): array
    {
        $jwt = session('jwt');
        if (empty($jwt)) {
            return [
                'status' => false,
                'message' => 'No JWT found in session. Please login first.',
                'data' => null
            ];
        }

        // --- FIX: Map simple input to Angel One specific constants ---
        $map = [
            'gainers'    => 'PercPriceGainers',
            'losers'     => 'PercPriceLosers',
            'oi_gainers' => 'PercOIGainers',
            'oi_losers'  => 'PercOILosers',
        ];

        // Default to Price Gainers if invalid key provided
        $apiDataType = $map[$datatype] ?? 'PercPriceGainers';

        $endpoint = "{$this->baseUrl}/rest/secure/angelbroking/marketData/v1/gainersLosers";
        
        $payload = [
            'datatype'   => $apiDataType,
            'expirytype' => strtoupper($expirytype) // Ensure uppercase
        ];

        try {
            $response = $this->client->post($endpoint, [
                'headers' => [
                    'X-PrivateKey'     => config('services.angel.api_key'),
                    'X-UserType'       => 'USER',
                    'X-SourceID'       => 'WEB',
                    'Authorization'    => 'Bearer ' . $jwt,
                    'Content-Type'     => 'application/json',
                    'Accept'           => 'application/json',
                    'X-ClientLocalIP'  => '127.0.0.1', // Ensure this matches registered IP if Static IP mode is on
                    'X-ClientPublicIP' => '127.0.0.1',
                    'X-MACAddress'     => '00:00:00:00:00:00',
                ],
                'json' => $payload,
                'http_errors' => false, 
            ]);

            $raw = json_decode($response->getBody()->getContents(), true);

            if (empty($raw) || !isset($raw['status']) || !$raw['status']) {
                return [
                    'status'    => false,
                    'message'   => $raw['message'] ?? 'Market Data API failed',
                    'errorcode' => $raw['errorcode'] ?? null,
                    'data'      => null,
                ];
            }

            return [
                'status'  => true,
                'message' => 'SUCCESS',
                'data'    => $raw['data'] ?? [],
            ];

        } catch (Exception $e) {
            Log::error("Angel MarketData Error: " . $e->getMessage());
            return [
                'status'  => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'data'    => null,
            ];
        }
    }
}