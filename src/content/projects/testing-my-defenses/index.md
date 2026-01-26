---
title: "Testing My Defenses"
summary: "Offensive security validation of my own Cloudflare-protected infrastructure"
date: 2025-12-22
status: completed
featured: false
technologies:
  - Nmap
  - Kali Linux
  - Cloudflare
  - Penetration Testing
  - OSINT
description: "A methodical assessment of my production websites using offensive security tools, validating defense-in-depth and documenting what attackers see versus what defenders capture."
---

## Overview

Theory needs validation. After building the [HomeLab SOC](/projects/homelab-soc/), I needed proof the defenses actually work—not just trust that they should.

I attacked my own infrastructure from my Kali machine using Nmap's aggressive scan mode, then analyzed the results from both perspectives: what the attacker learned, and what my SOC captured.

**The question:** Does my Cloudflare + SOC architecture actually protect my origin server and provide visibility into attack attempts?

**The answer:** Yes—with receipts.

## Methodology

### Test Environment

| Component | Details |
|-----------|---------|
| Attack machine | Kali Linux (home network) |
| Target | bytesbourbonbbq.com |
| Tool | Nmap 7.95 |
| Scan type | Aggressive (`-A`): OS detection, version detection, script scanning, traceroute |

### The Scan

```bash
nmap -v -A bytesbourbonbbq.com -oN bbbq_scan.txt
```

This is what a real attacker would run during reconnaissance—service enumeration, OS fingerprinting, and vulnerability probing in a single command.

## Attacker's Perspective

### What Nmap Found

![Nmap scan results](/images/projects/testing-my-defenses/nmap-output.png)

**Open ports:**

| Port | Service | Version |
|------|---------|---------|
| 80/tcp | http | Cloudflare http proxy |
| 443/tcp | ssl/http | Cloudflare http proxy |
| 8080/tcp | http | Cloudflare http proxy |
| 8443/tcp | ssl/http | Cloudflare http proxy |

Every port returns "Cloudflare http proxy"—not Apache, not the actual server version. The attacker learns nothing about my origin infrastructure.

**OS Detection:**

```
Aggressive OS guesses: Apple iOS 14.0 - 15.6 or tvOS 14.3 - 16.1 (89%), 
Apple iOS 15.7 (89%), Apple macOS 11 (Big Sur) - 13 (Ventura) (89%), 
FreeBSD 11.0-RELEASE (89%)...
```

Nmap is completely confused. My server runs Debian, but Cloudflare's edge network makes OS fingerprinting unreliable. The attacker gets no useful OS intelligence.

**SSL Certificate:**

```
ssl-cert: Subject: commonName=bytesbourbonbbq.com
Issuer: commonName=WE1/organizationName=Google Trust Services/countryName=US
```

The certificate is issued by Google Trust Services via Cloudflare—not my Let's Encrypt cert on the origin. Another layer of abstraction hiding the actual infrastructure.

**What the attacker learned:**
- ✅ The site exists
- ✅ It's behind Cloudflare
- ❌ Origin IP address
- ❌ Actual server software
- ❌ Operating system
- ❌ Anything useful for exploitation

## Defender's Perspective

While Nmap saw almost nothing useful, my SOC captured everything.

### Cloudflare Security Events

![Cloudflare analytics](/images/projects/testing-my-defenses/cloudflare-events.png)

Cloudflare logged 142 requests during the scan window:
- **72 mitigated** by Cloudflare security rules
- **69 served by origin** (probes that looked legitimate enough to forward)

The traffic spike around 13:10-13:15 shows exactly when the scan ran. The "served by origin" requests are what I want to analyze in my SIEM—Nmap's HTTP probes that passed initial filtering.

### Catching Nmap Red-Handed

![Cloudflare request detail](/images/projects/testing-my-defenses/cloudflare-detail.png)

Drilling into a specific request reveals the smoking gun:

| Field | Value |
|-------|-------|
| User Agent | `Mozilla/5.0 (compatible; Nmap Scripting Engine; https://nmap.org/book/nse.html)` |
| Path | `/HNAP1` |
| Method | GET |
| ASN | 701 - UUNET |

