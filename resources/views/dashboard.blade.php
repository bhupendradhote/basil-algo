<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>Angel One Dashboard - Symbols + RELIANCE (Live)</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lightweight-charts@3.7.0/dist/lightweight-charts.standalone.production.js"></script>

    <style>
        body { font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial; margin: 20px; background:#0b1220; color:#cfe6ff; }
        .controls { display:flex; gap:12px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
        label { display:flex; gap:8px; align-items:center; }
        select, input, button { padding:8px; border-radius:6px; border:1px solid #333; background:#0f1724; color:#dbeafe; }
        #chart { width:100%; height:540px; background:#071024; border-radius:8px; padding:8px; }
        .ltp { font-size:28px; font-weight:700; margin-top:8px; color:#b7f5c6; }
        .status { font-size:13px; color:#9fb3cc; margin-top:6px; }
        .small { font-size:12px; color:#92b6d6; }
        .legend { margin-left:6px; padding:8px; border-radius:6px; background: rgba(255,255,255,0.02); border:1px solid rgba(255,255,255,0.03); }
        .control-group { display:flex; gap:8px; align-items:center; }
    </style>
</head>
<body>

<h1>📈 Symbols + Historical (Auto) — Angel One (Live)</h1>

<div class="controls">
    <div class="control-group">
        <label>
            Interval:
            <select id="interval">
                <option value="ONE_MINUTE">ONE_MINUTE</option>
                <option value="THREE_MINUTE">THREE_MINUTE</option>
                <option value="FIVE_MINUTE" selected>FIVE_MINUTE</option>
                <option value="TEN_MINUTE">TEN_MINUTE</option>
                <option value="FIFTEEN_MINUTE">FIFTEEN_MINUTE</option>
                <option value="THIRTY_MINUTE">THIRTY_MINUTE</option>
                <option value="ONE_HOUR">ONE_HOUR</option>
                <option value="ONE_DAY">ONE_DAY</option>
            </select>
        </label>
        <div class="legend small" id="intervalDesc">15 Minute — max days per request: 200</div>
    </div>

    <div class="control-group" style="min-width: 420px;">
        <label style="flex:1;">
            Search symbol:
            <input id="symbolSearch" placeholder="Type symbol name (eg RELIANCE)"/>
        </label>

        <label>
            Results:
            <select id="symbolResults" style="min-width:260px"></select>
        </label>
    </div>

    <div class="control-group">
        <button id="login">Login (Laravel)</button>
    </div>

    <div style="margin-left:auto" class="small legend">
        Tip: click Login to fetch fresh jwt/feed then the bridge will stream live ticks.
    </div>
</div>

<div id="chart"></div>
<div class="ltp">LTP: <span id="ltp">--</span></div>
<div class="status" id="status">Status: idle</div>

<script>
(function(){
    const SYMBOLS_API = '/api/symbols';
    const HISTORY_API = '/api/history';
    const LARAVEL_LOGIN_API = '/api/login';
    const WSS_BRIDGE = 'ws://localhost:3001';

    // Interval metadata
    const intervalMeta = {
        ONE_MINUTE:   {desc: '1 Minute', maxDays: 30, secs: 60},
        THREE_MINUTE: {desc: '3 Minute', maxDays: 60, secs: 180},
        FIVE_MINUTE:  {desc: '5 Minute', maxDays: 100, secs: 300},
        TEN_MINUTE:   {desc: '10 Minute', maxDays: 100, secs: 600},
        FIFTEEN_MINUTE:{desc: '15 Minute', maxDays: 200, secs: 900},
        THIRTY_MINUTE:{desc: '30 Minute', maxDays: 200, secs: 1800},
        ONE_HOUR:     {desc: '1 Hour', maxDays: 400, secs: 3600},
        ONE_DAY:      {desc: '1 Day', maxDays: 2000, secs: 86400},
    };

    let selectedToken = '99926000'; // default Symbol
    let chart, candleSeries;
    let lastCandle = null;
    let ws = null;
    let jwtToken = null;
    let feedToken = null;
    let reconnectTimeout = null;
    let subscribedToken = null;

    // build chart
    function initChart() {
        const container = document.getElementById('chart');
        chart = LightweightCharts.createChart(container, {
            width: container.clientWidth,
            height: 540,
            layout: { backgroundColor: '#071024', textColor: '#cfe6ff' },
            grid: { vertLines: { color: '#101826' }, horzLines: { color: '#101826' } },
            timeScale: { timeVisible: true, secondsVisible: true }
        });
        candleSeries = chart.addCandlestickSeries({ upColor: '#4caf50', downColor: '#f44336', borderVisible: false, wickVisible: true });
        window.addEventListener('resize', () => chart.applyOptions({ width: container.clientWidth }));
    }

    initChart();

    // UI refs
    const $interval = $('#interval');
    const $intervalDesc = $('#intervalDesc');
    const $symbolSearch = $('#symbolSearch');
    const $symbolResults = $('#symbolResults');
    const $status = $('#status');
    const $ltp = $('#ltp');
    const $login = $('#login');

    function setStatus(t) { $status.text('Status: ' + t); }

    function updateIntervalDesc() {
        const val = $interval.val();
        const meta = intervalMeta[val] || {};
        $intervalDesc.text((meta.desc || val) + ' — max days per request: ' + (meta.maxDays || 'n/a'));
    }

    async function searchSymbols(q, limit = 500) {
        try {
            const url = new URL(SYMBOLS_API, window.location.origin);
            url.searchParams.set('q', q);
            url.searchParams.set('limit', limit);
            const r = await fetch(url.toString());
            if (!r.ok) { setStatus('symbols API error'); return []; }
            const payload = await r.json();
            return payload.data || [];
        } catch (err) {
            console.error(err);
            setStatus('symbols fetch failed');
            return [];
        }
    }

    function populateResults(items) {
        $symbolResults.empty();
        if (!items || items.length === 0) {
            $symbolResults.append(`<option value="">No results</option>`);
            return;
        }
        items.forEach(it => {
            const txt = `${it.symbol} • ${it.name || ''} • token:${it.token}`;
            const opt = $('<option/>').attr('value', it.token).text(txt).data('meta', it);
            $symbolResults.append(opt);
        });
        if (!selectedToken && items.length) {
            selectedToken = items[0].token;
            $symbolResults.val(selectedToken);
        }
    }

    async function loadHistoryForSelected() {
        if (!selectedToken) { setStatus('no token selected'); return; }
        setStatus('loading historical data for token ' + selectedToken + ' ...');
        const interval = $interval.val();
        try {
            const url = new URL(HISTORY_API, window.location.origin);
            url.searchParams.set('symbol', selectedToken);
            url.searchParams.set('interval', interval);

            const r = await fetch(url.toString());
            const payload = await r.json();
            const candles = payload.data || [];
            if (!Array.isArray(candles) || candles.length === 0) {
                setStatus('no candles returned');
                candleSeries.setData([]);
                lastCandle = null;
                return;
            }

            const seriesData = candles.map(c => ({
                time: Number(c.time), 
                open: Number(c.open),
                high: Number(c.high),
                low: Number(c.low),
                close: Number(c.close)
            }));

            candleSeries.setData(seriesData);
            lastCandle = seriesData[seriesData.length - 1] || null;
            setStatus('loaded ' + seriesData.length + ' candles');
        } catch (err) {
            console.error(err);
            setStatus('fetch error: ' + err.message);
        }
    }

    function debounce(fn, ms) {
        let t;
        function wrapper(...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), ms);
        }
        wrapper.flush = () => { clearTimeout(t); fn(); };
        return wrapper;
    }

    const debouncedSearch = debounce(async function() {
        const q = $symbolSearch.val().trim();
        if (q.length < 1) return;
        setStatus('searching symbols for "' + q + '" ...');
        const items = await searchSymbols(q, 100);
        populateResults(items);
        setStatus('found ' + items.length + ' symbols');
    }, 350);

    // ---------- WebSocket / Live ticking logic ----------

    function getIntervalSeconds() {
        const v = $interval.val();
        return (intervalMeta[v] && intervalMeta[v].secs) || 900;
    }

    function getBucketStart(epochSec, intervalSec) {
        return Math.floor(epochSec / intervalSec) * intervalSec;
    }

    // UPDATED: Now much simpler because server sends clean data
    function extractLTP(tick) {
        if (!tick) return null;
        if (tick.ltp !== undefined) return Number(tick.ltp);
        return null;
    }

    function handleTickMessage(msg) {
        // msg shape: { type: 'tick', tick: { ltp: 2500, token: "2885" }, ts: 12345678 }
        const payload = msg.tick;
        const ltp = extractLTP(payload);
        const tsSec = Math.floor(Date.now() / 1000);

        if (ltp !== null) {
            $ltp.text(Number(ltp).toFixed(2));
        } else {
            return;
        }

        const intervalSec = getIntervalSeconds();
        const bucket = getBucketStart(tsSec, intervalSec);

        if (!lastCandle) {
            lastCandle = { time: bucket, open: ltp, high: ltp, low: ltp, close: ltp };
            candleSeries.update(lastCandle);
            return;
        }

        if (bucket === lastCandle.time) {
            const newHigh = Math.max(lastCandle.high, ltp);
            const newLow = Math.min(lastCandle.low, ltp);
            lastCandle = { time: lastCandle.time, open: lastCandle.open, high: newHigh, low: newLow, close: ltp };
            candleSeries.update(lastCandle);
        } else if (bucket > lastCandle.time) {
            const newCandle = { time: bucket, open: lastCandle.close, high: Math.max(lastCandle.close, ltp), low: Math.min(lastCandle.close, ltp), close: ltp };
            lastCandle = newCandle;
            candleSeries.update(newCandle);
        }
    }

    function ensureWebSocketConnected(autoOpen = true) {
        if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
        if (!autoOpen) return;
        
        try {
            ws = new WebSocket(WSS_BRIDGE);
        } catch (err) {
            setStatus('ws open failed');
            scheduleReconnect();
            return;
        }

        ws.onopen = () => {
            setStatus('WS bridge connected');
            if (subscribedToken) sendSubscribe(subscribedToken);
        };

        ws.onmessage = (ev) => {
            let d;
            try { d = JSON.parse(ev.data); } catch (e) { return; }
            if (d.type === 'tick') {
                handleTickMessage(d);
            } else if (d.error) {
                console.warn('bridge error', d);
                setStatus('WS error: ' + d.error);
            }
        };

        ws.onclose = () => {
            setStatus('WS closed — reconnecting...');
            scheduleReconnect();
        };
    }

    function scheduleReconnect() {
        if (reconnectTimeout) return;
        reconnectTimeout = setTimeout(() => {
            reconnectTimeout = null;
            ensureWebSocketConnected(true);
        }, 3000);
    }

    function sendSubscribe(token) {
        if (!token) return;
        subscribedToken = token;
        const msg = { action: 'subscribe', tokens: [ String(token) ] };
        if (jwtToken) msg.jwt = jwtToken;
        if (feedToken) msg.feed = feedToken;
        
        if (!ws || ws.readyState !== WebSocket.OPEN) {
            ensureWebSocketConnected(true);
            return;
        }
        ws.send(JSON.stringify(msg));
    }

    // ---------- UI events ----------

    $symbolSearch.on('input', debouncedSearch);
    $symbolResults.on('change', function() {
        const newToken = $(this).val();
        if (!newToken) return;
        selectedToken = newToken;
        loadHistoryForSelected();
        sendSubscribe(selectedToken);
    });
    $interval.on('change', () => { updateIntervalDesc(); loadHistoryForSelected(); });
    
    $login.on('click', function() {
        setStatus('logging in...');
        $.ajax({
            url: LARAVEL_LOGIN_API,
            method: 'POST',
            success: function (res) {
                setStatus('Logged in. Connecting...');
                if (res && res.jwt) jwtToken = res.jwt;
                if (res && res.feed) feedToken = res.feed;
                if (!selectedToken) selectedToken = res.symbol || '99926000';
                ensureWebSocketConnected(true);
                setTimeout(() => sendSubscribe(selectedToken), 500);
            },
            error: function(err) { setStatus('login failed'); }
        });
    });

    // init
    (async function init(){
        updateIntervalDesc();
        await loadHistoryForSelected();
        ensureWebSocketConnected(true);
        setTimeout(() => sendSubscribe(selectedToken), 300);
    })();

})();
</script>

</body>
</html>