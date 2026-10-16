"""Preserve BS Units operational history and immutable monetary snapshots."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

TABLES = ('unit_hold_requests','unit_deposit_requests','unit_contract_requests','contracts',
          'unit_contract_request_attachments','contract_attachments',
          'contract_requests','contract_request_attachments','contract_templates')

def run():
    for table in TABLES:
        if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+s.literal(table)+';'):
            raise RuntimeError('Request-history target exists; refusing to overwrite.')
    for table in TABLES:
        s.target('CREATE TABLE `'+table+'` LIKE bsunits_migration_stage.`'+table+'`;')
    s.target('START TRANSACTION;\n'+'\n'.join('INSERT INTO `'+t+'` SELECT * FROM bsunits_migration_stage.`'+t+'`;' for t in TABLES)+'\nCOMMIT;')
    result = {}
    for table in TABLES:
        cols = s.target("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bsunits_migration_stage' AND TABLE_NAME="+s.literal(table)+' ORDER BY ORDINAL_POSITION;')
        if 'id' not in cols or any(not col.replace('_','').isalnum() for col in cols):
            raise RuntimeError('Unexpected workflow source schema.')
        def checksum(alias):
            return 'SHA2(JSON_OBJECT('+','.join(s.literal(col)+','+alias+'.`'+col+'`' for col in cols)+'),256)'
        mismatch = int(s.target('SELECT COUNT(*) FROM `'+table+'` r JOIN bsunits_migration_stage.`'+table+'` o ON o.id=r.id WHERE '+checksum('r')+'<>'+checksum('o')+';')[0])
        count = int(s.target('SELECT COUNT(*) FROM `'+table+'`;')[0])
        expected = int(s.target('SELECT COUNT(*) FROM bsunits_migration_stage.`'+table+'`;')[0])
        result[table] = {'rows':count,'source_rows':expected,'row_checksum_mismatches':mismatch}
        if count!=expected or mismatch:
            raise RuntimeError('Request history reconciliation failed.')
    report = {'tables':result,'scope':'All stored values retained, including approval states, actors, monetary snapshots and media paths. Historical orphan references remain for review. No new sale created for historical approvals and no app connection changed.'}
    (s.ROOT/'outputs/unified-db/request-history-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))

if __name__ == '__main__':
    run()
