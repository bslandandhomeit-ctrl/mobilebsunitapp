"""Preserve financial auxiliary tables and map only shared property references."""
import importlib.util
import argparse
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

ALREADY = {'tb_client_loan_accounts','tb_drawdown_account','tb_loans',
 'tb_coa_categories','tb_journal_requiry','tb_journal_detail','tb_repayment_schedule',
 'tb_loan_payments','tb_transactions_posting','tb_transactions_requiry',
 'tb_till_account','tb_till_transaction','tb_till_transaction_reject'}
PROPERTY = {'unit_id':'tb_units','project_id':'tb_projects','unit_type_id':'tb_unit_types'}

def run(resume=False):
    manifest = json.loads((s.ROOT/'outputs/unified-db/table-domain-map.json').read_text())
    tables = [r['table'] for r in manifest['beloan'] if r['domain']=='financial_preserve_and_reconcile' and r['table'] not in ALREADY]
    report_path = s.ROOT/'outputs/unified-db/financial-auxiliary-result.json'
    previous = json.loads(report_path.read_text()).get('tables',{}) if resume and report_path.exists() else {}
    for source in tables:
        target = 'currencies' if source=='tb_currency' else source[3:]
        if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+s.literal(target)+';'):
            if not resume or target not in previous:
                raise RuntimeError('Unverified auxiliary target exists; refusing to overwrite.')
    s.target('''CREATE TABLE IF NOT EXISTS financial_auxiliary_source_references (
      entity_table VARCHAR(80) NOT NULL, source_id BIGINT NOT NULL,
      original_properties JSON NOT NULL, PRIMARY KEY(entity_table,source_id)
    ) ENGINE=InnoDB;''')
    report = {}
    for source in tables:
        target = 'currencies' if source=='tb_currency' else source[3:]
        existing = target in previous
        print(('Rechecking ' if existing else 'Copying ')+target,flush=True)
        cols = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME="+s.literal(source)+' ORDER BY ORDINAL_POSITION;')
        mapped = [col for col in PROPERTY if col in cols]
        if mapped and 'id' not in cols:
            raise RuntimeError('Property-bearing table requires an explicit source key.')
        if not existing:
            # Legacy MySQL definitions contain a duplicate empty enum label.
            # Non-strict mode applies only to this short-lived DDL connection.
            s.target("SET SESSION sql_mode='NO_ENGINE_SUBSTITUTION'; CREATE TABLE `"+target+'` LIKE beloan_migration_stage.`'+source+'`;')
        if mapped and not existing:
            s.target('ALTER TABLE `'+target+'` '+','.join('MODIFY `'+col+'` BIGINT UNSIGNED NULL' for col in mapped)+';')
        statements = ['START TRANSACTION;','INSERT INTO `'+target+'` SELECT * FROM beloan_migration_stage.`'+source+'`;']
        if mapped:
            properties = 'JSON_OBJECT('+','.join(s.literal(col)+',`'+col+'`' for col in mapped)+')'
            statements.append('INSERT INTO financial_auxiliary_source_references SELECT '+s.literal(target)+',id,'+properties+' FROM beloan_migration_stage.`'+source+'`;')
            for col in mapped:
                statements.append('UPDATE `'+target+"` r LEFT JOIN source_record_map m ON m.source_system='beloan' AND m.source_table="+s.literal(PROPERTY[col])+' AND m.source_id=(CAST(r.`'+col+'` AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci) SET r.`'+col+'`=m.target_id;')
        statements.append('COMMIT;')
        if not existing:
            s.target('\n'.join(statements))
        actual = int(s.target('SELECT COUNT(*) FROM `'+target+'`;')[0])
        expected = int(s.target('SELECT COUNT(*) FROM beloan_migration_stage.`'+source+'`;')[0])
        if mapped:
            unchanged = [col for col in cols if col not in mapped]
            def checksum(alias):
                return 'SHA2(JSON_OBJECT('+','.join(s.literal(col)+','+alias+'.`'+col+'`' for col in unchanged)+'),256)'
            mismatch = int(s.target('SELECT COUNT(*) FROM `'+target+'` r JOIN beloan_migration_stage.`'+source+'` o ON o.id=r.id WHERE '+checksum('r')+'<>'+checksum('o')+';')[0])
            verification = 'SHA256_ALL_UNCHANGED_FIELDS'
        else:
            checksums = [r.split('\t')[-1] for r in s.target('CHECKSUM TABLE beloan_migration_stage.`'+source+'`, `'+target+'` EXTENDED;')]
            mismatch = int(len(checksums)!=2 or 'NULL' in checksums or checksums[0]!=checksums[1])
            verification = 'EXTENDED_FULL_ROW_CHECKSUM'
        mapping_differences = 0
        for col in mapped:
            mapping_differences += int(s.target('SELECT COUNT(*) FROM `'+target+'` r JOIN beloan_migration_stage.`'+source+"` o ON o.id=r.id LEFT JOIN source_record_map m ON m.source_system='beloan' AND m.source_table="+s.literal(PROPERTY[col])+' AND m.source_id=(CAST(o.`'+col+'` AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci) WHERE NOT(r.`'+col+'` <=> m.target_id);')[0])
        if actual!=expected or mismatch or mapping_differences:
            raise RuntimeError('Auxiliary financial verification failed.')
        report[target] = {'rows':actual,'source_rows':expected,'verification':verification,'mismatches':mismatch,'property_mapping_differences':mapping_differences,'remapped_property_columns':mapped}
        (s.ROOT/'outputs/unified-db/financial-auxiliary-result.json').write_text(json.dumps({'tables':report,'status':'COPYING'},indent=2)+'\n')
        print(target+' verified: '+str(actual)+' rows',flush=True)
    result = {'tables':report,'status':'VERIFIED','scope':'Financial IDs/types/amounts unchanged; canonical property aliases applied with original-reference lineage. Original actor and lookup references retained. No postings, loan recalculation or app connection changes.'}
    (s.ROOT/'outputs/unified-db/financial-auxiliary-result.json').write_text(json.dumps(result,indent=2)+'\n')
    print('Financial auxiliary tables verified.',flush=True)

if __name__ == '__main__':
    parser = argparse.ArgumentParser()
    parser.add_argument('--resume-verified',action='store_true',help='Recheck manifest-verified tables, then continue missing tables; never overwrite an unverified target.')
    run(parser.parse_args().resume_verified)
