"""Branches are independent records; a name match never assigns an organisation."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

def run():
    if int(s.target('SELECT COUNT(*) FROM branches;')[0]):
        raise RuntimeError('Branch target occupied; refusing to overwrite.')
    s.target('ALTER TABLE branches MODIFY organisation_id BIGINT UNSIGNED NULL, ADD COLUMN IF NOT EXISTS attributes JSON NULL, ADD COLUMN IF NOT EXISTS organisation_mapping_state VARCHAR(30) NOT NULL DEFAULT \'UNASSIGNED\';')
    cols = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='beloan_migration_stage' AND TABLE_NAME='tb_company_branch' ORDER BY ORDINAL_POSITION;")
    payload = 'JSON_OBJECT('+','.join(s.literal(col)+',`'+col+'`' for col in cols)+')'
    s.target('START TRANSACTION; INSERT INTO branches(id,organisation_id,code,name,attributes) SELECT id,NULL,branch_code,COALESCE(branch_name_en,branch_name),'+payload+' FROM beloan_migration_stage.tb_company_branch; COMMIT;')
    actual = int(s.target('SELECT COUNT(*) FROM branches;')[0])
    expected = int(s.target('SELECT COUNT(*) FROM beloan_migration_stage.tb_company_branch;')[0])
    financial = {}
    for table in ('loans','sale_items'):
        financial[table] = int(s.target('SELECT COUNT(*) FROM '+table+' f LEFT JOIN branches b ON b.id=f.company_branch_id WHERE f.company_branch_id IS NOT NULL AND f.company_branch_id<>0 AND b.id IS NULL;')[0])
    if actual!=expected or any(financial.values()):
        raise RuntimeError('Accounting branch source coverage failed.')
    report = {'branches':actual,'organisation_assignments':0,'missing_financial_branches':financial,'scope':'Independent branches, supporting future additions. Original branch IDs and attributes preserved. No name-based organisation merge or historical ownership reassignment.'}
    (s.ROOT/'outputs/unified-db/accounting-branch-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))

if __name__ == '__main__':
    run()
