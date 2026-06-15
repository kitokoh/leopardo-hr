# Security Policy — Leopardo RH

At Leopardo RH, security is not a feature; it's our foundation. We follow industry best practices to ensure the confidentiality, integrity, and availability of your HR data.

## 🛡 Security Architecture

### 1. Data Isolation
-   **Hybrid Multi-Tenancy:** We offer both logical (column-based) and physical (schema-based) isolation.
-   **Strict Scoping:** All database queries are automatically scoped by `company_id` at the infrastructure level.

### 2. Network Security
-   **TLS Encryption:** All data in transit is encrypted using TLS 1.3.
-   **WAF Protection:** Application-level firewall to prevent OWASP Top 10 attacks (SQLi, XSS, CSRF).
-   **DDoS Mitigation:** Infrastructure provided by Cloudflare and Render.

### 3. Application Security
-   **SAST/DAST:** Automated static and dynamic security testing in our CI/CD pipeline.
-   **Dependency Scanning:** We use `dependabot` and `composer audit` to monitor for vulnerable packages.
-   **Secrets Management:** Environment secrets are managed via GitHub Secrets and encrypted at the provider level.

---

## 🔒 Data Protection

-   **Encryption at Rest:** Sensitive fields (National ID, Bank Details, Passwords) are encrypted using AES-256.
-   **Anonymization:** Logging systems automatically redact PII (Personally Identifiable Information).
-   **Backups:** Daily encrypted backups with a 30-day retention policy.

---

## 🛠 Vulnerability Disclosure

We welcome reports from security researchers. If you find a vulnerability, please:
1.  **Do NOT** exploit it for any reason.
2.  **Report it** privately to [security@leopardo-rh.com](mailto:security@leopardo-rh.com).
3.  Give us **reasonable time** to investigate and fix the issue before public disclosure.

---

## 📜 Compliance
-   **GDPR Ready:** Built with data privacy principles in mind.
-   **ISO 27001 Roadmap:** Architecture designed to meet ISO 27001 control requirements.

---

For technical identity details, see [Auth System](AUTH_SYSTEM.md).
