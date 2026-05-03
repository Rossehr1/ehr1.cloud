#!/usr/bin/env python3
"""
Load the current master dataset into the deployed MySQL schema.

Authoritative inputs are the CSV files under Data Originals (NPPES family, DNCS, etc.).
EP PAID uses `tools/load_ep_paid.py` and `merged_ep_paid_npi` (see EHR1-Full-Data.md).
The loader streams files in batches so the multi-GB NPI file is not held in memory.

By default, picks the lexicographically last file for each CMS pattern in the data
directory (e.g. newest npidata_pfile_*.csv). Override with --npidata-file, etc.

Stores row_count_skipped_invalid_npi and row_count_skipped_duplicate on ref_source_batch
when sql/mysql/09_ref_source_batch_metrics.sql has been applied; otherwise appends
counts to notes and prints a reminder.
"""
from __future__ import annotations

import argparse
import csv
import json
import re
from datetime import datetime
from pathlib import Path
from typing import Any

import pymysql
import pymysql.err


CSV_FIELD_SIZE_LIMIT = 1024 * 1024 * 128
DEFAULT_DATA_DIR = Path("Data Originals")
DEFAULT_CONFIG = Path("deploy/ehr1-cloud-app/includes/config.local.php")


PROVIDER_MAP = {
    "NPI": "npi",
    "Entity Type Code": "entity_type_code",
    "Replacement NPI": "replacement_npi",
    "Employer Identification Number (EIN)": "employer_identification_number",
    "Provider Organization Name (Legal Business Name)": "provider_organization_name",
    "Provider Last Name (Legal Name)": "provider_last_name",
    "Provider First Name": "provider_first_name",
    "Provider Middle Name": "provider_middle_name",
    "Provider Name Prefix Text": "provider_name_prefix",
    "Provider Name Suffix Text": "provider_name_suffix",
    "Provider Credential Text": "provider_credential",
    "Provider First Line Business Mailing Address": "mailing_address_line1",
    "Provider Second Line Business Mailing Address": "mailing_address_line2",
    "Provider Business Mailing Address City Name": "mailing_city",
    "Provider Business Mailing Address State Name": "mailing_state",
    "Provider Business Mailing Address Postal Code": "mailing_postal_code",
    "Provider Business Mailing Address Country Code (If outside U.S.)": "mailing_country",
    "Provider Business Mailing Address Telephone Number": "mailing_phone",
    "Provider Business Mailing Address Fax Number": "mailing_fax",
    "Provider First Line Business Practice Location Address": "practice_address_line1",
    "Provider Second Line Business Practice Location Address": "practice_address_line2",
    "Provider Business Practice Location Address City Name": "practice_city",
    "Provider Business Practice Location Address State Name": "practice_state",
    "Provider Business Practice Location Address Postal Code": "practice_postal_code",
    "Provider Business Practice Location Address Country Code (If outside U.S.)": "practice_country",
    "Provider Business Practice Location Address Telephone Number": "practice_phone",
    "Provider Business Practice Location Address Fax Number": "practice_fax",
    "Provider Enumeration Date": "enumeration_date",
    "Last Update Date": "last_update_date",
    "NPI Deactivation Date": "npi_deactivation_date",
    "NPI Reactivation Date": "npi_reactivation_date",
    "Provider Sex Code": "provider_sex_code",
    "Authorized Official Last Name": "authorized_official_last_name",
    "Authorized Official First Name": "authorized_official_first_name",
    "Authorized Official Middle Name": "authorized_official_middle_name",
    "Authorized Official Title or Position": "authorized_official_title",
    "Authorized Official Telephone Number": "authorized_official_phone",
    "Healthcare Provider Taxonomy Code_1": "healthcare_provider_taxonomy_code_1",
    "Provider License Number_1": "provider_license_number_1",
    "Provider License Number State Code_1": "provider_license_number_state_1",
    "Healthcare Provider Primary Taxonomy Switch_1": "healthcare_provider_primary_taxonomy_switch_1",
    "Healthcare Provider Taxonomy Code_2": "healthcare_provider_taxonomy_code_2",
    "Provider License Number_2": "provider_license_number_2",
    "Provider License Number State Code_2": "provider_license_number_state_2",
    "Healthcare Provider Primary Taxonomy Switch_2": "healthcare_provider_primary_taxonomy_switch_2",
}

