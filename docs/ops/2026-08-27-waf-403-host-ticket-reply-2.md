# Second follow-up: WAF 403 ticket WRQ-710-05569 (mines.infodot.co.za /guardian/health)

Paste-ready reply to StableServer support (Syahrul A., Customer Care – Level 1).
Prior context: original ticket `2026-08-26-waf-403-host-ticket.md`; Jeremy E.'s reply
(01:37 SAST, 27 Aug) disabling ModSecurity + Monarx; our retest follow-up
`2026-08-27-waf-403-host-ticket-followup.md`.

---

Hi Syahrul,

Thank you for looking at this — but the GeoPeeker test doesn't address the reported issue, so I'd like to re-anchor the ticket before it drifts.

**Why the GeoPeeker result is not relevant here.** GeoPeeker fetched the public homepage from a handful of geographic vantage points. The reported problem is different on all three axes: it is (1) intermittent, (2) specific to the bearer-token-authenticated path `GET /guardian/health` on `mines.infodot.co.za`, and (3) specific to one source population — GitHub Actions runner IP addresses, which GeoPeeker does not sample. General site accessibility was never in question; the site being reachable worldwide neither confirms nor rules out anything in this ticket.

**The fresh test you asked for, from the correct source population.** Tonight at approximately 23:15 SAST (27 August) we sent 180 authenticated requests to `/guardian/health` from 45 distinct GitHub Actions runner IPs: **all returned HTTP 200.** In addition, our monitor has been polling the endpoint every 5 minutes since ~21:30 SAST — roughly 30 consecutive polls, zero 403s. The last observed 403 remains **2026-08-27 15:05:19 SAST (13:05:19 UTC)**.

So with the protections disabled, blocking has stopped. That was already established in our previous reply — and it is exactly why the three outstanding items from that reply matter more than another accessibility test:

1. **The server-side log entry for 2026-08-27 15:05:19 SAST** on `mines.infodot.co.za` (`GET /guardian/health`, HTTP 403, GitHub Actions runner source). That block occurred roughly 13.5 hours *after* the protections were reported disabled, so either the change had not fully applied, or a layer the per-account toggle does not cover (e.g. Imunify360 / a server-level firewall rule) produced it. The log line identifies which product and rule — the piece of information this ticket has been requesting since it was opened.

2. **Which subdomain(s) the temporary disable was applied to.** The monitored endpoint is on `mines.infodot.co.za` specifically.

3. **Most importantly: a timeline for re-enabling ModSecurity and Monarx Protect with a path exemption.** As far as we know, both have now been disabled on a production host for more than 24 hours. We agreed to that as a short test window, not as a steady state. Please re-enable both, with a rule or path exemption for `GET /guardian/health` on `mines.infodot.co.za` only — the endpoint is bearer-token authenticated, read-only, accepts no user input, and returns a fixed JSON document. We are not asking for IP whitelisting; a path-scoped exemption avoids that entirely.

If the exemption cannot be configured on shared hosting, please say so explicitly and we will plan around it — but please do not leave the protections off while the ticket idles.

Kind regards
