# Edge Storage Fallback

A simple strategy for making storage at the edge **resilient and reliable**.  
If one layer fails, the system falls back gracefully to the next.

---

##  Why Use Storage Fallback?
- Ensure **high availability** across networks and regions.  
- Reduce **latency** by serving data from the nearest location.  
- Provide **graceful degradation** when caches or KV stores are unavailable.  

---

##  Fallback Order

**Reads**
1. **Edge Cache** (CDN / Cache API)  
2. **Edge KV / Config Store** (Cloudflare KV, Vercel Edge Config, etc.)  
3. **Origin API / Database** (primary source of truth)  
4. **Client Cache** (IndexedDB or localStorage as a last resort)

**Writes**
1. **Origin API / Database** → main source of truth  
2. **Edge KV / Config Store** → async backfill or write-through  
3. **Edge Cache** → pre-warm or update  

---

## ⚙️ Cache Policy

- **Key format:** `namespace:version:resourceId`  
- **TTL:** 60–300 seconds for hot data  
- **Stale-While-Revalidate:** 30–300 seconds to smooth refresh  
- **Invalidate on write:** bump version or purge by key  
- **Keep objects small:** ≤1 MB when possible  

---

## 🧩 Example: Read Path (Edge Runtime)

```ts
export default async function handleRequest(req: Request, env: Env) {
  const key = `product:v3:123`;

  // 1. Try Edge Cache
  const cached = await caches.default.match(req);
  if (cached) return cached;

  // 2. Try Edge KV
  const kvData = await env.KV.get(key);
  if (kvData) {
    const res = new Response(kvData, { headers: { "Cache-Control": "max-age=120, stale-while-revalidate=60" } });
    event.waitUntil(caches.default.put(req, res.clone()));
    return res;
  }

  // 3. Fallback to Origin
  const originRes = await fetch(`${env.ORIGIN_URL}/api/items/123`);
  if (originRes.ok) {
    const text = await originRes.text();
    const res = new Response(text, { headers: { "Cache-Control": "max-age=120, stale-while-revalidate=60" } });
    event.waitUntil(env.KV.put(key, text, { expirationTtl: 300 }));
    event.waitUntil(caches.default.put(req, res.clone()));
    return res;
  }

  // 4. Last resort
  return new Response(JSON.stringify({ error: "Service unavailable", hint: "use client cache" }), { status: 503 });
}
