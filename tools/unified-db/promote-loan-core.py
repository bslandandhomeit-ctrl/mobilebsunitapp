"""Preserve loan core rows and monetary types; remap shared property references."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

TABLES = {'loan_accounts':'tb_client_loan_accounts','drawdown_accounts':'tb_drawdown_account','loans':'tb_loans'}
PROPERTY = {'unit_id':'tb_units','project_id':'tb_projects','unit_type_id':'tb_unit_types'}

def columns(source):
    return s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME="+s.literal(source)+' ORDER BY ORDINAL_POSITION;')

def run():
    for target in TABLES:
        if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+s.literal(target)+';'):
            raise RuntimeError('Loan target exists; refusing to overwrite.')
    s.target('''CREATE TABLE loan_core_source_references (
      entity_table VARCHAR(40) NOT NULL, source_id BIGINT UNSIGNED NOT NULL,
      original_references JSON NOT NULL,
      PRIMARY KEY(entity_table,source_id)
    ) ENGINE=InnoDB;''')
    for target,source in TABLES.items():
        rename = ', CHANGE contract_id legacy_contract_number VARCHAR(100) NULL' if target=='loans' else ''
        s.target('CREATE TABLE '+target+' LIKE beloan_migration_stage.'+source+'; ALTER TABLE '+target+' CHANGE client_id customer_account_id BIGINT NULL, MODIFY unit_id BIGINT UNSIGNED NULL, MODIFY project_id BIGINT UNSIGNED NULL, MODIFY unit_type_id BIGINT UNSIGNED NULL'+rename+';')
    statements = ['START TRANSACTION;']
    for target,source in TABLES.items():
        statements.append('INSERT INTO '+target+' SELECT * FROM beloan_migration_stage.'+source+';')
        refs = [col for col in columns(source) if col.endswith('_id') or col in ('drawdown_acc','sub_client_id')]
        expression = 'JSON_OBJECT('+','.join(s.literal(col)+',`'+col+'`' for col in refs)+')'
        statements.append('INSERT INTO loan_core_source_references SELECT '+s.literal(target)+',id,'+expression+' FROM beloan_migration_stage.'+source+';')
        for col,master in PROPERTY.items():
            # Unresolved/empty legacy refs stay NULL, with originals retained above.
            statements.append('UPDATE '+target+" r LEFT JOIN source_record_map m ON m.source_system='beloan' AND m.source_table="+s.literal(master)+' AND m.source_id=(CAST(r.'+col+' AS CHAR) COLLATE utf8mb4_unicode_ci) SET r.'+col+'=m.target_id;')
    statements.append('COMMIT;')
    s.target('\n'.join(statements))
    report = {}
    for target,source in TABLES.items():
        unchanged = [col for col in columns(source) if col not in PROPERTY]
        def expression(alias):
            parts = []
            for col in unchanged:
                mapped = ('customer_account_id' if col=='client_id' else 'legacy_contract_number' if col=='contract_id' and target=='loans' else col) if alias=='r' else col
                parts.append(s.literal(col)+','+alias+'.`'+mapped+'`')
            return 'SHA2(JSON_OBJECT('+','.join(parts)+'),256)'
        mismatch = int(s.target('SELECT COUNT(*) FROM '+target+' r JOIN beloan_migration_stage.'+source+' o ON o.id=r.id WHERE '+expression('r')+'<>'+expression('o')+';')[0])
        actual = int(s.target('SELECT COUNT(*) FROM '+target+';')[0])
        expected = int(s.target('SELECT COUNT(*) FROM beloan_migration_stage.'+source+';')[0])
        unresolved = {col:int(s.target('SELECT COUNT(*) FROM '+target+' WHERE '+col+' IS NULL;')[0]) for col in PROPERTY}
        report[target] = {'rows':actual,'source_rows':expected,'unchanged_field_checksum_mismatches':mismatch,'null_canonical_property_references':unresolved}
        if mismatch or actual!=expected:
            raise RuntimeError('Loan core reconciliation failed.')
    result = {'tables':report,'scope':'Original financial types, amounts, schedules references, accounting IDs and contract-number strings preserved. Empty/unmatched property references retained in lineage and represented as NULL. No schedule generation or posting executed; finance domain is not yet application-ready.'}
    (s.ROOT/'outputs/unified-db/loan-core-promotion-result.json').write_text(json.dumps(result,indent=2)+'\n')
    print(json.dumps(result,indent=2))

if __name__ == '__main__':
    run()
