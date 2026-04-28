# Sentinel Security Journal

## 2026-05-22 - SQL Injection in search_path Switching
**Vulnerability:** Raw string concatenation of tenant-controlled `schema_name` into `SET search_path` statements across multiple controllers and form requests.
**Learning:** Even if the schema name is generally considered "internal" or "system-set", allowing raw interpolation in SQL statements bypasses PDO parameterization (which doesn't support identifiers like schema names) and creates a risk if the `companies` table is ever compromised or mismanaged.
**Prevention:** Use a centralized, hardened helper like `Company::getSafeSearchPath()` that strictly validates/escapes identifiers and ensures the `public` schema is always appended, and add security comments to justify the pattern.
