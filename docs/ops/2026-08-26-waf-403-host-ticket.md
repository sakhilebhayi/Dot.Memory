# Support ticket — intermittent 403 on an authenticated endpoint

**Server:** s11993.lon1.stableserver.net (198.38.92.233) · LiteSpeed
**Account domains affected:** mines.infodot.co.za (observed), memory.infodot.co.za and dopemine.infodot.co.za (same account, same edge)

---

## Subject

Intermittent HTTP 403 from the server edge on an authenticated monitoring endpoint — please identify the rule and exempt the path

## What is happening

An automated health check requests one URL on a fixed schedule:

```
GET https://mines.infodot.co.za/guardian/health
Authorization: Bearer <token>
Accept: application/json
```

Roughly one request in fifteen is answered with **HTTP 403** by something in front of the application. The application is up and serving normally throughout — other traffic in the same window succeeds.

**Measured: 4 of 61 requests blocked (6.6%)** between 2026-08-25 20:00 and 2026-08-26 15:00 UTC.

Exact timestamps of the blocked requests (UTC):

| # | Timestamp |
| --- | --- |
| 1 | 2026-08-25 21:18:33 |
| 2 | 2026-08-25 22:15:34 |
| 3 | 2026-08-26 00:19:14 |
| 4 | 2026-08-26 14:00:49 |

## Why we are confident the 403 is not from our application

The application has **no code path that returns 403** on this route. Its middleware returns:

- **401** when the bearer token is missing or wrong
- **503** when the endpoint is not configured
- **200** on success

Verified live while preparing this ticket: no `Authorization` header → 401, deliberately wrong token → 401. A 403 therefore cannot have originated in our code, and the request must have been refused before it reached PHP.

## What we have already ruled out

- **Our own deploys.** Laravel maintenance mode returns 503, not 403, and one of the four blocks (14:00:49) occurred 51 minutes before the nearest deploy.
- **Authentication.** That path returns 401, and the token is valid — the same token succeeds on surrounding requests.
- **User-agent filtering.** Not reproducible: we probed the endpoint with five different user agents, including an empty one, and all returned 200.
- **Client-side failure.** The monitoring job itself succeeded on all 61 runs; it recorded the 403 response it received.

## Source of the requests

The checks originate from **GitHub Actions hosted runners**, which use Microsoft Azure address space and **rotate IPs on every run**. GitHub publishes the ranges at `https://api.github.com/meta`, but the `actions` list currently contains **7,280 CIDR ranges** — so a source-IP allowlist is not practical for either of us.

We suspect IP-reputation or rate-based filtering (Imunify360, mod_security, or CSF) reacting to shared cloud address space rather than to anything in our requests.

## What we are asking for

1. **The WAF / firewall log entries for the four timestamps above**, so we can see which product and which rule ID actually fired. This is the important one — without it we are both guessing.

2. **Once identified, exempt the path `/guardian/health` from that rule** for this account. It is a reasonable exemption:
   - it is bearer-authenticated by the application and returns 401 without a valid token
   - it accepts `GET` only, takes no user input, and touches no forms or query parameters
   - it returns a small fixed JSON health document and nothing else

3. If a path exemption is not possible, please advise what **is** workable given that the source IPs rotate across thousands of Azure ranges.

## Impact

This monitors three production applications on this account. Every false 403 previously raised a critical "platform unreachable" alert. We have since changed our side to tolerate isolated failures, so this is **not currently urgent** — but it does mean genuine outage detection is now delayed by design, and we would rather remove the cause than keep compensating for it.

Happy to supply any further detail — request IDs, additional timestamps, or a live reproduction window.
