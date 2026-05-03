# EHR1 Full Data — Plan

## Mission (this phase)

Build toward **one searchable database** for NPPES-style and related data—**without** boiling the ocean.

- **Concise:** Short documents, short iterations, small change sets.
- **Narrow scope:** Only the **next** milestone is committed work; everything else stays **out of scope** until we deliberately widen it.
- **Rules:** Day-to-day guardrails live in **`.cursor/rules/EHR1-Data-Rules.mdc`**. When behavior or structure changes, **update this plan and those rules together** so they stay aligned.

---

## North star (where we’re headed)

A **single query surface** (eventually) for providers, locations, endpoints, other names, and optional supplemental files—joined on **NPI** where possible, with **indexes** so routine search does not scan multi‑GB CSVs. **Repeatable loads** when new CMS files arrive.

*This section is direction only; timing follows the phased roadmap below.*

---

## Master dataset (authoritative)

Treat everything under **`Data Originals/`** **except** the file **`EP PAID Complete - Final 4-10-26.xlsx`** as the **current, authoritative master** for builds, loads, and reconciliation. That set (NPPES files, DNCS extract, documentation PDFs, etc.) is the **source of truth** for “most current” data.

**`EP PAID Complete - Final 4-10-26.xlsx`** is **supplemental**—use it for joins or reporting when needed, but **do not** treat it as the clock for overall freshness or as a replacement for the master files above.

The Access database **`2026 Data.accdb`** has been **removed** from this project; the consolidated database is the system of record going forward.

---

## Recommended operating rules (aligned with project rules)

These mirror **`EHR1-Data-Rules`**. Use them when deciding whether to add work or complexity.

| Principle | Meaning |
|-----------|--------|
| **One step at a time** | Finish the current phase before starting the next; avoid parallel “nice to haves.” |
| **Minimal change** | Implement only what the task requires; no drive-by refactors or extra docs unless needed. |
| **Protect raw sources** | Never hand-edit **`Data Originals/`**; transform in the database or in copied/derived outputs. |
| **Stable identifiers** | **NPI** and **ZIP** as **text** in the database. |
| **Name things clearly** | Files and modules are short and self-explanatory; top-level docs use **`EHR1-<Topic>.md`** where it fits. |
| **Keep code small** | Hand-written source files **≤ 500 lines**; split rather than grow a monolith. |
| **Document as you go** | Brief module notes + docstrings on public pieces; update when behavior changes. |
| **Record uncertainty** | Unknown joins, duplicate files, or policy calls → **Open decisions** below, not silent guesses. |
| **Widen scope deliberately** | New rule categories (validation, secrets, full automation) get added to **`EHR1-Data-Rules`** and this plan when we actually need them—not preemptively. |
| **Deploy, then smoke-test** | After deploying the web app, verify the changed behavior (e.g. load a key URL). If it fails, fix with the smallest change; keep the deployed footprint small. |

---

## Data inventory (reference)

| Asset | Role |
|--------|------|
| `npidata_pfile_*.csv` | Main NPI provider file (~11 GB) |
| `BigOne.csv` | **Verify** vs npidata before loading both |
| `endpoint_pfile_*.csv`, `pl_pfile_*.csv`, `othername_pfile_*.csv` | Endpoints, practice locations, other names |
| `*_fileheader.csv` | Headers for the above |
| `ndfiles-from-dncs-data-section.*` | Large extract — **defer** detailed modeling until core path works |
| `NPPES_Data_Dissemination_*.pdf` | CMS documentation |
| `EP PAID Complete - Final 4-10-26.xlsx` | **Supplemental only** — **not** part of the authoritative master set (see **Master dataset** above). Load into `supplemental_ep_paid` with `tools/ep_paid_sync.py load` (reads **all worksheets**, merges columns by NPI, fixes blank/duplicate Excel headers); optional: regenerate `deploy/ehr1-cloud-app/includes/ep_paid_headers.generated.json` for a fixed column order (otherwise the explorer **discovers** EP PAID field names from `payload_json` in the database). The explorer **merges** EP PAID rows to NPPES by NPI and **appends** EP PAID-only NPIs when filters do not require NPPES-only fields. |

---

## Technology (lightweight choice)

- **SQLite:** Smallest operational step for a **local** prototype (single file, few moving parts).
- **PostgreSQL:** Strong when you need **concurrent access**, **pg_trgm** name search, and a **network-facing** app—matches **Hostinger VPS** (see Hosting), not typical **shared** web hosting.

Pick one for **v1**; migrating later is allowed. **SQL Server** only if the org requires it.

