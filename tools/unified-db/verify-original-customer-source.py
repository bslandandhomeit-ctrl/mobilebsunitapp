import importlib.util,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
raw=['clients','client_cbc_general','client_cbc_address','client_cbc_contact','client_cbc_identification','client_cbc_employer','client_cbc_spouse']
report={}
for t in raw:
 target='beloan_ui_'+('customer_profiles' if t=='clients' else t)
 cols=[r.split('\t')[0] for r in m.target('SHOW COLUMNS FROM '+target)]
 expr="CRC32(CONCAT_WS(CHAR(31),"+','.join("COALESCE(CAST(`"+c+"` AS CHAR),CHAR(30))" for c in cols)+"))"
 select='SELECT COUNT(*),COALESCE(SUM('+expr+'),0) FROM '
 current=m.mysql(*m.SOURCES['beloan'][:3],select+'tb_'+t)[0].split('\t')
 final=m.target(select+target)[0].split('\t')
 report[t]={'original_rows':int(current[0]),'unified_profile_rows':int(final[0]),'original_and_profile_values_match':current==final}
report['status']='PASSED' if all(x['original_and_profile_values_match'] for x in report.values())else'REVIEW_SOURCE_DELTA'
Path('outputs/unified-db/original-customer-profile-reconciliation.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
