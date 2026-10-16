import importlib.util
from pathlib import Path
s=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
m.target('''CREATE TABLE IF NOT EXISTS approved_deposit_sale_amendments (
 deposit_request_id BIGINT UNSIGNED PRIMARY KEY,predecessor_request_id BIGINT UNSIGNED NOT NULL,
 sale_id BIGINT UNSIGNED NOT NULL,before_snapshot LONGTEXT NOT NULL,after_snapshot LONGTEXT NOT NULL,
 snapshot_sha256 CHAR(64) NOT NULL,created_at DATETIME NOT NULL,
 UNIQUE KEY predecessor_once(predecessor_request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci''')
print('Local amendment audit schema installed; no runtime switch or writer grants changed.')
