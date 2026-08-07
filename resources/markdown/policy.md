_Last updated: 7 August 2026_

This Privacy Policy explains how **BluePin Inc** ("BluePin", "we", "us", "our"), the company responsible for Dot.Memory, collects, uses, stores, and shares personal information when you use Dot.Memory and the wider Dot Ecosystem it connects to. It is written to align with South Africa's **Protection of Personal Information Act 4 of 2013 ("POPIA")**.

Dot.Memory is infrastructure, not a typical application — it's the persistence substrate the rest of the Dot Ecosystem stores knowledge in. That shapes this Policy differently from most of our other platforms.

## 1. Who we are

BluePin Inc is the responsible party for the personal information described in this Policy. Our Information Officer can be reached at privacy@infodot.co.za for any question, request, or concern about your personal information.

## 2. Two kinds of personal information Dot.Memory handles

- **Your account information**, if you sign in to Dot.Memory to monitor storage tiers, indexes, or durability — here, BluePin is the **responsible party**, and this Policy governs how we handle it.
- **Content stored by other Dot Ecosystem platforms.** Dot.Memory's core design principle is "store without reading": it holds the knowledge-graph data, vector indexes, and audit trails that other platforms write to it, but it doesn't model or read the content of what's stored — that content belongs to, and is the responsibility of, whichever platform published it. If your personal information appears in something stored on Dot.Memory, the platform that put it there is the responsible party for it, and BluePin acts only as an **operator**, storing it on that platform's instructions. If you have a question about specific content, contact the platform that generated it, not Dot.Memory directly.

## 3. What we collect (account holders)

**Account information** — your name, email address, and password (stored as a salted hash, never in plain text), via your Dot Ecosystem account.

**Team information** — the team you belong to, used to control access to the monitoring dashboard.

**Technical information** — IP address, browser and device information, and session activity, collected automatically for security and to keep you signed in.

## 4. Store without reading

Dot.Memory the platform publishes only its own operational telemetry — latency, durability, integrity statistics — never the content it stores. This is an architectural separation, not just a policy: our own domain models (storage tiers, indexes, retrieval classes, retrieval observations, durability outcomes) have no field for the content itself, and no way to associate it with an individual, a team, or a tenant. We can't read what other platforms store, and this Policy doesn't cover it — see §2.

## 5. Retention and erasure

We don't delete stored content outright; content that's no longer actively referenced loses "salience" over time — it moves from Hot to Warm to Cold storage rather than being erased, so the platform that published it can still retrieve it if needed, along with a full history of changes. Where South African or other applicable law requires erasure of specific personal information within stored content, our standing design intent is cryptographic shredding — destroying the encryption key rather than the record, which makes the content permanently unrecoverable without deleting the audit trail structure around it. As of this Policy's date, this mechanism is design intent, not yet implemented; requests for erasure of content stored by another platform should go to that platform, which can escalate to us.

## 6. Why we process your information

We process personal information to:

- create and maintain your account, and authenticate you when you sign in;
- let you sign in once and move between connected Dot Ecosystem platforms without re-entering your credentials;
- let you monitor storage tiers, index health, and durability outcomes for the platforms your team is responsible for; and
- keep Dot.Memory secure and prevent unauthorised access.

## 7. Ecosystem single sign-on

When you use another Dot Ecosystem platform to sign in to Dot.Memory (or vice versa), a short-lived, single-use authentication token confirms who you are without exposing your password to the connected platform.

## 8. How long we keep your information

We keep your account and team information for as long as your account is active. If you delete your account, this data is removed, except where we're required by law to retain certain records for longer. Content stored on behalf of other platforms follows the retention behaviour described in §5, governed by the publishing platform's own policy.

## 9. Security

We apply reasonable technical and organisational measures to protect personal information, including encrypted password storage, access-controlled monitoring dashboards, and single-use SSO tokens for ecosystem sign-in. No system is perfectly secure, and we can't guarantee absolute security.

## 10. Your rights under POPIA

Subject to applicable law, you have the right to:

- request access to the personal information we hold about you;
- request correction of inaccurate or incomplete information;
- request deletion of your personal information;
- object to our processing of your personal information in certain circumstances; and
- lodge a complaint with the Information Regulator of South Africa.

If your request concerns content stored by another platform, we may need to direct you to that platform, since they are the responsible party for it. To exercise any of these rights regarding your Dot.Memory account, contact privacy@infodot.co.za.

## 11. Cookies

Dot.Memory uses a session cookie to keep you signed in. See our Cookie Policy for details.

## 12. Changes to this Policy

We may update this Privacy Policy from time to time. If we make material changes, we'll update the "Last updated" date above and, where appropriate, notify you directly.

## 13. Contact us

If you have questions about this Privacy Policy or how we handle your personal information, contact us at privacy@infodot.co.za.
