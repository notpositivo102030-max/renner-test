# WAF enterprise / ModSecurity CRS baseline

Use a managed WAF (Cloudflare Enterprise/Akamai/AWS WAF/F5/Imperva) or ModSecurity CRS in front of the origin. Minimum policy:

- OWASP CRS paranoia level 2 in staging, promote to production after tuning false positives.
- Managed rules: SQLi, XSS, RFI/LFI, command injection, protocol anomalies, known CVEs, bot reputation, ASN/country policy when legally approved.
- Custom rules:
  - Challenge or block high-rate `POST /admin/login.php`.
  - Block direct access to `*.db`, `*.sqlite`, `*.sql`, `*.bak`, `.env`, `/app/`, `/config/`, `/docs/`, `/deploy/`, `/.git/`.
  - Enforce positive content-type rules for POST endpoints.
  - Alert on repeated 400/401/403/429 responses per IP/user-agent.
- Anti-DDoS: always-on L3/L4 and L7 protection with origin IP hidden from public DNS.
- Log all WAF events to SIEM with request ID, action, rule ID, source IP, URI, country/ASN, user-agent and sampled payload metadata with sensitive value redaction.
