// server.js
require('dotenv').config();
const express = require('express');
const http = require('http');
const WebSocket = require('ws');
const axios = require('axios');
const { WebSocketV2 } = require('smartapi-javascript');

const app = express();
const server = http.createServer(app);

const WS_PORT = process.env.WS_PORT ? Number(process.env.WS_PORT) : 3001;
const LARAVEL_API = process.env.LARAVEL_API || null;
const ANGEL_API_KEY = process.env.ANGEL_API_KEY || '';
const ANGEL_CLIENT_CODE = process.env.ANGEL_CLIENT_CODE || '';
// Default feed usually 'json' for V2, but let's allow env override
const DEFAULT_FEED = process.env.ANGEL_FEED || 'json'; 
const DEFAULT_JWT = process.env.ANGEL_JWT || '';

if (!ANGEL_API_KEY) {
  console.error('ERROR: ANGEL_API_KEY is not set in .env');
  process.exit(1);
}

app.get('/health', (_req, res) => res.json({ ok: true }));

const wss = new WebSocket.Server({ server, path: '/' });

// --- Helpers ---

async function fetchTokensFromLaravel() {
  if (!LARAVEL_API) return null;
  try {
    const resp = await axios.get(LARAVEL_API, { timeout: 8000 });
    if (resp && resp.data) return resp.data;
  } catch (err) {
    console.warn('[bridge] fetchTokensFromLaravel failed:', err.message || err);
  }
  return null;
}

function normalizeTokens(inputTokens) {
  if (!Array.isArray(inputTokens)) inputTokens = [inputTokens];
  return inputTokens
    .filter(Boolean)
    .map(t => {
      const s = String(t).trim();
      if (s.startsWith('nse_cm|')) return s.split('|')[1];
      return s;
    });
}

function computeLtpFromTick(tick) {
  if (!tick) return null;
  
  if (typeof tick === 'object') {
    // 1. Check Payload (Standard V2)
    if (tick.payload) {
      const p = tick.payload;
      // 'last_traded_price' is usually integer Paisa (e.g. 235000 -> 2350.00)
      if (p.last_traded_price !== undefined) return Number(p.last_traded_price) / 100;
      if (p.ltp !== undefined) return Number(p.ltp);
    }
    // 2. Check Direct Keys
    if (tick.last_traded_price !== undefined) return Number(tick.last_traded_price) / 100;
    if (tick.ltp !== undefined) return Number(tick.ltp);
  }

  // Legacy Array
  if (Array.isArray(tick)) {
    const tryIdx = [1, 4, 2];
    for (const i of tryIdx) {
      if (tick[i] !== undefined && !isNaN(Number(tick[i]))) return Number(tick[i]);
    }
  }
  return null;
}

// --- WebSocket Server ---

wss.on('connection', (clientWs, req) => {
  console.log('[bridge] Browser connected');
  clientWs.isAlive = true;
  clientWs.on('pong', () => clientWs.isAlive = true);

  // Store the Angel connection FOR THIS CLIENT
  let smartWs = null; 
  let subscribedTokens = [];

  // Function to establish OR reuse connection
  async function connectAndSubscribe(jwt, feed, client_code, tokens) {
    const cleanTokens = normalizeTokens(tokens || []);
    subscribedTokens = cleanTokens;

    // 1. Get Credentials if missing
    if (!jwt) {
        if (DEFAULT_JWT) jwt = DEFAULT_JWT;
        else {
            const got = await fetchTokensFromLaravel();
            if (got && got.jwt) jwt = got.jwt;
            if (got && got.feed) feed = feed || got.feed;
            if (got && got.client_code) client_code = client_code || got.client_code;
        }
    }

    if (!jwt) {
        clientWs.send(JSON.stringify({ error: 'missing_jwt', message: 'No JWT found' }));
        return;
    }

    if (smartWs && smartWs.ws && smartWs.ws.readyState !== 1) { // 1 = OPEN
        console.log('[bridge] Existing socket closed, creating new...');
        smartWs = null;
    }

    // 3. Create Connection ONLY if it doesn't exist
    if (!smartWs) {
        console.log('[bridge] Creating NEW SmartAPI connection...');
        try {
            smartWs = new WebSocketV2({
                jwttoken: jwt,
                apikey: ANGEL_API_KEY,
                clientcode: client_code || ANGEL_CLIENT_CODE || '',
                feedtype: feed || DEFAULT_FEED || 'json'
            });

            await smartWs.connect();
            console.log('[bridge] Connected to SmartAPI');

            smartWs.on('tick', (tick) => {
                // Heartbeat check
                if (tick === 'pong' || (typeof tick === 'string' && tick.includes('pong'))) return;

                try {
                    const ltp = computeLtpFromTick(tick);
                    
                    let token = null;
                    if (tick.payload && tick.payload.token) token = tick.payload.token.replace('nse_cm|','');
                    else if (tick.token) token = tick.token;

                    // DEBUG LOG: Print incoming tick to server console
                    if (ltp) {
                         console.log(`[bridge] TICK RECV: Token=${token} LTP=${ltp}`);
                    }

                    // Send to Client
                    if (ltp !== null && clientWs.readyState === WebSocket.OPEN) {
                        const payload = { 
                            type: 'tick', 
                            tick: { ltp, token, raw: tick },
                            ts: Date.now() 
                        };
                        clientWs.send(JSON.stringify(payload));
                    }
                } catch (e) { 
                    console.error('[bridge] Tick Parse Error:', e); 
                }
            });

            smartWs.on('close', () => console.warn('[bridge] SmartAPI Disconnected'));
            smartWs.on('error', (e) => console.error('[bridge] SmartAPI Error', e));

        } catch (err) {
            console.error('[bridge] Connection Failed:', err);
            clientWs.send(JSON.stringify({ error: 'smartapi_connect_failed' }));
            return;
        }
    }

    if (smartWs) {
        const req = {
            correlationID: 'sub_' + Date.now(),
            action: 1, // Subscribe
            mode: 1,   // LTP Mode
            exchangeType: 1, // NSE CM (Adjust if you use Indices/BSE)
            tokens: cleanTokens
        };
        try {
            smartWs.fetchData(req);
            clientWs.send(JSON.stringify({ status: 'subscribed', tokens: cleanTokens }));
            console.log('[bridge] Sent Subscribe for:', cleanTokens);
        } catch (e) {
            console.error('[bridge] Subscribe Request Failed:', e);
        }
    }
  }

  clientWs.on('message', async (raw) => {
    let msg;
    try { msg = JSON.parse(raw.toString()); } catch (e) { return; }

    const action = (msg.action || '').toLowerCase();

    if (action === 'subscribe') {
      const tokens = msg.tokens || [];
      await connectAndSubscribe(msg.jwt, msg.feed, msg.client_code, tokens);
    } 
  });

  clientWs.on('close', () => {
    console.log('[bridge] Browser Client Disconnected');
    if (smartWs) { 
        try { smartWs.close(); } catch(e){} 
        smartWs = null;
    }
  });
});

// Heartbeat for Browser Clients
setInterval(() => {
  wss.clients.forEach(ws => {
    if (ws.isAlive === false) return ws.terminate();
    ws.isAlive = false;
    ws.ping();
  });
}, 30000);

server.listen(WS_PORT, () => console.log(`[bridge] listening on ws://localhost:${WS_PORT}/`));

