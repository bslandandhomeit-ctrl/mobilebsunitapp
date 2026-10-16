"""Local-only retry queue and least-privilege canonical availability writer."""
import importlib.util
from pathlib import Path
s=importlib.util.spec_from_file_location('stage',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
c,p,d,_=m.SOURCES['bsunits']
m.mysql(c,p,d,'''CREATE TABLE IF NOT EXISTS unified_unit_action_outbox (
 unit_id BIGINT UNSIGNED PRIMARY KEY,
 action_id BIGINT UNSIGNED NOT NULL,
 queued_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;''')
m.target('''CREATE USER IF NOT EXISTS 'unified_unit_action_writer'@'%' IDENTIFIED BY 'local-only-unified-action-writer';
GRANT SELECT ON bsunits_beloan_final.units TO 'unified_unit_action_writer'@'%';
GRANT UPDATE (current_action_id,availability_state,is_saleable) ON bsunits_beloan_final.units TO 'unified_unit_action_writer'@'%';
GRANT SELECT,INSERT ON bsunits_beloan_final.unit_actions TO 'unified_unit_action_writer'@'%';''')
print('Local outbox created; writer limited to canonical availability columns and action insertion.')
