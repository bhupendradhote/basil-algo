<?php

namespace App\Services;

use GuzzleHttp\Client;
use OTPHP\TOTP;
use DateTime;
use DateTimeZone;
use Exception;
use Psr\Http\Message\ResponseInterface;

class AngelOneService
{
    private Client $client;
    private string $baseUrl = "https://apiconnect.angelbroking.com";
    private string $marketBaseUrl = "https://apiconnect.angelone.in"; // quote endpoint base
    private DateTimeZone $tz;

    public function __construct()
    {
        $this->client = new Client([
            'timeout' => 30,
            'connect_timeout' => 10,
        ]);

        $this->tz = new DateTimeZone('Asia/Kolkata');
    }

    /**
     * Login and store JWT/feed tokens in session.
     *
     * @return array
     * @throws Exception
     */
    public function login(): array
    {
        $totp = TOTP::create(config('services.angel.totp_secret'))->now();

        $response = $this->client->post(
            "{$this->baseUrl}/rest/auth/angelbroking/user/v1/loginByPassword",
            [
                'headers' => [
                    'X-PrivateKey' => config('services.angel.api_key'),
                    'X-UserType' => 'USER',
                    'X-SourceID' => 'WEB',
                    'X-ClientLocalIP' => '127.0.0.1',
                    'X-ClientPublicIP' => '127.0.0.1',
                    'X-MACAddress' => '00:00:00:00:00:00',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => [
                    'clientcode' => config('services.angel.client_code'),
                    'password' => config('services.angel.password'),
                    'totp' => $totp,
                ],
                'http_errors' => false,
            ]
        );

        $data = json_decode($response->getBody()->getContents(), true);

        if (empty($data['status']) || !$data['status']) {
            throw new Exception($data['message'] ?? 'Angel login failed');
        }

        session([
            'jwt' => $data['data']['jwtToken'],
            'feed' => $data['data']['feedToken'],
        ]);

        return $data;
    }

    /**
     * Public accessor to get max days allowed for an interval (SmartAPI rules)
     */
    public function getMaxDaysForInterval(string $interval): int
    {
        $map = $this->intervalMaxDaysMap();
        $intervalUpper = strtoupper($interval);
        return $map[$intervalUpper] ?? 30; // fallback 30
    }

    /**
     * Map of interval -> maximum days per single request (from your spec)
     */
    protected function intervalMaxDaysMap(): array
    {
        return [
            'ONE_MINUTE'   => 30,
            'THREE_MINUTE' => 60,
            'FIVE_MINUTE'  => 100,
            'TEN_MINUTE'   => 100,
            'FIFTEEN_MINUTE'=> 200,
            'THIRTY_MINUTE'=> 200,
            'ONE_HOUR'     => 400,
            'ONE_DAY'      => 2000,
        ];
    }

    /**
     * Fetch historical candle data from Angel's historical API — supports automatic chunking.
     */
    public function historical(string $symbolToken, string $interval, ?string $from, ?string $to): array
    {
        $jwt = session('jwt');

        if (empty($jwt)) {
            return [
                'status' => false,
                'message' => 'No JWT found in session. Please login first.',
                'data' => null
            ];
        }

        $intervalUpper = strtoupper($interval);
        $maxDays = $this->getMaxDaysForInterval($intervalUpper);

        $now = new DateTime('now', $this->tz);

        if (empty($to)) {
            $toDt = clone $now;
        } else {
            try {
                $toDt = new DateTime($to, $this->tz);
            } catch (Exception $e) {
                $toDt = clone $now;
            }
        }

        if (empty($from)) {
            $fromDt = (clone $toDt)->modify("-{$maxDays} days");
            $fromDt->setTime(9, 15, 0);
        } else {
            try {
                $fromDt = new DateTime($from, $this->tz);
            } catch (Exception $e) {
                $fromDt = (clone $toDt)->modify("-{$maxDays} days");
                $fromDt->setTime(9, 15, 0);
            }
        }

        if ($fromDt > $toDt) {
            $tmp = $fromDt;
            $fromDt = $toDt;
            $toDt = $tmp;
        }

        $combinedRawData = [];
        $currentStart = clone $fromDt;

        try {
            while ($currentStart <= $toDt) {
                $chunkEnd = (clone $currentStart)->modify('+' . ($maxDays - 1) . ' days');

                if ($chunkEnd > $toDt) {
                    $chunkEnd = clone $toDt;
                }

                $payload = [
                    'exchange' => 'NSE',
                    'symboltoken' => (string)$symbolToken,
                    'interval' => $intervalUpper,
                    'fromdate' => $currentStart->format('Y-m-d H:i'),
                    'todate' => $chunkEnd->format('Y-m-d H:i'),
                ];

                $response = $this->client->post(
                    "{$this->baseUrl}/rest/secure/angelbroking/historical/v1/getCandleData",
                    [
                        'headers' => [
                            'X-PrivateKey' => config('services.angel.api_key'),
                            'X-UserType' => 'USER',
                            'X-SourceID' => 'WEB',
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $jwt,
                            'X-ClientLocalIP' => '127.0.0.1',
                            'X-ClientPublicIP' => '127.0.0.1',
                            'X-MACAddress' => '00:00:00:00:00:00',
                        ],
                        'json' => $payload,
                        'http_errors' => false,
                        'timeout' => 60,
                    ]
                );

                $raw = json_decode($response->getBody()->getContents(), true);

                if (empty($raw) || !isset($raw['status']) || !$raw['status']) {
                    return [
                        'status' => false,
                        'message' => $raw['message'] ?? 'Historical API returned error for chunk',
                        'errorcode' => $raw['errorcode'] ?? null,
                        'failed_chunk' => [
                            'from' => $currentStart->format('Y-m-d H:i'),
                            'to' => $chunkEnd->format('Y-m-d H:i'),
                            'payload' => $payload,
                        ],
                        'raw' => $raw,
                        'data' => null
                    ];
                }

                if (!empty($raw['data']) && is_array($raw['data'])) {
                    foreach ($raw['data'] as $r) {
                        $combinedRawData[] = $r;
                    }
                }

                $currentStart = (clone $chunkEnd)->modify('+1 second');
                if (count($combinedRawData) > 500000) {
                    break;
                }
            }

            $candles = [];
            $seenTimestamps = [];

            foreach ($combinedRawData as $row) {
                if (!isset($row[0])) continue;

                try {
                    $dt = new DateTime($row[0], $this->tz);
                } catch (Exception $e) {
                    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $row[0], $this->tz);
                    if ($dt === false) {
                        continue;
                    }
                }

                $timestamp = $dt->getTimestamp();

                if (isset($seenTimestamps[$timestamp])) continue;
                $seenTimestamps[$timestamp] = true;

                $open  = isset($row[1]) ? (float)$row[1] : 0.0;
                $high  = isset($row[2]) ? (float)$row[2] : $open;
                $low   = isset($row[3]) ? (float)$row[3] : $open;
                $close = isset($row[4]) ? (float)$row[4] : $open;

                $candles[] = [
                    'time'  => $timestamp,
                    'open'  => $open,
                    'high'  => $high,
                    'low'   => $low,
                    'close' => $close,
                ];
            }

            usort($candles, function ($a, $b) {
                return $a['time'] <=> $b['time'];
            });

            return [
                'status' => true,
                'message' => 'OK',
                'data' => $candles
            ];

        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Exception: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }


public function quote($symbols, string $mode = 'FULL', string $exchange = 'NSE'): array
{
    // Normalize symbols to array
    if (!is_array($symbols)) {
        $symbols = [$symbols];
    }

    $symbols = array_values(array_filter(array_map('strval', $symbols)));

    if (empty($symbols)) {
        return [
            'status' => false,
            'message' => 'No symbol tokens provided',
            'data' => null,
        ];
    }

    // Ensure JWT
    if (!session('jwt')) {
        try {
            $this->login();
        } catch (Exception $e) {
            return [
                'status' => false,
                'message' => 'Login failed: ' . $e->getMessage(),
                'data' => null,
            ];
        }
    }

    $payload = [
        'mode' => strtoupper($mode),
        'exchangeTokens' => [
            strtoupper($exchange) => $symbols
        ]
    ];

    try {
        $response = $this->client->post(
            "{$this->marketBaseUrl}/rest/secure/angelbroking/market/v1/quote/",
            [
                'headers' => [
                    'X-PrivateKey' => config('services.angel.api_key'),
                    'X-UserType' => 'USER',
                    'X-SourceID' => 'WEB',
                    'Authorization' => 'Bearer ' . session('jwt'),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'X-ClientLocalIP' => '127.0.0.1',
                    'X-ClientPublicIP' => '127.0.0.1',
                    'X-MACAddress' => '00:00:00:00:00:00',
                ],
                'json' => $payload,
                'http_errors' => false,
                'timeout' => 20,
            ]
        );

        $raw = json_decode($response->getBody()->getContents(), true);

        if (!isset($raw['status']) || !$raw['status']) {
            return [
                'status' => false,
                'message' => $raw['message'] ?? 'Quote API error',
                'errorcode' => $raw['errorcode'] ?? null,
                'raw' => $raw,
                'data' => null,
            ];
        }

        return [
            'status' => true,
            'message' => 'SUCCESS',
            'data' => $raw['data'],
        ];

    } catch (Exception $e) {
        return [
            'status' => false,
            'message' => 'Exception: ' . $e->getMessage(),
            'data' => null,
        ];
    }
}

}
