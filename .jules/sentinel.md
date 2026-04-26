# Sentinel Journal - Leopardo RH

## 2026-05-22 - Sentinel Initialized
Mission: Protect the codebase from vulnerabilities and security risks.
Focus: Backend (Laravel), Mobile (Flutter), Web (Next.js).
Current Project Version: 4.1.73

## 2026-05-22 - SQL Injection in SET search_path
**Vulnerability:** SQL Injection via unsanitized schema names in `SET search_path` statements. PostgreSQL identifiers cannot be parameterized in these statements.
**Learning:** Raw string concatenation or regex-based sanitization for schema names is insufficient. Correct escaping involves doubling internal double quotes and wrapping in double quotes.
**Prevention:** Use a centralized helper like `Company::getSafeSearchPath()` for all dynamic `SET search_path` calls.
