import "dotenv/config";          // 🔥 auto-load .env
import WebSocket from "ws";
import fetch from "node-fetch";
import SmartApiPkg from "smartapi-javascript";

/* -------------------------------------------------- */
/* DEBUG — PROVE ENV */
/* -------------------------------------------------- */
console.log("CWD =", process.cwd());
console.log("LARAVEL_API =", process.env.LARAVEL_API);

if (!process.env.LARAVEL_API) {
  console.error("❌ ENV STILL NOT LOADED");
  process.exit(1);
}

/* -------------------------------------------------- */
/* CONSTANTS */
/* -------------------------------------------------- */
const LARAVEL_API = process.env.LARAVEL_API;
const WS_PORT = Number(process.env.WS_PORT || 3001);

/* -------------------------------------------------- */
/* WS SERVER */
/* -------------------------------------------------- */
const wss = new WebSocket.Server({ port: WS_PORT });
console.log(`🚀 WS Bridge running on ws://localhost:${WS_PORT}`);

wss.on("connection", () => {
  console.log("🟢 Browser connected");
  testLaravel();
});

/* -------------------------------------------------- */
/* TEST LARAVEL ENDPOINT */
/* -------------------------------------------------- */
async function testLaravel() {
  try {
    const res = await fetch(LARAVEL_API);
    console.log("Laravel HTTP status:", res.status);
    const txt = await res.text();
    console.log("Laravel response:", txt);
  } catch (e) {
    console.error("Laravel fetch failed:", e.message);
  }
}