Nmap doesn't hide—it announces itself in the user agent. The `/HNAP1` path is a known vulnerability probe targeting D-Link router management interfaces. Nmap's scripts check for common misconfigurations automatically.

This request wasn't mitigated (it looked like a normal GET), but it's now logged, indexed in OpenSearch, and available for threat hunting.

### What Reached the Origin

Nmap's HTTP-based probes that Cloudflare forwarded:

| Method | Count | Purpose |
|--------|-------|---------|
| GET | 56 | Site crawling, page enumeration |
| OPTIONS | 47 | HTTP method discovery |
| POST | 13 | Form/upload endpoint discovery |
| HEAD | 10 | Header-only requests |
| UNKNOWN | 16 | Malformed methods (error handling tests) |

The **UNKNOWN** methods are particularly interesting—Nmap sends garbage input to test how the server handles malformed requests. Good for fingerprinting error handling behavior. My server correctly rejected these.

### Threat Intelligence Enrichment

My SOC automation checked the scanning IP against AbuseIPDB:

- **Abuse Score:** 0/100
- **Reports:** 0
- **Action:** None (not blocked)

This is correct behavior—my home IP has no malicious history, so it wasn't auto-blocked. If this had been a known malicious IP (score ≥90), it would have been blocked at Cloudflare before completing the scan.

## Key Findings

### Defense-in-Depth Validated

| Layer | Function | Result |
|-------|----------|--------|
| Cloudflare Edge | Hide origin, filter obvious attacks | ✅ Origin IP protected |
| Cloudflare WAF | Block malicious patterns | ✅ 72 requests mitigated |
| Origin Logging | Capture what gets through | ✅ Full visibility in SIEM |
| Threat Intel | Identify known bad actors | ✅ Would block if score ≥90 |

### Attacker Frustration Points

1. **No origin IP** — Can't bypass Cloudflare to attack directly
2. **No OS fingerprint** — Can't tailor exploits to specific platform
3. **No server version** — Can't search for version-specific CVEs
4. **Vulnerability probes logged** — /HNAP1 and other probes captured for analysis
5. **Error handling secure** — Malformed requests don't leak info

### Defender Advantages

1. **Full scan visibility** — Every probe logged and searchable
2. **Automated enrichment** — IPs checked against threat intel automatically
3. **Geographic context** — Know where attacks originate
4. **Method analysis** — Understand attacker techniques
5. **Correlation capability** — Link this scan to future activity from same IP

## What This Demonstrates

**Offensive Security Skills:**
- Reconnaissance methodology
- Nmap scan interpretation
- Understanding attacker perspective
- Identifying what information leakage to prevent

**Defensive Analysis:**
- Log correlation across multiple sources
- Understanding what "normal" scanning looks like
- Validating security controls work as designed
- Translating attacks into detection opportunities

**Security Architecture:**
- Defense-in-depth effectiveness
- Edge security value proposition
- Visibility requirements for detection
- Automated response capabilities

## Lessons Learned

1. **Test your own defenses.** Assumptions aren't proof. Running offensive tools against your infrastructure validates (or breaks) your security model.

2. **Cloudflare earns its keep.** The origin IP protection and OS fingerprint masking alone justify the proxy layer. Attackers hitting Cloudflare learn almost nothing useful.

3. **Forward what you want to analyze.** Cloudflare blocking everything would mean no SIEM data. Letting "legitimate-looking" probes through to the origin creates the log data needed for detection engineering.

4. **Scanners announce themselves.** Nmap's user agent is a gift to defenders. Real attackers might spoof this, but automated scanners often don't bother.

5. **Correlate attacker and defender views.** Seeing both perspectives—what the attacker got vs. what we captured—tells the complete story.

## Related

- [HomeLab SOC](/projects/homelab-soc/) — The infrastructure that made this analysis possible

---

*Self-assessment is ongoing. New attack techniques get tested against the infrastructure regularly to validate detection capabilities.*
