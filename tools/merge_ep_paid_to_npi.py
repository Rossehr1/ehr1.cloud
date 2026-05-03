#!/usr/bin/env python3
"""
Rebuild merged_ep_paid_npi from supplemental_ep_paid.

Rule (v1): one row per NPI — payload from the supplemental row with MAX(ep_paid_id).
Does not read the workbook; safe to run after any EP PAID import batch.

Uses the same deploy config pattern as load_ep_paid.py (--config, --host, --port).
"""
from __future__ import annotations

import argparse
import sys
from pathlib import Path
from typing import Any

import pymysql

_TOOLS = Path(__file__).resolve().parent
sys.path.insert(0, str(_TOOLS))

from load_master_dataset import _read_php_config  # noqa: E402

DEFAULT_CONFIG = Path("deploy/ehr1-cloud-app/includes/config.local.php")

_REBUILD_SQL = """
INSERT INTO merged_ep_paid_npi (npi, payload_json, source_ep_paid_id, source_batch_id)
SELECT s.npi, s.payload_json, s.ep_paid_id, s.source_batch_id
FROM supplemental_ep_paid s
INNER JOIN (
  SELECT npi, MAX(ep_paid_id) AS max_id
  FROM supplemental_ep_paid
  WHERE npi IS NOT NULL AND npi != ''
  GROUP BY npi
) x ON s.npi = x.npi AND s.ep_paid_id = x.max_id
"""


def rebuild_merged_ep_paid(conn: Any) -> int:
    """Delete all merged rows and repopulate from supplemental. Returns row count after rebuild."""
    with conn.cursor() as cur:
        cur.execute(
            "SELECT COUNT(*) FROM information_schema.tables "
            "WHERE table_schema = DATABASE() AND table_name = 'merged_ep_paid_npi'"
        )
        if cur.fetchone()[0] == 0:
            raise RuntimeError("Table merged_ep_paid_npi missing — apply sql/mysql/11_merged_ep_paid.sql")
        cur.execute("DELETE FROM merged_ep_paid_npi")
        cur.execute(_REBUILD_SQL)
    with conn.cursor() as cur:
        cur.execute("SELECT COUNT(*) FROM merged_ep_paid_npi")
        return int(cur.fetchone()[0])


def main() -> None:
    parser = argparse.ArgumentParser(description="Rebuild merged EP PAID rows (latest supplemental per NPI)")
    parser.add_argument("--config", default=str(DEFAULT_CONFIG))
    parser.add_argument("--host", default=None)
    parser.add_argument("--port", type=int, default=None)
    args = parser.parse_args()

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
    try:
        n = rebuild_merged_ep_paid(conn)
        conn.commit()
        print(f"merged_ep_paid_npi: {n:,} row(s) (latest supplemental per NPI)", flush=True)
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()


if __name__ == "__main__":
    main()
