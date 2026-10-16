"""Preserve BS historical notification rows locally without overwriting any newer rows."""
import importlib.util,json
from pathlib import Path
HERE=Path(__file__).resolve().parent
spec=importlib.util.spec_from_file_location('stage',HERE/'stage-properties.py');m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m)
cols=m.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bsunits_migration_stage' AND TABLE_NAME='notifications' ORDER BY ORDINAL_POSITION")
diff=' OR '.join('NOT(BINARY t.`'+c+'` <=> BINARY s.`'+c+'`)' for c in cols)
check=lambda:int(m.target('SELECT COUNT(*) FROM unified_request_notifications t JOIN bsunits_migration_stage.notifications s ON s.id=t.id WHERE '+diff)[0])
if check():raise RuntimeError('Existing notification versions differ; refusing overwrite')
m.target('INSERT INTO unified_request_notifications SELECT s.* FROM bsunits_migration_stage.notifications s LEFT JOIN unified_request_notifications t ON t.id=s.id WHERE t.id IS NULL')
missing=int(m.target('SELECT COUNT(*) FROM bsunits_migration_stage.notifications s LEFT JOIN unified_request_notifications t ON t.id=s.id WHERE t.id IS NULL')[0])
if missing or check():raise RuntimeError('Notification history verification failed')
USER='unified_notification_reader';m.target("CREATE USER IF NOT EXISTS '"+USER+"'@'%' IDENTIFIED BY 'local-only-unified-notification-reader';GRANT SELECT,UPDATE(read_at,updated_at) ON bsunits_beloan_final.unified_request_notifications TO '"+USER+"'@'%'")
r={'status':'PASSED','local_only':True,'source_rows':int(m.target('SELECT COUNT(*) FROM bsunits_migration_stage.notifications')[0]),'missing_rows':missing,'all_column_mismatches':check(),'existing_rows_overwritten':False,'ui_flag_enabled':False}
(HERE.parents[1]/'outputs/unified-db/notification-history-preservation-result.json').write_text(json.dumps(r,indent=2)+'\n');print(json.dumps(r,indent=2))