ENDPOINT_MAP = {
    "NPI": "npi",
    "Endpoint Type": "endpoint_type",
    "Endpoint Type Description": "endpoint_type_desc",
    "Endpoint": "endpoint_url",
    "Affiliation": "affiliation",
    "Endpoint Description": "endpoint_description",
    "Affiliation Legal Business Name": "affiliation_legal_business_name",
    "Use Code": "use_code",
    "Use Description": "use_description",
    "Content Type": "content_type",
    "Content Description": "content_description",
}

PL_MAP = {
    "NPI": "npi",
    "Provider Secondary Practice Location Address- Address Line 1": "pl_address_line1",
    "Provider Secondary Practice Location Address-  Address Line 2": "pl_address_line2",
    "Provider Secondary Practice Location Address - City Name": "pl_city",
    "Provider Secondary Practice Location Address - State Name": "pl_state",
    "Provider Secondary Practice Location Address - Postal Code": "pl_postal_code",
    "Provider Secondary Practice Location Address - Country Code (If outside U.S.)": "pl_country",
    "Provider Secondary Practice Location Address - Telephone Number": "pl_phone",
    "Provider Secondary Practice Location Address - Telephone Extension": "pl_phone_extension",
    "Provider Practice Location Address - Fax Number": "pl_fax",
}

OTHER_NAME_MAP = {
    "NPI": "npi",
    "Provider Other Organization Name": "provider_other_organization_name",
    "Provider Other Organization Name Type Code": "provider_other_organization_name_type_code",
}


def _read_php_config(path: Path) -> dict[str, Any]:
    text = path.read_text(encoding="utf-8")

    def grab(key: str) -> str:
        m = re.search(rf"'{re.escape(key)}'\s*=>\s*'([^']*)'", text)
        if not m:
            raise ValueError(f"Missing config key: {key}")
        return m.group(1)

    port_m = re.search(r"'port'\s*=>\s*(\d+)", text)
    return {
        "host": grab("host"),
        "port": int(port_m.group(1)) if port_m else 3306,
        "database": grab("name"),
        "user": grab("user"),
        "password": grab("pass"),
        "charset": grab("charset"),
    }


def _blank_to_none(value: Any) -> Any:
    if value is None:
        return None
    s = str(value).strip()
    return s if s != "" else None


def _normalize_npi(value: Any) -> str | None:
    if value is None:
        return None
    s = re.sub(r"\D", "", str(value))
    return s if len(s) == 10 else None


def _date_or_none(value: Any) -> str | None:
    s = _blank_to_none(value)
    if s is None:
        return None
    for fmt in ("%m/%d/%Y", "%Y-%m-%d"):
        try:
            return datetime.strptime(str(s), fmt).date().isoformat()
        except ValueError:
            continue
    return None


def _batch_insert(cur: Any, table: str, cols: list[str], rows: list[tuple[Any, ...]], upsert: bool = False) -> None:
    if not rows:
        return
    col_sql = ", ".join(f"`{c}`" for c in cols)
    ph = ", ".join(["%s"] * len(cols))
    sql = f"INSERT INTO `{table}` ({col_sql}) VALUES ({ph})"
    if upsert:
        updates = ", ".join(f"`{c}` = VALUES(`{c}`)" for c in cols if c != "npi")
        sql += f" ON DUPLICATE KEY UPDATE {updates}"
    cur.executemany(sql, rows)


def _create_batch(cur: Any, source_key: str, path: Path, notes: str) -> int:
    cur.execute(
        "INSERT INTO ref_source_batch (source_key, file_name, file_effective_date, row_count_loaded, notes) "
        "VALUES (%s, %s, CURDATE(), 0, %s)",
        (source_key, path.name, notes),
    )
    return int(cur.lastrowid)


