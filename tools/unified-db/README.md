# Unified local database migration workspace

## Backend adapters started

The original BS Units backend now has disabled-by-default authenticated canonical catalog routes and an internal audited current-price write service. Real database, HTTP, permission and two-session retry tests pass. Test mutations were rolled back or confined to disposable scratch data. See `outputs/unified-db/backend-adapter-progress.md`. Existing mobile endpoints/admin workflows still use the original DB.

Beloan's copied application is missing its main `vendor/autoload.php`. Restore matching code/dependencies from the running server container before full runtime testing; do not boot it with production environment settings.

## Latest customer and transaction rehearsal

Customer accounts/profiles, account evidence, signed buyer snapshots, sales, loan core and BS Units request/contract history are now populated. See `outputs/unified-db/verified-final-model.md` for the current model, confirmed source policies and the remaining cutover work. Earlier progress sections below describe the stages as they were completed.

The user approved strict person matching (National ID, full name, birthdate and gender) and BS Units current availability. There are 26,893 separate customer accounts, 26,850 people after 43 duplicate-profile merges, and 3,291 verified signed-buyer links. No financial accounts were merged. Missing/ambiguous identities remain unresolved.

Run order after the property rehearsal: `promote-customers.sql`, `promote-customer-evidence.py`, `promote-buyer-snapshots.py`, `resolve-customer-identities.py`, `promote-sales.py`, `promote-loan-core.py`, `promote-request-history.py`, `review-availability.sql`, `apply-availability-policy.sql`, then `promote-ledger-history.py`. Scripts refuse populated targets; they are not a repeatable application deployment procedure.

Current availability follows the referenced BS Units action, not merely the `saleable` flag. The catalog has 5,708 AVAILABLE, 7 RESERVED, 11,900 SOLD, 3,487 UNAVAILABLE and 373 UNVERIFIED legacy-only units. Unit action history is preserved and checksummed. No backend connection has been changed.

Ledger history and financial auxiliary tables are now verified. There are 918 source-qualified staff/login identities, 63 scoped roles and 322 scoped permissions. Companies and accounting branches remain independent: two branches and 20 project/accounting-branch links are preserved without assigning a company by name. Access-control orphan references remain explicitly reported, not converted into grants for a different user.

Run `verify-final-rehearsal.py` for read-only combined checks. `final-rehearsal-verification.json` records zero differences in the checked price, identity, snapshot, property-reference and branch-reference invariants. All 16 migration tests pass. This is a verified data rehearsal; remaining specialised/reference data, backend adapters, permission behavior, approval idempotency and workflow/file UAT still prevent an application cutover.

This is the third, isolated local database. It is not yet an application-ready replacement.
BS Units and Beloan still use their original databases. No production configuration is changed.

## Connection

DBeaver: MariaDB, host `127.0.0.1`, port `3309`, database `bsunits_beloan_final`, user `root`, password `local-only-unified` (local testing only).

```sh
docker compose -f tools/unified-db/compose.yaml up -d --wait
python3 tools/unified-db/stage-properties.py
python3 -m unittest discover -s tools/unified-db -p 'test_*.py'
```

The staging script reads projects, unit types and units from the two original local containers, preserving every column in source snapshots. It writes only to the new target. Re-runs create separate run IDs, never truncate old runs or modify original rows. Candidate decisions start PENDING. The property-domain canonical tables are now populated for rehearsal; further domain migrations, availability reconciliation and backend adapters are still required.

Source reads are separate statements, suitable for the current development audit. Freeze source writes or use consistent full snapshots for a production rehearsal/cutover. The current source dates differ; disagreement is not itself corruption.

Run `review.sql` in DBeaver to inspect candidates and source values. `outputs/unified-db/property-staging-result.json` records the latest successful load and reconciled counts. It contains aggregate information only.

Comparisons currently cover project English name/publication, selected unit-type fees/contractability, unit price/saleability, physical specifications and deletion timestamp. The raw snapshots also retain all other fields for later mapping. A unique code match does not imply all fields agree or authorize merging. Project matches still require organisation/branch identity verification.

Before promotion: review master-value policy, duplicate keys, customer/account/person identity, historical snapshots, missing relationships, source status mappings, financial reconciliation and permissions. Final DB engine/version must also be validated against the old Beloan framework and raw queries; MariaDB 10.11 here is a local workspace choice.

Do not repoint either app's `.env` at this database yet. Customer/request/sale/contract/finance tables and application writes are not migrated.

## Confirmed current-price policy

The user confirmed BS Units has the latest unit prices. `apply-price-policy.sql` records BS Units as the selected source for current list prices in unambiguous, BS Units-only and explicitly resolved duplicate groups. It preserves both source snapshots. Beloan-only raw unit prices are not overwritten; their canonical current list prices remain unset. Historical sale/contract/loan amounts are outside this policy.

Applied to the current run: 21,102 price selections, resolving 8,061 compared price-conflict groups. Reapplying produces no duplicate decisions. Canonical current-unit prices also match the latest BS Units raw snapshot with zero differences. The property rehearsal is populated; the full application schema is not ready yet.

The user also confirmed BS Units has the latest physical unit details. `apply-unit-detail-policy.sql` now selects its dimensions, floor, room counts, street/block information and construction progress: 20 fields for each of 21,102 groups (422,040 selections). Selected values are verified by exact JSON-value checksums, including nulls, empty strings and zero values. MariaDB's JSON/string equality semantics require checksum comparison rather than direct text-to-JSON equality. The five initially ambiguous identity groups were subsequently resolved under the unused-duplicate rule below. This policy does not alter availability/deletion state, customer information, agreed sale/contract/loan terms or accounting data.

