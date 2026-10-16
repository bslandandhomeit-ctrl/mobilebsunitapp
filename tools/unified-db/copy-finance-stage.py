"""Copy the entire frozen local Beloan snapshot into isolated raw staging.

Streaming dump: no extra SQL archive, no source writes, no trigger/event activation.
Staging is deliberately separate from canonical bsunits_beloan_final tables.
"""
import datetime
import importlib.util
import json
import pathlib
import subprocess
import sys
import uuid

HERE = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)
RAW_DB = 'beloan_migration_stage'

def target(sql,database=RAW_DB):
    return staging.mysql(staging.TARGET_CONTAINER,'local-only-unified',database,sql)

def main():
    source,password,database,_ = staging.SOURCES['beloan']
    metadata = staging.mysql(source,password,database,
        "SELECT TABLE_NAME,TABLE_TYPE FROM information_schema.TABLES "
        "WHERE TABLE_SCHEMA='land_home_v2' ORDER BY TABLE_NAME;")
    tables = [line.split('\t')[0] for line in metadata if line.endswith('\tBASE TABLE')]
    views = [line.split('\t')[0] for line in metadata if line.endswith('\tVIEW')]
    staging.target("CREATE DATABASE IF NOT EXISTS beloan_migration_stage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
    occupied = target("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='beloan_migration_stage';")
    if occupied != ['0']:
        raise RuntimeError('Raw staging already contains tables. Refusing to overwrite it.')
    expected = {}
    for table in tables:
        expected[table] = int(staging.mysql(source,password,database,'SELECT COUNT(*) FROM `'+table+'`;')[0])
    started = datetime.datetime.now(datetime.timezone.utc).isoformat()
    run_id = str(uuid.uuid4())
    report = {'run_id':run_id,'status':'COPYING','source':database,'staging':RAW_DB,
        'started_at':started,'expected_counts':expected,'expected_views':views,
        'scope':'Raw migration staging; canonical business records and app connections are unchanged.'}
    output = staging.ROOT/'outputs'/'unified-db'/'finance-stage-result.json'
    output.write_text(json.dumps(report,indent=2))
    print('Streaming '+str(sum(expected.values()))+' rows across '+str(len(tables))+' tables.',flush=True)
    dump_command = ['docker','exec','-e','MYSQL_PWD='+password,source,'mysqldump','-uroot',
        '--default-character-set=utf8mb4','--single-transaction','--quick','--hex-blob',
        '--skip-triggers','--skip-add-locks','--no-tablespaces','--set-gtid-purged=OFF',database]
    restore_command = ['docker','exec','-i','-e','MYSQL_PWD=local-only-unified',staging.TARGET_CONTAINER,
        'mysql','-uroot','--default-character-set=utf8mb4',RAW_DB]
    # Errors may quote financial/customer fields. Retain no raw SQL or stderr in reports.
    with open('/dev/null','wb') as private_errors:
        dump = subprocess.Popen(dump_command,stdout=subprocess.PIPE,stderr=private_errors)
        restore = subprocess.Popen(restore_command,stdin=dump.stdout,stdout=subprocess.DEVNULL,stderr=private_errors)
        dump.stdout.close()
        restore_code = restore.wait()
        dump_code = dump.wait()
    if dump_code:
        report['status'] = 'FAILED'
        report['dump_exit_code'] = dump_code
        report['restore_exit_code'] = restore_code
        output.write_text(json.dumps(report,indent=2))
        raise RuntimeError('Streaming copy failed; partial staging retained, no source data changed.')
    # MySQL view definitions may qualify the original DB name. Rebuild only the
    # two source-declared views inside staging, even if that qualification caused
    # mysql to stop at the final view-creation section. Every base table and its
    # exact row count must still pass validation below.
    for view in views:
        definitions = staging.mysql(source,password,database,
            "SELECT VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_SCHEMA='land_home_v2' AND TABLE_NAME="
            +staging.literal(view)+';')
        definition = '\n'.join(definitions).replace('`land_home_v2`','`beloan_migration_stage`')
        existing = target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME="
                          +staging.literal(view)+';')
        if existing == ['BASE TABLE']:
            target('DROP TABLE `'+view+'`;')  # mysqldump's temporary view placeholder only
        target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW `'+view+'` AS '+definition+';')
    report['dump_exit_code'] = dump_code
    report['restore_exit_code'] = restore_code
    report['view_definitions_rebound_to_staging'] = views
    print('Copy complete. Verifying exact table counts.',flush=True)
    actual = {t:int(target('SELECT COUNT(*) FROM `'+t+'`;')[0]) for t in tables}
    mismatches = {t:{'expected':expected[t],'actual':actual[t]} for t in tables if actual[t]!=expected[t]}
    view_checks = [target('CHECK TABLE `'+v+'`;') for v in views]
    view_ok = all(any(row.endswith('\tstatus\tOK') for row in result) for result in view_checks)
    report.update({'status':'VERIFIED' if not mismatches and view_ok else 'FAILED',
        'actual_counts':actual,'count_mismatches':mismatches,'views_valid':view_ok,
        'verified_rows':sum(actual.values()),'completed_at':datetime.datetime.now(datetime.timezone.utc).isoformat()})
    output.write_text(json.dumps(report,indent=2))
    target('SET GLOBAL innodb_flush_log_at_trx_commit=1; FLUSH LOGS;')
    if mismatches or not view_ok:
        raise RuntimeError('Raw staging verification failed; inspect aggregate report.')
    print('Verified '+str(sum(actual.values()))+' rows; all table counts and views match.',flush=True)

