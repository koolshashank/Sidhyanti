# Sidhyanti CRM v2 — built for the new `SidhyantiCRM` database

This is the complete PHP CRM, **fully rewritten against the new, clean
`SidhyantiCRM` database** (see the separate database package: files
`1_create_lookup_tables.sql` through `7_demo_seed_data.sql`). It no
longer touches the old `Sidhyanti` production schema at all.

## What's new compared to v1 (legacy-DB version)

- Every dropdown (stage, occupation, purpose, priority, etc.) is now
  driven by real lookup tables instead of hardcoded PHP arrays or
  undocumented status codes.
- Changing a member's or loan's stage is a **plain `UPDATE`** — the
  database trigger automatically records it in `Lead_Stage_History` /
  `Loan_Stage_History`. The app never has to think about audit logging.
- **`config/db.php`** now points at `SidhyantiCRM`.
- **Every request tells SQL Server who's logged in** via
  `sp_set_session_context`, so `Audit_Log` rows are correctly attributed
  (see `includes/functions.php` → `require_login()`).
- Moving a loan to **Disbursed** or **Active** auto-generates its
  `Repayment_Schedule` (once) — no more depending on an external system
  to have already populated it.
- New **"Record a Payment"** form on the loan page writes a real
  `Collection` row (supports partial payments, receipt numbers, payment
  mode) instead of overwriting a single collection column.
- New **Audit Log page** (`audit_log.php`) — browse every automatically
  logged create/update/delete, with who/when/before/after.
- Dashboard and Reports pages now read from the pre-built reporting
  views (`vw_Branch_Portfolio_Summary`, `vw_Loan_Pipeline`, `vw_Loan_TAT`,
  etc.) instead of re-deriving stage logic from raw dates.

## Setup

1. Set up the database first — run `1_create_lookup_tables.sql` through
   `5_reporting_views.sql`, then **either**:
   - `6_migrate_from_legacy_Sidhyanti.sql` to pull in your real production data, **or**
   - `7_demo_seed_data.sql` for a small ready-to-explore demo dataset.
2. Edit `config/db.php` with your SQL Server host/credentials.
3. Point your webserver at this folder, or run:
   ```bash
   php -S localhost:8000
   ```
4. Log in with any seeded `Staff` username — password `password123` for
   every demo account (change this before going live).

## Notes

- `password123` is accepted for **any** account as a demo convenience —
  remove that fallback in `index.php` before production use.
- The audit trail, stage history, and repayment-schedule generation all
  happen automatically via database triggers — you don't need to
  replicate that logic anywhere else if you build additional pages later.