**Hosted target:** **Hostinger** — site hostname **`ehr1.cloud`** (Business Web Hosting), **few trusted users**—see **Hosting** below.

---

## Hosting (Hostinger, ehr1.icloud)

**Current plan:** **Business Web Hosting** (shared: **MySQL/MariaDB**, PHP—not a VPS).

**Plan purchase date:** **2024-08-07** — under Hostinger’s published tiers this falls in **Web hosting V1** (plans purchased **until 2025-04-23**). See [New Web and Cloud Hosting Limits at Hostinger](https://support.hostinger.com/en/articles/10717644-new-web-and-cloud-hosting-limits-at-hostinger).

**Published limits for V1 · Business** (always **confirm in hPanel → Plan details**; Hostinger can adjust accounts):

| Item | Documented V1 Business |
|------|-------------------------|
| Storage | **200 GB** |
| Databases | **300** |
| Websites | **100** |
| RAM | **3 GB** |
| CPU | **2** cores |

**Implication for EHR1:** More **total account disk** than on current **V2/V3** Business docs (**50 GB**)—still check **per-MySQL-database** max and **RAM** before assuming a **full** NPPES import will fit.

**Scope (strict):** **Only `ehr1.icloud`.** Do **not** change **any other** website, domain, subdomain, folder, or database elsewhere in the Hostinger account. All new code, DB users, and uploads for this project stay **under this hostname’s document root** (and its MySQL database)—**nothing else** in the account is touched.

**Site:** **`ehr1.icloud`** — **do not change** existing pages on this hostname except **adding** the new app area (new subdirectory + new DB + config). No edits to unrelated sites on other domains in the same account.

**Audience:** **Few trusted users**; app at an **unlinked** path on **`ehr1.icloud`** (“hidden page”—not advertised in main nav).

**Note:** If hPanel shows a slightly different URL (e.g. `www` prefix or another suffix), use **Hostinger’s listed site URL**—the rule stays: **only this site**, nowhere else in the account.

### Website integration (add-only)

- **“Hidden”** here means **not linked** from primary navigation; users open it via **bookmark** or **direct URL**.
- **Security:** Obscure URLs are **not** enough—use **HTTPS** + **login** (session, HTTP basic, or hPanel **password-protected directory**) so leaked links don’t expose data.
- **Implementation:** New files under e.g. `https://ehr1.icloud/<app-folder>/` (PHP or stack your plan supports) in a **dedicated folder** on **this** site only—avoid editing existing global templates unless there is no other way (prefer **none**).

### Capacity & database engine

| Approach | Fits this project? |
|----------|---------------------|
| **Business Web Hosting (shared)** | **MySQL** — **per-database** limits in hPanel → **Databases**. This account is **V1 Business** (~**200 GB** account storage per Hostinger’s table—**verify in hPanel**). **Full NPPES-scale** DB + indexes may still hit **per-DB** caps or **memory** on import—**verify before** a full load. |
| **VPS** (add/upgrade, KVM) | **Best fit for full consolidated NPPES** in one database: **PostgreSQL**, sized **NVMe**, no shared MySQL cap dance. |

**If you stay on Business Web Hosting only:** Design for **MySQL** + a **smaller footprint** (e.g. **subset** of columns, **active NPIs only**, or **regional** slice)—or host only a **summary/search** table built locally and **import** that. **Do not** assume the full raw NPPES file will load until hPanel numbers say it will.

**If you add a VPS:** Use **PostgreSQL** there for the **full** database; keep Business hosting for a **landing site** or **redirect**, or migrate the app to the VPS—your call.

**Platform implication (few users over the internet):** Put a **small app** in front—**HTTPS + login**. On shared hosting, the app talks to **MySQL** on localhost; **do not** expose MySQL to the public internet. On a VPS, bind **Postgres** to localhost and same pattern.

**Security (basics):** Strong passwords, least-privilege DB user for the app, firewall on VPS (SSH + 80/443). Keep Hostinger panel + server patches current.

**Action:** In **hPanel**, note **(1)** max **MySQL database** size, **(2)** total **disk** remaining, **(3)** whether **PostgreSQL** is even available (it usually is **not** on shared—only **MySQL**).

---

## Phased roadmap (narrow → wider)

| Phase | Scope (keep it small) |
|-------|------------------------|
| **A — Decide + profile** | Record **Hostinger plan** (now **Business Web Hosting**): check **MySQL DB size limit** + **disk** in hPanel vs expected DB size. Decide **MySQL-only on shared** (subset?) vs **add VPS + Postgres** for full data. Confirm **`BigOne` vs `npidata`**. Optional **SQLite** for local dev. **Stop here** if nothing is loaded yet—that’s still progress. |
| **B — First load** | One **chunked** load path (e.g. main NPI file → **staging** only). No UI, no API. |
| **C — Core + search** | Typed **core** table(s), **NPI** text, basic **indexes**; optional simple **materialized** or denormalized slice for search. |
| **D — Children** | Endpoint, PL, other name tables **after** main NPI path is stable. |
| **E — Supplemental** | EP PAID, DNCS—**only** after D is done and documented. |
| **F — Optional** | **Search UI** on **`ehr1.icloud`** only—**dedicated subpath** (unlinked); automation for monthly CMS drops—only if still needed. |

*Phases C–F are **out of scope** until the previous phase is complete unless we explicitly reprioritize in this file.*

### Prereqs to finish Phase A and start Phase B

Check these before writing load scripts or deploying to **`ehr1.cloud`**:

| Prereq | Why |
|--------|-----|
| **hPanel numbers** | Note **max MySQL database size**, **free disk**, and **PHP version** for this site—shared limits can block a huge single DB even with **200 GB** account storage. |
| **`BigOne` vs `npidata`** | Pick **one** main feed for v1; document in **Open decisions**. |
| **App URL path** | Choose a fixed subpath (e.g. `/app/` or `/data/`) for the add-on UI—**unlinked**, **login**-protected. |
| **Credentials hygiene** | Any password shared outside a vault should be **rotated**; app config only on server or gitignored `.env`. |
| **Close or defer “where full DB lives”** | Either commit to **MySQL on this Business plan** (after sizing) or schedule a **VPS** for Postgres—don’t leave it ambiguous once imports start. |

---

## Schema and ETL (defer detail)

When you reach **B** and **C**, use **staging** (raw-ish) then **core** (typed, validated). Chunk large CSVs; record **source file / date** per batch. Add **indexes** when you add real query patterns—not before.

**Initial MySQL DDL + test seed:** `sql/mysql/` — `ref_source_batch`, `core_npi_provider` (expandable toward full NPPES), `core_npi_endpoint`, `core_npi_practice_location`, `core_npi_other_name`, supplemental placeholders; `99_seed_test_data.sql` loads a few synthetic rows. See `sql/mysql/apply_order.txt`. Full parity with all CMS columns will use **ALTER** / new migrations from `npidata_pfile_*_fileheader.csv`.

**Production web (PHP on `ehr1.cloud`):** `deploy/ehr1-cloud-app/` — status page, **Reports** (`/reports/`) for NPI-centric reports, cross-reference search, and practice/group rosters; `core_npi_relationship` links group NPI → individual NPIs. Upload to `public_html/ehr1-data/`, set `http_base_path` in config (e.g. `/ehr1-data`). After deploy, `chmod 755` on `reports/` and `assets/` if SCP leaves them `700`. See `README-DEPLOY.txt` and `tools/migrate_relationship_only.php` for adding the relationship table to an existing DB.

*(Full normalization, trigram search, and idempotent batch patterns can be spelled out in a short addendum when we enter phase C.)*

---

## Risks (short)

- **Disk:** Full load + indexes can need **multiple ×** raw CSV size—watch free space before big loads.
- **Shared hosting:** **Business Web Hosting** caps **per-MySQL-database** size and total **account** storage—easy to hit with a **full** NPPES import; check hPanel **before** loading everything.
- **Compliance:** Public NPPES vs internal lists—follow **internal policy** for access and exports.

---

## Open decisions

- [x] **Hosting provider:** **Hostinger**; **few users**.
- [x] **Current Hostinger product:** **Business Web Hosting** (shared; **MySQL**, not Postgres).
- [x] **Deploy target:** **`ehr1.icloud` only** — **no** changes anywhere else in the Hostinger account; on this hostname, database app only as **add-on** at a **separate, unlinked** path + **login** (do not rework unrelated pages unless unavoidable).
- [ ] **Where the full DB lives:** **MySQL on Business** (only if hPanel **limits** allow after sizing—often requires a **reduced** dataset) **vs** **add Hostinger VPS** with **PostgreSQL** for **full** NPPES-scale consolidation.
- [ ] **Local dev:** **SQLite** or **Postgres** for early load tests—either is fine; keep **NPI/ZIP as text** everywhere.
- [ ] **`BigOne.csv` vs `npidata`:** duplicate or not; load **one** for v1

---

*Last updated: 2026-04-10* · **Site:** `ehr1.icloud` only (confirm exact URL in hPanel)