def verify_existing():
    """Resume verification after a completed import with source-qualified views."""
    output = staging.ROOT/'outputs'/'unified-db'/'finance-stage-result.json'
    report = json.loads(output.read_text())
    if report.get('status') == 'COPYING':
        raise RuntimeError('Copy is still running; do not verify or repair its views concurrently.')
    source,password,database,_ = staging.SOURCES['beloan']
    expected = report['expected_counts']
    actual = {t:int(target('SELECT COUNT(*) FROM `'+t+'`;')[0]) for t in expected}
    if actual != expected:
        raise RuntimeError('Base-table counts differ; refusing view repair and promotion.')
    for view in report['expected_views']:
        definition = '\n'.join(staging.mysql(source,password,database,
            "SELECT VIEW_DEFINITION FROM information_schema.VIEWS WHERE TABLE_SCHEMA='land_home_v2' AND TABLE_NAME="
            +staging.literal(view)+';')).replace('`land_home_v2`','`beloan_migration_stage`')
        existing = target("SELECT TABLE_TYPE FROM information_schema.TABLES WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME="
                          +staging.literal(view)+';')
        if existing == ['BASE TABLE']:
            target('DROP TABLE `'+view+'`;')
        target('CREATE OR REPLACE SQL SECURITY INVOKER VIEW `'+view+'` AS '+definition+';')
    checks = [target('CHECK TABLE `'+v+'`;') for v in report['expected_views']]
    if not all(any(row.endswith('\tstatus\tOK') for row in result) for result in checks):
        raise RuntimeError('Rebound views failed validation.')
    report.update({'status':'VERIFIED','actual_counts':actual,'count_mismatches':{},
        'views_valid':True,'view_definitions_rebound_to_staging':report['expected_views'],
        'verified_rows':sum(actual.values()),'completed_at':datetime.datetime.now(datetime.timezone.utc).isoformat()})
    output.write_text(json.dumps(report,indent=2))
    target('SET GLOBAL innodb_flush_log_at_trx_commit=1; FLUSH LOGS;')
    print('Verified '+str(sum(actual.values()))+' rows and rebound staging views.',flush=True)

if __name__ == '__main__':
    verify_existing() if '--verify-existing' in sys.argv else main()