To stop without deleting data:

```sh
docker compose -f tools/unified-db/compose.yaml stop
```

## Customer, sale, contract and loan groundwork

`stage-business.py` preserves exact JSON numeric text and checksums for Beloan customers/CBC records, sales/items, loan accounts, drawdowns and loans, plus BS Units holds/deposits/contract requests/contracts and attachment metadata. It does not copy uploaded files, repayment schedule rows or accounting journals yet. Each source table is staged in a transaction; failed runs cannot be promoted. Production use requires consistent frozen source snapshots.

Current successful business run: 227,454 source snapshots, verified against staged totals, with zero checksum mismatches. Source relationships are classified as MATCHED, EMPTY, MISSING or AMBIGUOUS. An empty legacy sale reference is not automatically corruption or grounds to delete a loan account.

`customer-candidates.py` creates review links between signed-contract buyer national IDs and Beloan National ID records. It keeps leading zeros/punctuation and does not match phone/name alone. DOB, gender and name agreement are supporting evidence only. All decisions remain PENDING. It found 4,234 candidate links across 4,150 buyer snapshots, with 3,363 links also supported by name, birthdate and gender. Forty-nine national-ID keys are shared by multiple Beloan accounts; these may be legitimate separate accounts and are not automatically merged. Missing IDs and unmatched buyers need an explicit policy; preserved signed buyer snapshots must never be rewritten.

Use `review-business.sql` in DBeaver. Aggregate reports are in `outputs/unified-db/business-staging-result.json` and `customer-candidate-result.json`. The original databases and existing financial amounts remain unchanged. Business snapshot runs are kept separate from property runs so current-price rules cannot accidentally operate on loan data.

## Full finance snapshot and reconciliation

The complete original Beloan database is now copied to `beloan_migration_stage` on the same isolated local instance (port 3309). This is raw staging for migration and audit, not a second operational database for the apps. It preserves all 135 base tables and two views, including schedules, accounting history, reference data and backups: **19,748,816 rows**, with zero table-count differences. The stream required no additional SQL archive. Source-qualified views are checked/rebound to staging if necessary; no view references the original database. Triggers and events are absent, and normal flush durability is restored.

`copy-finance-stage.py` refuses to overwrite an occupied staging schema. Do not rerun a full copy into that schema. `--verify-existing` is a verification/rebinding recovery path only after a completed import; it requires exact base-table counts before repairing views.

`reconcile-finance.py` compared original/staging row counts, non-null money counts and fixed-decimal aggregate baselines for loans, sales/items, loan/drawdown accounts, schedules, payments and journals. It also compared journal debit/credit counts and totals by account/year. All matched. This is an aggregate reconciliation, not a complete row-by-row proof; float-based sums are compared at eight decimal places without rewriting original data.

`verify-loan-prices.py` additionally checked each of the 16,311 existing loans' agreed unit price, final price and deposit amount (DECIMAL fields). All rows match exactly. The BS Units current-price rule remains confined to property migration selections.

Reports: `finance-stage-result.json`, `financial-reconciliation.json`, `loan-price-verification.json`, `table-domain-map.json`, and `application-dependency-inventory.json` under `outputs/unified-db/`. The finance data has not been promoted to canonical app-ready tables, and neither backend has been switched.

## Canonical property catalog populated

`copy-bsunits-stage.py` preserved all 66 BS Units base tables and 1,104,064 rows in a consistent raw staging snapshot. `promote-properties.py` then populated canonical organisations/projects/unit types/units in one transaction. It refuses a repeat promotion into occupied tables.

Current canonical counts: two organisations, 20 projects, 128 unit types and 21,475 units. Of the units, 21,102 use the current BS Units catalog price/details, and 373 exist only in Beloan. The latter are marked LEGACY_ONLY with null current list prices; their original details and loan/sale amounts remain preserved in raw staging. Availability is UNVERIFIED and action flags remain disabled pending workflow reconciliation.

Unique project/type/unit-code matches produce source aliases to one canonical unit, not duplicate master rows. Five duplicate-key groups (ten Beloan rows) were investigated: none had loan, sale-item, loan-account or drawdown references. `resolve-unused-unit-duplicates.sql` guards those same conditions before mapping both old IDs to the single BS Units counterpart. Original conflicting availability flags remain preserved, and availability is not inferred from these identity mappings. All 21,093 Beloan unit IDs now have canonical mappings. There are 42,476 property source mappings overall; zero unit mappings point to a missing target.

The source mapping preserves original IDs for audit and future foreign-reference migration. Accounting branches remain separate from current project ownership: notably SCC-D has different source company/branch associations. No accounting branch or financial ownership was silently reassigned.

Twelve focused migration tests pass. Canonical BS Units prices have zero differences, all source-unit aliases resolve, and all 16,311 existing loan price rows were rechecked unchanged. `property-promotion-result.json` records the initial promotion before duplicate reconciliation; its unresolved-ID list is historical and was subsequently resolved as described above.

`record-property-identity-decisions.sql` records the identity outcomes already represented by those mappings: 20,846 merged hierarchy/code groups and 777 source-only groups kept as separate identities. No property-identity decision remains pending in the initial catalog run. This does not resolve customer candidates, availability or accounting ownership. `property-reconciliation-result.json` contains the final verified property counts and mapping checks.
