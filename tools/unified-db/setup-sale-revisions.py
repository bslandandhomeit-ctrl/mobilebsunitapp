import importlib.util
from pathlib import Path
spec=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
m.target('''CREATE TABLE IF NOT EXISTS approved_deposit_sale_revisions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,sale_id BIGINT UNSIGNED NOT NULL,
 deposit_request_id BIGINT UNSIGNED NOT NULL,before_snapshot LONGTEXT NOT NULL,
 after_snapshot LONGTEXT NOT NULL,before_sha256 CHAR(64) NOT NULL,after_sha256 CHAR(64) NOT NULL,
 approved_actor_id BIGINT UNSIGNED NOT NULL,created_at DATETIME NOT NULL,
 UNIQUE KEY revision_once(sale_id,after_sha256)
) ENGINE=InnoDB''')
print('Local revision audit schema prepared; no business records created or writer permissions changed.')
