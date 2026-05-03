# EHR1 Master App (milestone pin)

**Name:** Master App  
**Pinned date:** 2026-05-01  

This document marks a **stable reference** for the EHR1 Data web app and database tooling. Treat it as the snapshot to return to when experimenting with new features, schema, or loads.

---

## What this pin covers

| Area | Location |
|------|----------|
| Deployable PHP app | `deploy/ehr1-cloud-app/` |
| MySQL schema (source of DDL order) | `sql/mysql/` |
| Master CSV load (streaming) | `tools/load_master_dataset.py` |
| EP PAID load + merge to **`merged_ep_paid_npi`** | `tools/load_ep_paid.py`, `tools/merge_ep_paid_to_npi.py` |
| Data definitions & merge rules | `EHR1-Full-Data.md` |

**Authoritative file set:** **`Data Originals/`** (CMS/NPPES baseline for **`core_*`**). **EP PAID:** **`supplemental_ep_paid`** + **`merged_ep_paid_npi`** via **`tools/load_ep_paid.py`** / **`merge_ep_paid_to_npi.py`** (`EHR1-Full-Data.md`).

**App identity at runtime:** pages that load `includes/bootstrap.php` expose milestone constants `EHR1_APP_MILESTONE` and `EHR1_APP_MILESTONE_DATE` (see footer on Status and Data explorer).

---

## How to get back here

1. **Workspace:** Restore the project tree from backup or copy that matches this milestone (this file + `bootstrap.php` milestone lines + `EHR1-Full-Data.md` cross-reference).
2. **Git (if you use it elsewhere):** From a machine with Git, at this tree state run  
   `git tag -a master-app -m "EHR1 Master App milestone"`  
   and push tags if remotes apply.

---

## Intentionally unchanged at this pin

- No new scope beyond what is already described in **`EHR1-Full-Data.md`** (phases, open decisions, supplemental feeds).
- **Minimal / additive** changes only after this pin unless you start a new milestone document.

---

## Amendments after the pin date

| Date | Change |
|------|--------|
| 2026-05-01 | **Data explorer:** the grid form `action` uses `ehr1_url('/reports/index.php')` (from `http_base_path`) instead of `REQUEST_URI`, so **Run report** / **Update lists** POST to the real app URL. Fixes filters appearing to do nothing when the host reports a path without the subdirectory. File: `deploy/ehr1-cloud-app/includes/grid_report.inc.php`. |

---

*For day-to-day rules, see `.cursor/rules/EHR1-Data-Rules.mdc`.*
