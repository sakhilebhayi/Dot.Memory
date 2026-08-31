# Follow-up: WAF 403 retest results (ticket re: mines.infodot.co.za /guardian/health)

Paste-ready reply to StableServer support (Jeremy E., Customer Care – Level 1).
Original ticket: `2026-08-26-waf-403-host-ticket.md`.

---

Hi Jeremy,

Thank you for disabling ModSecurity and Monarx Protect so we could retest. Results below.

**Retest performed.** On 27 August at approximately 20:20 SAST (18:20 UTC) we sent 180 authenticated GET requests to `https://mines.infodot.co.za/guardian/health` from 45 distinct GitHub Actions runner IP addresses — the same source population that was being blocked. Every request returned HTTP 200. Before your change, the block rate was about 6.6% of monitoring runs, so this is a substantial improvement and confirms the blocking originates in the protection layer rather than the application.

**However, one 403 occurred after your reply.** Please correlate this with your server-side logs:

- **2026-08-27 15:05:19 SAST (13:05:19 UTC)** — `GET /guardian/health` on `mines.infodot.co.za`, HTTP 403, source: a GitHub Actions runner IP.

Your reply arrived at 01:37 SAST on 27 August, so this block happened roughly 13.5 hours after the protections were reported disabled. That suggests either the change had not fully taken effect at that time, or the 403 is produced by a different layer (for example Imunify360 or a firewall rule at server level) that the per-account disable does not cover. The log entry for that timestamp should settle which.

**What we would like as the permanent outcome.** We do not want ModSecurity or Monarx left disabled — please re-enable both once you have what you need from the logs. What we are asking for instead:

1. From the logs (this timestamp and/or the four in the original ticket), identify **which product and rule** is firing.
2. Re-enable the protections with a **rule or path exemption for `GET /guardian/health` on mines.infodot.co.za only**. This endpoint is bearer-token authenticated, read-only, takes no user input, and returns a fixed JSON document — it is a monitoring probe, not user traffic.

We understand IP whitelisting is not possible on shared hosting, and we are not asking for it — a path-scoped exemption avoids that entirely.

Could you also confirm which subdomain(s) the temporary disable was applied to? The monitored endpoint is on `mines.infodot.co.za` specifically.

Kind regards
