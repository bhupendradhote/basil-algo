<!DOCTYPE html>

<html>

<head>

    <meta charset="utf-8" />

    <title>Basil Star - Strategy</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

    <script src="https://unpkg.com/lightweight-charts@3.7.0/dist/lightweight-charts.standalone.production.js"></script>
    <style>
        body { font-family: Inter, system-ui, -apple-system, sans-serif; margin: 20px; background:#0b1220; color:#cfe6ff; }
        /* Layout */
        .controls { display:flex; gap:12px; align-items:center; margin-bottom:12px; flex-wrap:wrap; }
        .panel { background:#111c2e; padding:15px; border-radius:8px; border:1px solid #2d3748; margin-bottom:15px; }
        /* Inputs */
        label { display:flex; gap:8px; align-items:center; font-size: 13px;}
        select, input, button { padding:8px; border-radius:6px; border:1px solid #333; background:#0f1724; color:#dbeafe; outline:none; }
        button { cursor: pointer; background: #1f2937; }
        button:hover { background: #374151; }
        /* Chart */
        #chart { width:100%; height:450px; background:#071024; border-radius:8px; overflow:hidden; border:1px solid #1f2937; }
        /* Stats Grid */
        .grid-stats { display:grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap:10px; }
        .stat-box { background:#0b1220; padding:10px; border-radius:6px; border:1px solid #1f2937; display:flex; flex-direction:column; justify-content:center; }
        .stat-label { font-size:11px; text-transform:uppercase; letter-spacing:1px; color:#6b7280; margin-bottom:4px; font-weight:bold; }
        .stat-value { font-size:15px; font-weight:600; color: #fff; }
        .small { font-size:11px; color:#6b7280; margin-top:2px; }
        /* Signal Colors */
        .text-buy { color: #34d399; }
        .text-sell { color: #f87171; }
        .text-wait { color: #9ca3af; }
        .bg-buy { background: rgba(5, 150, 105, 0.2); border-color: #059669; color: #34d399; }
        .bg-sell { background: rgba(220, 38, 38, 0.2); border-color: #dc2626; color: #f87171; }
        .signal-badge {
            font-size:20px; font-weight:800; padding:10px; border-radius:6px;
            text-align:center; display:flex; flex-direction:column; align-items:center; justify-content:center;
            min-height: 60px;
        }
        /* Signal Table */
        .table-container { max-height: 400px; overflow-y: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th { text-align: left; padding: 10px; background: #0f1724; color: #9ca3af; font-size: 11px; text-transform: uppercase; position: sticky; top: 0; }
        td { padding: 8px 10px; border-bottom: 1px solid #1f2937; color: #d1d5db; }
        tr:hover { background: #1f2937; }
        .outcome-win { color: #34d399; font-weight: bold; }
        .outcome-loss { color: #f87171; font-weight: bold; }
        .outcome-open { color: #fbbf24; }
        /* Loader */
        #loader { position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(11,18,32,0.9); z-index:999; display:flex; align-items:center; justify-content:center; flex-direction:column; display:none; }
        /* Scan UI */
        #scan-results { display:none; margin-top:10px; }
        .scan-controls { display:flex; gap:8px; align-items:center; }
        #scan-progress { width:220px; height:10px; background:#0f1724; border-radius:6px; overflow:hidden; border:1px solid #1f2937; }
        #scan-progress-bar { height:100%; width:0%; background: linear-gradient(90deg, rgba(52,211,153,0.2), rgba(248,113,113,0.2)); }
        .clickable-row { cursor: pointer; }

        /* --- Improved Dropdown / Suggestions --- */
        .search-wrapper { position: relative; width:100%; max-width:520px; }
        .symbol-input { width:100%; box-sizing:border-box; }
        .suggestions { position:absolute; top:calc(100% + 6px); left:0; right:0; background:#071024; border:1px solid #1f2937; border-radius:8px; max-height:320px; overflow:auto; z-index:60; display:none; }
        .suggestion-item { padding:8px 10px; display:flex; justify-content:space-between; gap:8px; align-items:center; cursor:pointer; }
        .suggestion-item:hover, .suggestion-item.active { background:#0f1724; }
        .suggestion-left { display:flex; gap:8px; align-items:center; }
        .suggestion-symbol { font-weight:700; }
        .suggestion-name { font-size:12px; color:#9ca3af; }
        .suggestion-token { font-size:12px; color:#6b7280; }
        .suggestion-highlight { color:#fff; background:linear-gradient(90deg, rgba(255,255,255,0.06), rgba(255,255,255,0.02)); padding:0 4px; border-radius:3px; }
        .no-results { padding:10px; color:#9ca3af; }
        .suggestion-spinner { width:18px; height:18px; border-radius:50%; border:2px solid rgba(255,255,255,0.06); border-top-color:#34d399; animation:spin 1s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

    </style>

</head>

<body>


<div id="loader">
    <h2 id="loader-msg">Processing History...</h2>
</div>

<h1>📈 Basil Star Strategy + Signals</h1>

<div class="panel">
    <div style="margin-bottom:10px; font-weight:bold; color:#9ca3af; display:flex; justify-content:space-between;">
        <span>Strategy Monitor (Live)</span>
        <span id="ltp" style="color:#fff;">LTP: --</span>
    </div>
    <div class="grid-stats">
        <div class="stat-box">
            <div class="stat-label">1D Trend</div>
            <div class="stat-value" id="live-1d">--</div>
            <div class="small" id="live-1d-detail">EMA50 + Stoch</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">1H Filter</div>
            <div class="stat-value" id="live-1h">--</div>
            <div class="small" id="live-1h-detail">EMA9/50 + RSI</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">15m Trigger</div>
            <div class="stat-value" id="live-15m">--</div>
            <div class="small" id="live-15m-detail">HA EMA + MACD</div>
        </div>
        <div class="stat-box" style="grid-column: span 2;">
            <div class="signal-badge" id="live-signal">WAIT</div>
        </div>
    </div>
</div>

<div class="controls">
    <div class="control-group">
        <label>
            View:
            <select id="interval">
                <option value="FIFTEEN_MINUTE" selected>15 Minute (Strategy)</option>
                <option value="ONE_HOUR">1 Hour</option>
                <option value="ONE_DAY">1 Day</option>
            </select>
        </label>
    </div>

    <div class="control-group" style="flex-grow:1;">
        <div class="search-wrapper">
            <input id="symbolSearch" class="symbol-input" style="width:100%;" placeholder="Search Symbol (e.g. RELIANCE)..." autocomplete="off" />
            <div id="symbolDropdown" class="suggestions" role="listbox" aria-hidden="true"></div>
        </div>
    </div>

    <button id="login">Reconnect / Login</button>

    <!-- New Scan Button -->
    <div style="margin-left:auto;">
        <button id="scanBtn">Scan Stocks</button>
    </div>
</div>

<div id="chart"></div>
<div class="small" style="margin-top:5px; margin-bottom:15px;">* Markers indicate historical signals generated by the strategy logic.</div>

<div class="panel">
    <div style="margin-bottom:15px; font-weight:bold; color:#9ca3af; display:flex; justify-content:space-between; align-items:center;">
        <span>Historical Performance (Last 500 Candles)</span>
        <span class="small">Exit: 15m MA cross | Hold: 20 Candles</span>
    </div>

    <div class="grid-stats" style="margin-bottom:20px;">
        <div class="stat-box">
            <div class="stat-label">Total Signals</div>
            <div class="stat-value" id="bt-total">0</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Win Rate</div>
            <div class="stat-value" id="bt-rate" style="color:#fbbf24">0%</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Wins</div>
            <div class="stat-value text-buy" id="bt-wins">0</div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Losses</div>
            <div class="stat-value text-sell" id="bt-losses">0</div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Entry Price</th>
                    <th>Exit Time</th>
                    <th>Exit Price</th>
                    <th>Outcome</th>
                    <th>Profit/Loss %</th>
                </tr>
            </thead>
            <tbody id="signals-body">
            </tbody>
        </table>
    </div>
</div>

<!-- Scan results panel -->
<div id="scan-results" class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <div style="font-weight:bold; color:#9ca3af;">Scanner — current signals forming</div>
        <div style="display:flex; gap:10px; align-items:center;">
            <div class="scan-controls">
                <div id="scan-progress"><div id="scan-progress-bar"></div></div>
                <div id="scan-status" class="small" style="margin-left:8px;color:#9ca3af;">Idle</div>
            </div>
            <button id="stopScanBtn" style="display:none">Stop</button>
        </div>
    </div>

    <div style="margin-bottom:10px;">
        <span class="small">Results: <strong id="scan-count">0</strong></span>
    </div>

    <div class="table-container" style="max-height:340px;">
        <table>
            <thead>
                <tr>
                    <th>Symbol</th>
                    <th>Token</th>
                    <th>Signal</th>
                    <th>1D</th>
                    <th>1H</th>
                    <th>15m</th>
                </tr>
            </thead>
            <tbody id="scan-body"></tbody>
        </table>
    </div>
</div>

<script>
(function(){
    // --- Config ---
    const API = {
        SYMBOLS: '/api/symbols',
        HISTORY: '/api/history',
        LOGIN: '/api/login',
        WS: 'ws://localhost:3001'
    };
    const TF = { D: 'ONE_DAY', H: 'ONE_HOUR', M15: 'FIFTEEN_MINUTE' };

    // --- State ---
    let activeToken = '2885';
    let chart, series;
    let ws;
    let rawData = { [TF.D]: [], [TF.H]: [], [TF.M15]: [] };
    let scanning = false; // control flag to stop scan

    // --- Suggestion / Search State ---
    const dropdown = $('#symbolDropdown');
    const input = $('#symbolSearch');
    let suggestionIndex = -1;
    let currentSuggestions = [];
    let searchCache = new Map();
    let pendingRequest = null;

    // --- Chart Setup ---
    function initChart() {
        const el = document.getElementById('chart');
        chart = LightweightCharts.createChart(el, {
            width: el.clientWidth, height: 450,
            layout: { backgroundColor: '#071024', textColor: '#9ca3af' },
            grid: { vertLines: { color: '#1f2937' }, horzLines: { color: '#1f2937' } },
            timeScale: { timeVisible: true, secondsVisible: false, borderColor: '#2d3748' },
            rightPriceScale: { borderColor: '#2d3748' }
        });
        series = chart.addCandlestickSeries({ upColor: '#10b981', downColor: '#ef4444', borderVisible: false, wickVisible: true });
        window.onresize = () => chart.applyOptions({ width: el.clientWidth });
    }
    initChart();

    // --- Helper Functions (same as before) ---
    const calcEMA = (data, len, key='close') => {
        const k = 2/(len+1);
        let res = new Array(data.length).fill(null);
        if(!data.length) return res;
        let ema = data[0]?.[key];
        if(ema===undefined || ema===null) return res;
        res[0]=ema;
        for(let i=1; i<data.length; i++) {
            const val = (data[i]?.[key]===undefined || data[i]?.[key]===null) ? ema : data[i][key];
            ema = (val * k) + (ema * (1-k));
            res[i] = ema;
        }
        return res;
    };

    const calcRSI = (data, len=14) => {
        let res = new Array(data.length).fill(null);
        if(data.length < len) return res;
        let gain=0, loss=0;
        for(let i=1; i<=len; i++) {
            let chg = data[i].close - data[i-1].close;
            if(chg>0) gain+=chg; else loss+=Math.abs(chg);
        }
        let avgGain = gain/len, avgLoss = loss/len;
        res[len] = 100 - (100/(1+(avgGain/avgLoss)));
        for(let i=len+1; i<data.length; i++) {
            let chg = data[i].close - data[i-1].close;
            let g = chg>0?chg:0, l = chg<0?Math.abs(chg):0;
            avgGain = (avgGain*(len-1)+g)/len;
            avgLoss = (avgLoss*(len-1)+l)/len;
            res[i] = 100 - (100/(1+(avgGain/avgLoss)));
        }
        return res;
    };

    const calcStoch = (data, len=14) => {
        let kLine=[];
        for(let i=0; i<data.length; i++) {
            if(i<len-1) { kLine.push(50); continue; }
            let slice = data.slice(i-len+1, i+1);
            let low = Math.min(...slice.map(x=>x.low));
            let high = Math.max(...slice.map(x=>x.high));
            let k = high===low ? 50 : ((data[i].close-low)/(high-low))*100;
            kLine.push(k);
        }
        let kSmooth = calcEMA(kLine.map(x=>({close:x})), 3);
        let dSmooth = calcEMA(kSmooth.map(x=>({close:x})), 3);
        return { k: kSmooth, d: dSmooth };
    };

    const calcHA = (data) => {
        if(!data.length) return [];
        let res = [];
        let prev = { open: data[0].open, close: data[0].close };
        res.push({ ...data[0], ...prev });
        for(let i=1; i<data.length; i++) {
            let c = data[i];
            let haClose = (c.open + c.high + c.low + c.close)/4;
            let haOpen = (prev.open + prev.close)/2;
            let haHigh = Math.max(c.high, haOpen, haClose);
            let haLow = Math.min(c.low, haOpen, haClose);
            prev = { open: haOpen, close: haClose, high: haHigh, low: haLow, time: c.time };
            res.push(prev);
        }
        return res;
    };

    const calcMACD = (data) => {
        const e12 = calcEMA(data, 12);
        const e26 = calcEMA(data, 26);
        const macd = e12.map((v,i) => (v!==null && e26[i]!==null) ? v - e26[i] : null);
        const sig = calcEMA(macd.map(x=>({close:x||0})), 9);
        return { macd, sig };
    };

    function getSyncIndex(targetArr, timestamp) {
        if(!targetArr.length) return -1;
        let l=0, r=targetArr.length-1, ans=-1;
        while(l<=r) {
            let m = Math.floor((l+r)/2);
            if(targetArr[m].time <= timestamp) {
                ans = m;
                l = m+1;
            } else {
                r = m-1;
            }
        }
        return ans;
    }

    // --- Strategy & Backtest Engine (unused change) ---
    function runStrategy() {
        const d15 = rawData[TF.M15];
        const dH = rawData[TF.H];
        const dD = rawData[TF.D];
        if(d15.length < 50 || dH.length < 20 || dD.length < 20) return;
        // Calc Indicators
        const ema50_d = calcEMA(dD, 50);
        const stoch_d = calcStoch(dD);
        const ema9_h = calcEMA(dH, 9);
        const ema50_h = calcEMA(dH, 50);
        const rsi_h = calcRSI(dH);
        const ha_15 = calcHA(d15);
        const ema5_ha = calcEMA(ha_15, 5, 'close');
        const ema9_ha = calcEMA(ha_15, 9, 'close');
        const macd_15 = calcMACD(d15);

        const MAX_HOLD = 20; // keep hold as limit

        let markers = [];
        let stats = { total:0, wins:0, losses:0 };
        let signalHistory = [];
        let currStats = {};

        for(let i=50; i<d15.length; i++) {
            const time = d15[i].time;
            const idxD = getSyncIndex(dD, time);
            const idxH = getSyncIndex(dH, time);
            if(idxD === -1 || idxH === -1) continue;

            const isBullD = dD[idxD].close > ema50_d[idxD] && stoch_d.k[idxD] > stoch_d.d[idxD];
            const isBearD = dD[idxD].close < ema50_d[idxD] && stoch_d.k[idxD] < stoch_d.d[idxD];
            const isBuyH = ema9_h[idxH] > ema50_h[idxH] && rsi_h[idxH] > 60;
            const isSellH = ema9_h[idxH] < ema50_h[idxH] && rsi_h[idxH] < 40;
            const haCrossUp = ema5_ha[i-1] <= ema9_ha[i-1] && ema5_ha[i] > ema9_ha[i];
            const haCrossDown = ema5_ha[i-1] >= ema9_ha[i-1] && ema5_ha[i] < ema9_ha[i];
            const macdBull = macd_15.macd[i] > macd_15.sig[i];
            const macdBear = macd_15.macd[i] < macd_15.sig[i];

            let signal = null;
            if (isBullD && isBuyH && haCrossUp && macdBull) signal = 'BUY';
            else if (isBearD && isSellH && haCrossDown && macdBear) signal = 'SELL';

            if(i === d15.length - 1) {
                currStats = {
                    d1: isBullD ? 'BULLISH' : (isBearD ? 'BEARISH' : 'NEUTRAL'),
                    h1: isBuyH ? 'PASS (BUY)' : (isSellH ? 'PASS (SELL)' : 'WAIT'),
                    m15: haCrossUp ? 'CROSS UP' : (haCrossDown ? 'CROSS DOWN' : 'NO CROSS'),
                    sig: signal || 'WAIT'
                };
            }

            if(signal) {
                stats.total++;
                let entry = d15[i].close;
                let exitIndex = -1;
                let exitPrice = entry;
                let finalOutcome = 'OPEN';
                let pnl = 0;

                for(let j=1; j<=MAX_HOLD; j++) {
                    if(i+j >= d15.length) break;
                    const prev5 = ema5_ha[i+j-1], prev9 = ema9_ha[i+j-1];
                    const cur5 = ema5_ha[i+j], cur9 = ema9_ha[i+j];
                    if(prev5==null || prev9==null || cur5==null || cur9==null) continue;
                    if(signal === 'BUY') {
                        if(prev5 >= prev9 && cur5 < cur9) {
                            exitIndex = i+j;
                            exitPrice = d15[exitIndex].close;
                            finalOutcome = 'CLOSED';
                            break;
                        }
                    } else {
                        if(prev5 <= prev9 && cur5 > cur9) {
                            exitIndex = i+j;
                            exitPrice = d15[exitIndex].close;
                            finalOutcome = 'CLOSED';
                            break;
                        }
                    }
                }

                if(exitIndex === -1) {
                    if(i + MAX_HOLD < d15.length) {
                        exitIndex = i + MAX_HOLD;
                        exitPrice = d15[exitIndex].close;
                        finalOutcome = 'CLOSED';
                    } else {
                        exitIndex = d15.length - 1;
                        exitPrice = d15[exitIndex].close;
                        finalOutcome = (i + MAX_HOLD < d15.length) ? 'CLOSED' : 'OPEN';
                    }
                }

                if(signal === 'BUY') pnl = ((exitPrice - entry)/entry)*100;
                else pnl = ((entry - exitPrice)/entry)*100;

                let outcomeLabel = finalOutcome;
                if(finalOutcome === 'CLOSED') {
                    if(pnl > 0) { outcomeLabel = 'WIN'; stats.wins++; }
                    else if(pnl < 0) { outcomeLabel = 'LOSS'; stats.losses++; }
                    else { outcomeLabel = 'BREAKEVEN'; }
                } else { outcomeLabel = 'OPEN'; }

                signalHistory.push({ time: time, type: signal, price: entry, exitTime: exitIndex >=0 ? d15[exitIndex].time : null, exitPrice: exitPrice, outcome: outcomeLabel, pnl: pnl });

                markers.push({ time: time, position: signal==='BUY'?'belowBar':'aboveBar', color: signal==='BUY'?'#10b981':'#ef4444', shape: signal==='BUY'?'arrowUp':'arrowDown', text: signal });
            }
        }

        updateUI(currStats, stats, markers, signalHistory);
    }

    function updateUI(live, bt, markers, history) {
        $('#live-1d').text(live.d1).attr('class', 'stat-value ' + (live.d1==='BULLISH'?'text-buy':(live.d1==='BEARISH'?'text-sell':'text-wait')));
        $('#live-1h').text(live.h1).attr('class', 'stat-value ' + (live.h1.includes('BUY')?'text-buy':(live.h1.includes('SELL')?'text-sell':'text-wait')));
        $('#live-15m').text(live.m15);

        const sigEl = $('#live-signal');
        sigEl.text(live.sig).removeClass('bg-buy bg-sell');
        if(live.sig === 'BUY') sigEl.addClass('bg-buy');
        if(live.sig === 'SELL') sigEl.addClass('bg-sell');

        $('#bt-total').text(bt.total);
        $('#bt-wins').text(bt.wins);
        $('#bt-losses').text(bt.losses);
        let rate = bt.total > 0 ? ((bt.wins / (bt.wins + bt.losses)) * 100).toFixed(1) : 0;
        $('#bt-rate').text(rate + '%');

        if($('#interval').val() === 'FIFTEEN_MINUTE') {
            series.setMarkers(markers);
        } else {
            series.setMarkers([]);
        }

        const tbody = $('#signals-body');
        tbody.empty();
        const recentHistory = history.slice(-50).reverse();

        if(recentHistory.length === 0) {
            tbody.append('<tr><td colspan="7" style="text-align:center">No signals generated yet.</td></tr>');
        } else {
            recentHistory.forEach(sig => {
                const dateStr = new Date(sig.time * 1000).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' });
                const exitTimeStr = sig.exitTime ? new Date(sig.exitTime * 1000).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' }) : '-';
                const typeClass = sig.type === 'BUY' ? 'text-buy' : 'text-sell';
                const outClass = sig.outcome === 'WIN' ? 'outcome-win' : (sig.outcome === 'LOSS' ? 'outcome-loss' : (sig.outcome === 'OPEN' ? 'outcome-open' : ''));
                const pnlSign = sig.pnl > 0 ? '+' : '';
                const exitPriceDisplay = (sig.exitPrice === undefined || sig.exitPrice === null) ? '-' : sig.exitPrice.toFixed(2);
                const row = `
                    <tr>
                        <td style="color:#9ca3af">${dateStr}</td>
                        <td class="${typeClass}" style="font-weight:bold">${sig.type}</td>
                        <td>${sig.price.toFixed(2)}</td>
                        <td style="color:#9ca3af">${exitTimeStr}</td>
                        <td>${exitPriceDisplay}</td>
                        <td class="${outClass}">${sig.outcome}</td>
                        <td style="color:${sig.pnl>=0?'#34d399':'#f87171'}">${pnlSign}${sig.pnl.toFixed(2)}%</td>
                    </tr>
                `;
                tbody.append(row);
            });
        }
    }

    // --- Core Logic: data loading and realtime tick update ---
    async function loadData() {
        $('#loader').show();
        try {
            const fetchTF = async (int) => {
                const r = await fetch(`${API.HISTORY}?symbol=${activeToken}&interval=${int}`);
                const j = await r.json();
                return (j.data||[]).map(x=>({ time: Number(x.time), open:Number(x.open), high:Number(x.high), low:Number(x.low), close:Number(x.close) }));
            };
            const [dD, dH, d15] = await Promise.all([ fetchTF(TF.D), fetchTF(TF.H), fetchTF(TF.M15) ]);
            rawData[TF.D] = dD; rawData[TF.H] = dH; rawData[TF.M15] = d15;
            const view = $('#interval').val();
            if(rawData[view]) series.setData(rawData[view]);
            runStrategy();
        } catch(e) { console.error(e); }
        $('#loader').hide();
    }

    function handleTick(tick) {
        if(!tick || !tick.ltp) return;
        const ltp = Number(tick.ltp);
        $('#ltp').text(ltp.toFixed(2));
        const ts = Math.floor(Date.now()/1000);

        [TF.D, TF.H, TF.M15].forEach(tf => {
            const arr = rawData[tf];
            if(!arr.length) return;
            const sec = tf===TF.M15?900:(tf===TF.H?3600:86400);
            const bucket = Math.floor(ts/sec)*sec;
            const last = arr[arr.length-1];
            if(last.time === bucket) {
                last.close = ltp; last.high = Math.max(last.high, ltp); last.low = Math.min(last.low, ltp);
                if($('#interval').val() === tf) series.update(last);
            } else if(bucket > last.time) {
                const newC = { time:bucket, open:last.close, high:ltp, low:ltp, close:ltp };
                arr.push(newC);
                if($('#interval').val() === tf) series.update(newC);
            }
        });
        runStrategy();
    }

    // --- Improved Search / Dropdown Implementation ---
    function debounce(fn, wait=250) {
        let t;
        return function(...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function escapeHtml(str) { return String(str).replace(/[&<>\"']/g, s => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[s]); }

    function highlightMatch(text, q) {
        if(!q) return escapeHtml(text);
        const re = new RegExp('('+q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+')','ig');
        return escapeHtml(text).replace(re, '<span class="suggestion-highlight">$1</span>');
    }

    async function searchSymbols(query, limit=10) {
        if(!query) return [];
        query = query.trim();
        if(searchCache.has(query)) return searchCache.get(query);

        // Cancel any pending request controller
        if(pendingRequest && pendingRequest.abort) pendingRequest.abort();
        const controller = new AbortController();
        pendingRequest = controller;

        try {
            const r = await fetch(`${API.SYMBOLS}?q=${encodeURIComponent(query)}&limit=${limit}`, { signal: controller.signal });
            const j = await r.json();
            const results = (j.data||[]).map(x => ({ token: x.token || x.token_id || x.Token || x.tokenId, symbol: x.symbol || x.symbolName || x.display || x.symbol_name || x.sy || x.symbol || '', name: x.name || x.fullName || '' }));
            searchCache.set(query, results);
            return results;
        } catch(err) {
            if(err.name === 'AbortError') return [];
            console.error('searchSymbols error', err);
            return [];
        } finally {
            pendingRequest = null;
        }
    }

    function renderSuggestions(list, q) {
        dropdown.empty();
        currentSuggestions = list || [];
        suggestionIndex = -1;
        if(!list || list.length === 0) {
            dropdown.append(`<div class="no-results">No results</div>`).show().attr('aria-hidden','false');
            return;
        }
        for(let i=0;i<list.length;i++) {
            const item = list[i];
            const el = $(
                `<div class="suggestion-item" data-idx="${i}" data-token="${item.token}">
                    <div class="suggestion-left">
                        <div>
                            <div class="suggestion-symbol">${highlightMatch(item.symbol || item.token, q)}</div>
                            ${item.name?`<div class="suggestion-name">${highlightMatch(item.name, q)}</div>`:''}
                        </div>
                    </div>
                    <div class="suggestion-token">${escapeHtml(String(item.token||''))}</div>
                </div>`
            );
            el.on('mousedown', function(e){
                // mousedown -> prevent blur before click
                e.preventDefault();
            });
            el.on('click', function(){ selectSuggestion(i); });
            dropdown.append(el);
        }
        dropdown.show().attr('aria-hidden','false');
    }

    function selectSuggestion(index) {
        const s = currentSuggestions[index];
        if(!s) return;
        activeToken = s.token || activeToken;
        input.val(s.symbol || s.token || '');
        hideSuggestions();
        loadData();
    }

    function hideSuggestions() { dropdown.hide().attr('aria-hidden','true'); currentSuggestions=[]; suggestionIndex=-1; }

    const debouncedSearch = debounce(async function(){
        const q = input.val();
        if(!q || q.trim().length < 1) { hideSuggestions(); return; }
        dropdown.html('<div class="no-results"><div style="display:flex;align-items:center;gap:8px"><div class="suggestion-spinner"></div>Searching...</div></div>').show().attr('aria-hidden','false');
        const list = await searchSymbols(q, 12);
        renderSuggestions(list, q);
    }, 220);

    // keyboard navigation
    input.on('keydown', function(e){
        if(dropdown.is(':hidden')) return;
        if(e.key === 'ArrowDown') { e.preventDefault(); suggestionIndex = Math.min(suggestionIndex+1, currentSuggestions.length-1); updateActive(); }
        else if(e.key === 'ArrowUp') { e.preventDefault(); suggestionIndex = Math.max(suggestionIndex-1, 0); updateActive(); }
        else if(e.key === 'Enter') { e.preventDefault(); if(suggestionIndex === -1 && currentSuggestions.length === 1) selectSuggestion(0); else selectSuggestion(suggestionIndex); }
        else if(e.key === 'Escape') { hideSuggestions(); }
    });

    function updateActive() {
        dropdown.children().removeClass('active');
        if(suggestionIndex >=0) {
            const child = dropdown.children(`[data-idx="${suggestionIndex}"]`);
            child.addClass('active');
            // ensure visible
            const el = child.get(0);
            if(el) el.scrollIntoView({ block: 'nearest' });
            // populate value preview but do not commit
            const s = currentSuggestions[suggestionIndex];
            if(s) input.val(s.symbol || s.token || input.val());
        }
    }

    // input events
    input.on('input', function(){ debouncedSearch(); });

    // on focus: if input has text, show cached results or trigger search; if empty, fetch top symbols as quick pick
    input.on('focus', async function(){
        const q = input.val();
        if(q && q.trim().length) { debouncedSearch(); return; }
        // quick fetch popular symbols (cache key "__popular__")
        if(searchCache.has('__popular__')) { renderSuggestions(searchCache.get('__popular__'), ''); return; }
        dropdown.html('<div class="no-results"><div style="display:flex;align-items:center;gap:8px"><div class="suggestion-spinner"></div>Loading...</div></div>').show().attr('aria-hidden','false');
        try {
            const r = await fetch(`${API.SYMBOLS}?q=&limit=12`);
            const j = await r.json();
            const results = (j.data||[]).map(x => ({ token: x.token || x.token_id || x.Token || x.tokenId, symbol: x.symbol || x.symbolName || x.display || x.symbol_name || x.sy || x.symbol || '', name: x.name || x.fullName || '' }));
            searchCache.set('__popular__', results);
            renderSuggestions(results, '');
        } catch(err) { console.error(err); dropdown.html('<div class="no-results">Failed to load</div>'); }
    });

    // hide on outside click
    $(document).on('click', function(e){ if(!$(e.target).closest('.search-wrapper').length) hideSuggestions(); });

    // select on paste Enter
    input.on('blur', function(){ setTimeout(()=>{ if(document.activeElement && $(document.activeElement).closest('.suggestions').length) return; /* clicked suggestion */ hideSuggestions(); }, 150); });

    // --- End Improved Search ---

    // --- Inputs and other handlers kept unchanged except symbolResults removal ---
    $('#interval').on('change', function(){
        const v = $(this).val();
        if(rawData[v]) series.setData(rawData[v]);
        runStrategy();
    });

    $('#login').on('click', () => {
        $.post(API.LOGIN, (r) => {
            if(r.jwt) {
                if(ws) ws.close();
                ws = new WebSocket(API.WS);
                ws.onopen = () => ws.send(JSON.stringify({ action:'subscribe', tokens:[activeToken], jwt:r.jwt, feed:r.feed }));
                ws.onmessage = (e) => { try { handleTick(JSON.parse(e.data).tick); } catch(x){} };
            }
            loadData();
        });
    });

    loadData();

    // --- Scanner code unchanged (copied) ---
    function showScanPanel(show = true) {
        if(show) { $('#scan-results').show(); $('#scanBtn').text('Rescan'); } else { $('#scan-results').hide(); $('#scanBtn').text('Scan Stocks'); }
    }

    async function sleep(ms) { return new Promise(res=>setTimeout(res, ms)); }

    async function asyncPool(poolLimit, array, iteratorFn) {
        const ret = [];
        const executing = [];
        for (const item of array) {
            const p = Promise.resolve().then(() => iteratorFn(item));
            ret.push(p);
            if (poolLimit <= array.length) {
                const e = p.then(() => executing.splice(executing.indexOf(e), 1));
                executing.push(e);
                if (executing.length >= poolLimit) { await Promise.race(executing); }
            }
            if(!scanning) break;
        }
        return Promise.all(ret);
    }

    async function detectSignalForToken(token, symbolText) {
        try {
            const [dD, dH, d15] = await Promise.all([
                fetch(`${API.HISTORY}?symbol=${token}&interval=${TF.D}`).then(r=>r.json()).then(j=> (j.data||[]).map(x=>({ time: Number(x.time), open:Number(x.open), high:Number(x.high), low:Number(x.low), close:Number(x.close) }))),
                fetch(`${API.HISTORY}?symbol=${token}&interval=${TF.H}`).then(r=>r.json()).then(j=> (j.data||[]).map(x=>({ time: Number(x.time), open:Number(x.open), high:Number(x.high), low:Number(x.low), close:Number(x.close) }))),
                fetch(`${API.HISTORY}?symbol=${token}&interval=${TF.M15}`).then(r=>r.json()).then(j=> (j.data||[]).map(x=>({ time: Number(x.time), open:Number(x.open), high:Number(x.high), low:Number(x.low), close:Number(x.close) })))
            ]);

            if(!d15.length || !dH.length || !dD.length) return null;

            const ema50_d = calcEMA(dD, 50);
            const stoch_d = calcStoch(dD);
            const ema9_h = calcEMA(dH, 9);
            const ema50_h = calcEMA(dH, 50);
            const rsi_h = calcRSI(dH);
            const ha_15 = calcHA(d15);
            const ema5_ha = calcEMA(ha_15, 5, 'close');
            const ema9_ha = calcEMA(ha_15, 9, 'close');
            const macd_15 = calcMACD(d15);

            const i15 = d15.length - 1;
            const time = d15[i15].time;
            const idxD = getSyncIndex(dD, time);
            const idxH = getSyncIndex(dH, time);
            if(idxD === -1 || idxH === -1) return null;

            const isBullD = dD[idxD].close > ema50_d[idxD] && stoch_d.k[idxD] > stoch_d.d[idxD];
            const isBearD = dD[idxD].close < ema50_d[idxD] && stoch_d.k[idxD] < stoch_d.d[idxD];
            const isBuyH = ema9_h[idxH] > ema50_h[idxH] && rsi_h[idxH] > 60;
            const isSellH = ema9_h[idxH] < ema50_h[idxH] && rsi_h[idxH] < 40;
            const haCrossUp = ema5_ha[i15-1] <= ema9_ha[i15-1] && ema5_ha[i15] > ema9_ha[i15];
            const haCrossDown = ema5_ha[i15-1] >= ema9_ha[i15-1] && ema5_ha[i15] < ema9_ha[i15];
            const macdBull = macd_15.macd[i15] > macd_15.sig[i15];
            const macdBear = macd_15.macd[i15] < macd_15.sig[i15];

            let signal = null;
            if (isBullD && isBuyH && haCrossUp && macdBull) signal = 'BUY'; else if (isBearD && isSellH && haCrossDown && macdBear) signal = 'SELL';

            return { symbol: symbolText || token, token, signal: signal || 'WAIT', d1: isBullD ? 'BULL' : (isBearD ? 'BEAR' : 'NEUT'), h1: isBuyH ? 'BUY' : (isSellH ? 'SELL' : 'WAIT'), m15: haCrossUp ? 'CROSS UP' : (haCrossDown ? 'CROSS DOWN' : 'NO'), time };
        } catch(err) { return null; }
    }

    async function runScanner() {
        try {
            scanning = true;
            showScanPanel(true);
            $('#scan-body').empty();
            $('#scan-count').text('0');
            $('#scan-status').text('Fetching symbol list...');
            $('#scan-progress-bar').css('width','0%');
            $('#scan-results').show();
            $('#stopScanBtn').show();

            const limitVal = 500;
            const res = await fetch(`${API.SYMBOLS}?q=&limit=${limitVal}`);
            const list = await res.json();
            const symbols = (list.data||[]);

            if(!symbols.length) {
                $('#scan-status').text('No symbols returned.');
                scanning = false; $('#stopScanBtn').hide(); return;
            }

            const total = symbols.length; let processed = 0; let results = [];
            $('#scan-status').text(`Scanning ${total} symbols...`);
            const poolLimit = 6;

            await asyncPool(poolLimit, symbols, async (s) => {
                if(!scanning) return;
                const token = s.token || s.token_id || s.Token || s.tokenId;
                const symbolText = s.symbol || s.symbolName || s.display || s.symbol_name || s.sy || s.symbol || '';
                if(!token) { processed++; $('#scan-progress-bar').css('width', `${Math.round((processed/total)*100)}%`); return; }
                const meta = await detectSignalForToken(token, symbolText);
                processed++;
                $('#scan-progress-bar').css('width', `${Math.round((processed/total)*100)}%`);
                $('#scan-status').text(`Scanning ${processed}/${total}`);
                if(meta && meta.signal && meta.signal !== 'WAIT') {
                    results.push(meta);
                    const tr = $(`
                        <tr class="clickable-row" data-token="${meta.token}">
                            <td style="color:#9ca3af">${meta.symbol}</td>
                            <td>${meta.token}</td>
                            <td class="${meta.signal==='BUY'?'text-buy':'text-sell'}" style="font-weight:bold">${meta.signal}</td>
                            <td>${meta.d1}</td>
                            <td>${meta.h1}</td>
                            <td>${meta.m15}</td>
                        </tr>
                    `);
                    tr.on('click', function(){ const token = $(this).data('token'); activeToken = token; loadData(); $(this).css('background','#0f1724'); });
                    $('#scan-body').append(tr);
                    $('#scan-count').text(results.length);
                }
            });

            $('#scan-status').text(`Scan complete. Found ${$('#scan-count').text()} signals.`);
        } catch(e) {
            console.error(e);
            $('#scan-status').text('Scan failed. See console for details.');
        } finally {
            scanning = false; $('#stopScanBtn').hide(); $('#scan-progress-bar').css('width','100%');
        }
    }

    $('#stopScanBtn').on('click', () => { scanning = false; $('#scan-status').text('Stopping...'); $('#stopScanBtn').hide(); });
    $('#scanBtn').on('click', async () => { if(scanning) { scanning = false; $('#scan-status').text('Stopping...'); return; } await runScanner(); });

})();
</script>


</body>

</html>
