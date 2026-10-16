import importlib.util
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
m.target('''CREATE TABLE IF NOT EXISTS unified_id_allocators (entity VARCHAR(40) PRIMARY KEY,next_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB;
INSERT IGNORE INTO unified_id_allocators SELECT 'customer_accounts',COALESCE(MAX(id),0)+1 FROM customer_accounts;
INSERT IGNORE INTO unified_id_allocators SELECT 'persons',COALESCE(MAX(id),0)+1 FROM persons;
CREATE TABLE IF NOT EXISTS approved_deposit_conversion_keys (source_system VARCHAR(20) NOT NULL,deposit_request_id BIGINT UNSIGNED NOT NULL,PRIMARY KEY(source_system,deposit_request_id)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS approved_deposit_sale_terms (sale_id BIGINT UNSIGNED PRIMARY KEY,terms_snapshot JSON NOT NULL) ENGINE=InnoDB;
CREATE OR REPLACE VIEW bsunits_actor_aliases AS SELECT source_user_id,actor_id FROM actor_source_map WHERE source_system='bsunits';
CREATE USER IF NOT EXISTS 'unified_deposit_writer'@'%' IDENTIFIED BY 'local-only-unified-deposit-writer';''')
for t in ['customer_accounts','persons','customer_account_members','customer_account_evidence','sales','sale_items','approved_deposit_conversion_keys','approved_deposit_sale_terms','approved_deposit_sale_links']:
 m.target(f"GRANT SELECT,INSERT ON bsunits_beloan_final.{t} TO 'unified_deposit_writer'@'%'")
for t in ['units','unit_types','project_accounting_branch_links','currencies','bsunits_actor_aliases']:
 m.target(f"GRANT SELECT ON bsunits_beloan_final.{t} TO 'unified_deposit_writer'@'%'")
m.target("GRANT SELECT,UPDATE ON bsunits_beloan_final.unified_id_allocators TO 'unified_deposit_writer'@'%'")
c,p,d,_=m.SOURCES['bsunits'];m.mysql(c,p,d,'''CREATE TABLE IF NOT EXISTS unified_approved_deposit_outbox (
 deposit_request_id BIGINT UNSIGNED PRIMARY KEY,
 request_snapshot LONGTEXT NOT NULL,
 snapshot_sha256 CHAR(64) NOT NULL,
 queued_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;''')
print('Local conversion prerequisites and least-privilege writer installed; no business records created.')