def _find_latest_csv(data_dir: Path, glob_pattern: str, label: str) -> Path:
    """Pick the lexicographically latest path matching the glob, excluding CMS *_fileheader.csv stubs."""
    paths = [
        p
        for p in data_dir.glob(glob_pattern)
        if p.is_file() and "fileheader" not in p.name.lower()
    ]
    if not paths:
        raise FileNotFoundError(f"{label}: no file matching {glob_pattern!r} under {data_dir}")
    paths.sort()
    chosen = paths[-1]
    if len(paths) > 1:
        print(f"{label}: using last of {len(paths)} matches: {chosen.name}", flush=True)
    return chosen


def _finish_batch(
    cur: Any,
    batch_id: int,
    loaded: int,
    invalid_npi: int = 0,
    duplicate_skipped: int = 0,
) -> None:
    try:
        cur.execute(
            "UPDATE ref_source_batch SET row_count_loaded = %s, "
            "row_count_skipped_invalid_npi = %s, row_count_skipped_duplicate = %s "
            "WHERE batch_id = %s",
            (loaded, invalid_npi, duplicate_skipped, batch_id),
        )
    except pymysql.err.ProgrammingError as e:
        if e.args[0] != 1054:
            raise
        extra = ""
        if invalid_npi or duplicate_skipped:
            extra = f" | invalid_npi={invalid_npi} dup_skipped={duplicate_skipped}"
        cur.execute(
            "UPDATE ref_source_batch SET row_count_loaded = %s, notes = CONCAT(COALESCE(notes, ''), %s) "
            "WHERE batch_id = %s",
            (loaded, extra, batch_id),
        )
        print(
            "ref_source_batch: run sql/mysql/09_ref_source_batch_metrics.sql (or php tools/migrate_batch_metrics_only.php) "
            "to store skip counts in columns.",
            flush=True,
        )


def _provider_row(row: dict[str, str], batch_id: int) -> tuple[Any, ...] | None:
    npi = _normalize_npi(row.get("NPI"))
    if npi is None:
        return None
    out: dict[str, Any] = {"source_batch_id": batch_id}
    for src, dst in PROVIDER_MAP.items():
        val = row.get(src)
        if dst == "npi":
            out[dst] = npi
        elif dst == "entity_type_code":
            v = _blank_to_none(val)
            out[dst] = int(v) if v and str(v).isdigit() else None
        elif dst.endswith("_date") or dst in ("enumeration_date", "last_update_date"):
            out[dst] = _date_or_none(val)
        else:
            out[dst] = _blank_to_none(val)
    return tuple(out[c] for c in list(PROVIDER_MAP.values()) + ["source_batch_id"])


def _mapped_row(row: dict[str, str], mapping: dict[str, str], batch_id: int) -> tuple[Any, ...] | None:
    npi = _normalize_npi(row.get("NPI"))
    if npi is None:
        return None
    out: dict[str, Any] = {"source_batch_id": batch_id}
    for src, dst in mapping.items():
        out[dst] = npi if dst == "npi" else _blank_to_none(row.get(src))
    return tuple(out[c] for c in list(mapping.values()) + ["source_batch_id"])


def _dedupe_key(item: tuple[Any, ...], cols: list[str], dedupe_cols: list[str]) -> tuple[Any, ...]:
    index = {c: i for i, c in enumerate(cols)}
    return tuple(item[index[c]] for c in dedupe_cols)


