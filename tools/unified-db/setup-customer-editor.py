import importlib.util
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
m.target('''CREATE TABLE IF NOT EXISTS customer_account_change_audit (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,account_id BIGINT UNSIGNED NOT NULL,actor_id BIGINT UNSIGNED NOT NULL,before_snapshot LONGTEXT NOT NULL,after_snapshot LONGTEXT NOT NULL,created_at DATETIME NOT NULL) ENGINE=InnoDB;
CREATE OR REPLACE VIEW beloan_actor_aliases AS SELECT source_user_id,actor_id FROM actor_source_map WHERE source_system='beloan';
CREATE USER IF NOT EXISTS 'unified_beloan_customer_writer'@'%' IDENTIFIED BY 'local-only-unified-customer-writer';
GRANT SELECT ON bsunits_beloan_final.customer_accounts TO 'unified_beloan_customer_writer'@'%';
GRANT UPDATE(display_name) ON bsunits_beloan_final.customer_accounts TO 'unified_beloan_customer_writer'@'%';
GRANT SELECT,INSERT,UPDATE ON bsunits_beloan_final.customer_account_evidence TO 'unified_beloan_customer_writer'@'%';
GRANT SELECT ON bsunits_beloan_final.beloan_actor_aliases TO 'unified_beloan_customer_writer'@'%';
GRANT INSERT ON bsunits_beloan_final.customer_account_change_audit TO 'unified_beloan_customer_writer'@'%';''')
print('Audited local account/contact writer prepared; person and financial updates not granted.')
