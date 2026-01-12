<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SymbolService
{
    protected string $masterUrl = 'https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json';
    protected string $cacheKey = 'angel_symbol_master';
    protected int $cacheSeconds;

    public function __construct()
    {
        // default cache TTL (6 hours)
        $this->cacheSeconds = config('services.angel.symbol_cache_seconds', 60 * 60 * 6);
    }

    public function getMaster(bool $forceRefresh = false): array
    {
        if (! $forceRefresh && Cache::has($this->cacheKey)) {
            $data = Cache::get($this->cacheKey, []);
            if (is_array($data)) {
                return $data;
            }
        }

        // Attempt to fetch remote file
        try {
            $resp = Http::timeout(20)->get($this->masterUrl);
            if ($resp->ok()) {
                $json = $resp->json();
                if (is_array($json)) {
                    Cache::put($this->cacheKey, $json, $this->cacheSeconds);
                    return $json;
                }
            }
        } catch (\Exception $e) {
            // ignore and fall through to cached value if present
        }

        return Cache::get($this->cacheKey, []);
    }

    public function search(?string $query = null, array $filters = []): array
    {
        $master = $this->getMaster();

        if (empty($master)) {
            return [];
        }

        $q = $query !== null ? trim($query) : null;
        $qLower = $q ? mb_strtolower($q) : null;
        $exchange = isset($filters['exchange']) ? strtoupper($filters['exchange']) : null;
        $segment = isset($filters['segment']) ? strtoupper($filters['segment']) : null;
        $tokenFilter = isset($filters['token']) ? (string)$filters['token'] : null;
        $exact = isset($filters['exact']) ? (bool)$filters['exact'] : false;
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
        if ($limit <= 0) $limit = 200;

        $results = [];

        foreach ($master as $row) {
            // typical row fields include: "name", "symbol", "symboltoken", "exchange", "segment", etc.
            $name = isset($row['name']) ? (string)$row['name'] : (isset($row['symbol']) ? (string)$row['symbol'] : '');
            $symbol = isset($row['symbol']) ? (string)$row['symbol'] : '';
            $token = isset($row['symboltoken']) ? (string)$row['symboltoken'] : (isset($row['token']) ? (string)$row['token'] : '');

            // filters by exchange/segment/token
            if ($exchange && isset($row['exchange']) && strtoupper($row['exchange']) !== $exchange) continue;
            if ($segment && isset($row['segment']) && strtoupper($row['segment']) !== $segment) continue;
            if ($tokenFilter && $token !== $tokenFilter) continue;

            if ($qLower) {
                $found = false;
                if ($exact) {
                    if (mb_strtolower($symbol) === $qLower || mb_strtolower($name) === $qLower || $token === $q) {
                        $found = true;
                    }
                } else {
                    if (mb_stripos($symbol, $q) !== false || mb_stripos($name, $q) !== false || mb_stripos($token, $q) !== false) {
                        $found = true;
                    }
                }
                if (! $found) continue;
            }

            // push minimal useful fields for frontend
            $results[] = [
                'symbol' => $symbol,
                'name' => $name,
                'token' => $token,
                'exchange' => $row['exchange'] ?? null,
                'segment' => $row['segment'] ?? null,
                'expiry' => $row['expiry'] ?? null,
                'strike' => $row['strike'] ?? null,
                'optionType' => $row['optiontype'] ?? ($row['optionType'] ?? null),
            ];

            if (count($results) >= $limit) break;
        }

        return $results;
    }
}



