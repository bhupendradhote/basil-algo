# Angel One WebSocket Bridge

## Setup
1. `cd server`
2. `npm install`
3. Create `.env` (copy template). Provide ANGEL_API_KEY and optionally LARAVEL_API.
4. `npm start` (or `node server.js`)

## How frontend should subscribe
Open WebSocket to `ws://localhost:3001/` and send JSON:
```json
{
  "action": "subscribe",
  "tokens": ["2885"],
  "jwt": "<optional JWT if available>",
  "feed": "<optional feed token>"
}






---

# 5) Minimal frontend subscribe example (if you want it again)

Add/replace your login websocket section (in your existing Laravel frontend) with this snippet — it will ask the bridge to fetch tokens from Laravel if you don't send a JWT.

```js
// after a successful Laravel login (or even without it), open bridge WS:
const ws = new WebSocket('ws://localhost:3001/');

ws.onopen = () => {
  // Option A: send jwt/feed you got from Laravel /api/login
  // ws.send(JSON.stringify({ action: 'subscribe', jwt: res.jwt, feed: res.feed, tokens: [res.symbol] }));

  // Option B: let bridge fetch tokens from your Laravel API (no jwt supplied)
  ws.send(JSON.stringify({ action: 'subscribe', tokens: [selectedToken] }));
};

ws.onmessage = (e) => {
  const msg = JSON.parse(e.data);
  if (msg.type === 'tick' && msg.tick) {
    console.log('tick', msg.tick);
    // update LTP UI: try common fields
    const t = msg.tick;
    const ltp = t.ltp || t.lastPrice || t.last_traded_price || t[0] || null;
    if (ltp !== null && !isNaN(Number(ltp))) {
      document.getElementById('ltp').innerText = Number(ltp).toFixed(2);
    }
  } else if (msg.status || msg.error) {
    console.log('bridge status/error', msg);
  }
};
