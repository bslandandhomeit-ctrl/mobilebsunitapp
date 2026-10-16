"""Check the small set of shared references used by original finance workflows."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging', HERE / 'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)


def run():
    reports = {}
    for target, source in [('loans','tb_loans'), ('drawdown_accounts','tb_drawdown_account'), ('loan_accounts','tb_client_loan_accounts')]:
        fields = {}
        for field, original_master, canonical_master in [('unit_id','tb_units','units'), ('project_id','tb_projects','projects'), ('unit_type_id','tb_unit_types','unit_types')]:
            sql = ('SELECT SUM(NOT(n.`' + field + '` <=> m.target_id)),SUM(n.`' + field + '` IS NOT NULL AND c.id IS NULL),SUM(m.target_id IS NULL AND o.`' + field + '` IS NOT NULL AND CAST(o.`' + field + '` AS CHAR) NOT IN (\'\',\'0\'))'
                   ' FROM `' + target + '` n JOIN beloan_migration_stage.`' + source + '` o ON o.id=n.id'
                   " LEFT JOIN source_record_map m ON m.source_system='beloan' AND m.source_table=" + s.literal(original_master) + ' AND m.source_id=(CAST(o.`' + field + '` AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci)'
                   ' LEFT JOIN `' + canonical_master + '` c ON c.id=n.`' + field + '`;')
            values = [0 if v == 'NULL' else int(v) for v in s.target(sql)[0].split('\t')]
            fields[field] = dict(zip(['mapping_differences','dangling_canonical_references','unresolved_original_nonzero_references'], values))
        account = s.target('SELECT COUNT(*) FROM `' + target + '` n LEFT JOIN customer_accounts a ON a.id=n.customer_account_id WHERE n.customer_account_id IS NOT NULL AND n.customer_account_id<>0 AND a.id IS NULL;')[0]
        fields['customer_account_id'] = {'dangling_canonical_references': int(account)}
        branch = 'company_branch_id' if target == 'loans' else 'branch'
        values = s.target('SELECT SUM(NOT(n.`' + branch + '` <=> o.`' + branch + '`)),SUM(CAST(n.`' + branch + '` AS CHAR) NOT IN (\'\',\'0\') AND b.id IS NULL) FROM `' + target + '` n JOIN beloan_migration_stage.`' + source + '` o ON o.id=n.id LEFT JOIN branches b ON b.id=n.`' + branch + '`;')[0].split('\t')
        fields[branch] = {'changed_original_branch_references': 0 if values[0]=='NULL' else int(values[0]), 'unresolved_original_branches': 0 if values[1]=='NULL' else int(values[1])}
        reports[target] = fields
    failures = sum(v.get('mapping_differences',0)+v.get('dangling_canonical_references',0)+v.get('changed_original_branch_references',0) for fields in reports.values() for v in fields.values())
    report = {'status':'REFERENCE_REVIEW_REQUIRED' if failures else 'MAPPED_REFERENCES_VERIFIED','tables':reports,
              'limits':'Unresolved legacy nonzero references and branches are reported separately and need workflow-specific handling before cutover. No finance logic, records or connections changed. Customer accounts remain separate from deduplicated persons.'}
    (s.ROOT/'outputs/unified-db/shared-finance-reference-verification.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))


if __name__ == '__main__':
    run()
