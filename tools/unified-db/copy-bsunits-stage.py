"""Freeze the complete local BS Units DB into isolated migration staging."""
import importlib.util
import json
import pathlib
import subprocess

HERE = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)
RAW_DB = 'bsunits_migration_stage'

def main():
    source,password,database,_ = staging.SOURCES['bsunits']
    staging.target('CREATE DATABASE IF NOT EXISTS bsunits_migration_stage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    existing = staging.mysql(staging.TARGET_CONTAINER,'local-only-unified',RAW_DB,
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='bsunits_migration_stage';")
    if existing != ['0']:
        raise RuntimeError('BS Units raw staging is occupied; refusing overwrite.')
    tables = staging.mysql(source,password,database,
        "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='bsunits_backup_20261005' "
        "AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME;")
    expected = {table:int(staging.mysql(source,password,database,'SELECT COUNT(*) FROM `'+table+'`;')[0]) for table in tables}
    manifest = {'source':database,'staging':RAW_DB,'status':'COPYING','expected_counts':expected}
    output = staging.ROOT/'outputs'/'unified-db'/'bsunits-stage-result.json'
    output.write_text(json.dumps(manifest,indent=2))
    dump_command = ['docker','exec','-e','MYSQL_PWD='+password,source,'mysqldump','-uroot',
        '--single-transaction','--quick','--hex-blob','--skip-triggers','--skip-add-locks',
        '--no-tablespaces',database]
    restore_command = ['docker','exec','-i','-e','MYSQL_PWD=local-only-unified',
        staging.TARGET_CONTAINER,'mysql','-uroot','--default-character-set=utf8mb4',RAW_DB]
    with open('/dev/null','wb') as private_errors:
        dump = subprocess.Popen(dump_command,stdout=subprocess.PIPE,stderr=private_errors)
        restore = subprocess.Popen(restore_command,stdin=dump.stdout,stdout=subprocess.DEVNULL,stderr=private_errors)
        dump.stdout.close()
        restore_code = restore.wait()
        dump_code = dump.wait()
    if dump_code or restore_code:
        manifest.update({'status':'FAILED','dump_exit_code':dump_code,'restore_exit_code':restore_code})
        output.write_text(json.dumps(manifest,indent=2))
        raise RuntimeError('BS Units staging copy failed; source untouched, partial staging retained.')
    actual = {table:int(staging.mysql(staging.TARGET_CONTAINER,'local-only-unified',RAW_DB,
        'SELECT COUNT(*) FROM `'+table+'`;')[0]) for table in tables}
    mismatches = {table:{'expected':expected[table],'actual':actual[table]} for table in tables if actual[table]!=expected[table]}
    manifest.update({'status':'VERIFIED' if not mismatches else 'FAILED','actual_counts':actual,
                     'mismatches':mismatches,'verified_rows':sum(actual.values())})
    output.write_text(json.dumps(manifest,indent=2))
    if mismatches:
        raise RuntimeError('BS Units staging count mismatch; do not promote.')
    print('Verified BS Units staging: '+str(len(tables))+' tables, '+str(sum(actual.values()))+' rows.')

if __name__ == '__main__':
    main()