def _load_csv_mapped(
    conn: Any,
    path: Path,
    source_key: str,
    table: str,
    mapping: dict[str, str],
    batch_size: int,
    provider: bool = False,
    dedupe_cols: list[str] | None = None,
) -> int:
    cols = list(mapping.values()) + ["source_batch_id"]
    with conn.cursor() as cur:
        batch_id = _create_batch(cur, source_key, path, "master dataset load")
    conn.commit()

    loaded = 0
    skipped_duplicates = 0
    invalid_npi = 0
    seen: set[tuple[Any, ...]] = set()
    pending: list[tuple[Any, ...]] = []
    with path.open("r", encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            item = _provider_row(row, batch_id) if provider else _mapped_row(row, mapping, batch_id)
            if item is None:
                invalid_npi += 1
                continue
            if dedupe_cols is not None:
                sig = _dedupe_key(item, cols, dedupe_cols)
                if sig in seen:
                    skipped_duplicates += 1
                    continue
                seen.add(sig)
            pending.append(item)
            if len(pending) >= batch_size:
                with conn.cursor() as cur:
                    _batch_insert(cur, table, cols, pending, upsert=provider)
                conn.commit()
                loaded += len(pending)
                print(f"{path.name}: loaded {loaded:,}", flush=True)
                pending = []
    if pending:
        with conn.cursor() as cur:
            _batch_insert(cur, table, cols, pending, upsert=provider)
        conn.commit()
        loaded += len(pending)

    with conn.cursor() as cur:
        _finish_batch(cur, batch_id, loaded, invalid_npi, skipped_duplicates)
    conn.commit()
    if invalid_npi:
        print(f"{path.name}: skipped {invalid_npi:,} row(s) (invalid/missing NPI)", flush=True)
    if skipped_duplicates:
        print(f"{path.name}: skipped {skipped_duplicates:,} exact duplicate row(s)", flush=True)
    print(f"{path.name}: loaded {loaded:,} total", flush=True)
    return loaded


def _load_dncs(conn: Any, path: Path, batch_size: int) -> int:
    with conn.cursor() as cur:
        batch_id = _create_batch(cur, "dncs", path, "master dataset supplemental payload load")
    conn.commit()

    loaded = 0
    null_npi_rows = 0
    pending: list[tuple[Any, ...]] = []
    with path.open("r", encoding="utf-8-sig", newline="") as f:
        reader = csv.DictReader(f)
        for row in reader:
            npi = _normalize_npi(row.get("npi"))
            if npi is None:
                null_npi_rows += 1
            payload = {k: _blank_to_none(v) for k, v in row.items() if k != "adr_ln_2" and _blank_to_none(v) is not None}
            pending.append((npi, json.dumps(payload, ensure_ascii=False), batch_id))
            if len(pending) >= batch_size:
                with conn.cursor() as cur:
                    _batch_insert(cur, "supplemental_dncs_ndfile", ["npi", "payload_json", "source_batch_id"], pending)
                conn.commit()
                loaded += len(pending)
                print(f"{path.name}: loaded {loaded:,}", flush=True)
                pending = []
    if pending:
        with conn.cursor() as cur:
            _batch_insert(cur, "supplemental_dncs_ndfile", ["npi", "payload_json", "source_batch_id"], pending)
        conn.commit()
        loaded += len(pending)

    with conn.cursor() as cur:
        _finish_batch(cur, batch_id, loaded)
    conn.commit()
    if null_npi_rows:
        print(
            f"{path.name}: {null_npi_rows:,} row(s) stored with NULL NPI (DNCS — review or normalize upstream)",
            flush=True,
        )
    print(f"{path.name}: loaded {loaded:,} total", flush=True)
    return loaded


def _reset_tables(conn: Any) -> None:
    tables = [
        "core_npi_relationship",
        "core_npi_endpoint",
        "core_npi_practice_location",
        "core_npi_other_name",
        "supplemental_dncs_ndfile",
        "core_npi_provider",
    ]
    with conn.cursor() as cur:
        cur.execute("SET FOREIGN_KEY_CHECKS = 0")
        for table in tables:
            cur.execute(f"TRUNCATE TABLE `{table}`")
        cur.execute("DELETE FROM ref_source_batch WHERE source_key IN ('npidata','endpoint','pl','othername','dncs')")
        cur.execute("SET FOREIGN_KEY_CHECKS = 1")
    conn.commit()


def main() -> None:
    parser = argparse.ArgumentParser(description="Load authoritative master CSVs into MySQL")
    parser.add_argument("--data-dir", default=str(DEFAULT_DATA_DIR))
    parser.add_argument("--config", default=str(DEFAULT_CONFIG))
    parser.add_argument("--host", default=None)
    parser.add_argument("--port", type=int, default=None)
    parser.add_argument("--batch-size", type=int, default=1000)
    parser.add_argument("--reset", action="store_true", help="Clear existing master tables before loading")
    parser.add_argument(
        "--npidata-file",
        default=None,
        help="Path to npidata_pfile CSV (default: latest npidata_pfile_*.csv under data dir)",
    )
    parser.add_argument(
        "--endpoint-file",
        default=None,
        help="Path to endpoint CSV (default: latest endpoint_pfile_*.csv)",
    )
    parser.add_argument(
        "--pl-file",
        default=None,
        help="Path to pl CSV (default: latest pl_pfile_*.csv)",
    )
    parser.add_argument(
        "--othername-file",
        default=None,
        help="Path to othername CSV (default: latest othername_pfile_*.csv)",
    )
    parser.add_argument(
        "--dncs-file",
        default=None,
        help="Path to DNCS CSV (default: latest ndfiles-from-dncs-data-section*.csv)",
    )
    parser.add_argument("--skip-provider", action="store_true", help="Skip the main provider file")
    parser.add_argument("--skip-endpoint", action="store_true", help="Skip the endpoint child file")
    parser.add_argument("--skip-pl", action="store_true", help="Skip the secondary practice location child file")
    parser.add_argument("--skip-othername", action="store_true", help="Skip the other-name child file")
    parser.add_argument("--skip-dncs", action="store_true", help="Skip large DNCS supplemental file")
    args = parser.parse_args()

    csv.field_size_limit(CSV_FIELD_SIZE_LIMIT)
    cfg = _read_php_config(Path(args.config))
    if args.host is not None:
        cfg["host"] = args.host
    if args.port is not None:
        cfg["port"] = args.port

    conn = pymysql.connect(
        host=cfg["host"],
        port=int(cfg["port"]),
        user=cfg["user"],
        password=cfg["password"],
        database=cfg["database"],
        charset=cfg["charset"],
        autocommit=False,
    )
    data_dir = Path(args.data_dir)

    def pick_csv(override: str | None, glob_pat: str, label: str) -> Path:
        if override:
            p = Path(override)
            if not p.is_file():
                raise FileNotFoundError(f"Not a file: {p}")
            return p
        return _find_latest_csv(data_dir, glob_pat, label)

    try:
        if args.reset:
            _reset_tables(conn)
        if not args.skip_provider:
            _load_csv_mapped(
                conn,
                pick_csv(args.npidata_file, "npidata_pfile_*.csv", "npidata"),
                "npidata",
                "core_npi_provider",
                PROVIDER_MAP,
                args.batch_size,
                provider=True,
            )
        if not args.skip_endpoint:
            _load_csv_mapped(
                conn,
                pick_csv(args.endpoint_file, "endpoint_pfile_*.csv", "endpoint"),
                "endpoint",
                "core_npi_endpoint",
                ENDPOINT_MAP,
                args.batch_size,
                dedupe_cols=["npi", "endpoint_type", "endpoint_url", "affiliation", "use_code", "content_type"],
            )
        if not args.skip_pl:
            _load_csv_mapped(
                conn,
                pick_csv(args.pl_file, "pl_pfile_*.csv", "pl"),
                "pl",
                "core_npi_practice_location",
                PL_MAP,
                args.batch_size,
                dedupe_cols=["npi", "pl_address_line1", "pl_address_line2", "pl_city", "pl_state", "pl_postal_code", "pl_phone"],
            )
        if not args.skip_othername:
            _load_csv_mapped(
                conn,
                pick_csv(args.othername_file, "othername_pfile_*.csv", "othername"),
                "othername",
                "core_npi_other_name",
                OTHER_NAME_MAP,
                args.batch_size,
                dedupe_cols=["npi", "provider_other_organization_name", "provider_other_organization_name_type_code"],
            )
        if not args.skip_dncs:
            _load_dncs(conn, pick_csv(args.dncs_file, "ndfiles-from-dncs-data-section*.csv", "dncs"), args.batch_size)
    finally:
        conn.close()


if __name__ == "__main__":
    main()
