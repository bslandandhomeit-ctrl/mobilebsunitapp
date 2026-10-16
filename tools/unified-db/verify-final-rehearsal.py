"""Read-only reconciliation gate for the populated local migration rehearsal."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

CHECKS = {
 'current_unit_price_differences':"SELECT COUNT(*) FROM units u JOIN bsunits_migration_stage.units b ON b.id=u.id WHERE u.catalog_state='CURRENT' AND NOT(u.list_price <=> b.price);",
 'available_without_bs_available_action':"SELECT COUNT(*) FROM units u LEFT JOIN bsunits_migration_stage.units b ON b.id=u.id LEFT JOIN bsunits_migration_stage.unit_actions a ON a.id=b.unit_action_id AND a.unit_id=b.id WHERE u.availability_state='AVAILABLE' AND (a.id IS NULL OR a.action<>'AVAILABLE' OR b.saleable<>1 OR b.deleted_at IS NOT NULL);",
 'unmapped_beloan_units':"SELECT COUNT(*) FROM beloan_migration_stage.tb_units b LEFT JOIN source_record_map m ON m.source_system='beloan' AND m.source_table='tb_units' AND m.source_id=(CAST(b.id AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci) WHERE m.target_id IS NULL;",
 'dangling_person_aliases':'SELECT COUNT(*) FROM person_source_map m LEFT JOIN persons p ON p.id=m.person_id WHERE p.id IS NULL;',
 'missing_customer_accounts':'SELECT COUNT(*) FROM beloan_migration_stage.tb_clients b LEFT JOIN customer_accounts a ON a.source_id=b.id AND a.source_system=\'beloan\' WHERE a.id IS NULL;',
 'buyer_snapshot_checksum_differences':'SELECT COUNT(*) FROM contract_buyer_snapshots WHERE snapshot_checksum<>SHA2(buyer_snapshot,256);',
 'customer_evidence_checksum_differences':'SELECT COUNT(*) FROM customer_account_evidence WHERE source_checksum<>SHA2(source_payload,256);',
 'verified_buyers_without_person':'SELECT COUNT(*) FROM contract_buyer_snapshots b LEFT JOIN persons p ON p.id=b.person_id WHERE b.link_state=\'VERIFIED\' AND p.id IS NULL;',
 'changed_historical_loan_prices':'SELECT COUNT(*) FROM loans l JOIN beloan_migration_stage.tb_loans b ON b.id=l.id WHERE NOT(l.unit_sale_price <=> b.unit_sale_price) OR NOT(l.final_price <=> b.final_price) OR NOT(l.diposit_amount <=> b.diposit_amount);',
 'broken_sale_item_units':'SELECT COUNT(*) FROM sale_items i LEFT JOIN units u ON u.id=i.unit_id WHERE u.id IS NULL;',
 'unmapped_accounting_projects':"SELECT COUNT(*) FROM beloan_migration_stage.tb_projects p LEFT JOIN project_accounting_branch_links l ON l.source_system='beloan' AND l.source_project_id=p.id WHERE l.project_id IS NULL;",
 'dangling_actor_aliases':'SELECT COUNT(*) FROM actor_source_map m LEFT JOIN actors a ON a.id=m.actor_id WHERE a.id IS NULL;',
}

def run():
    checks = {name:int(s.target(sql)[0]) for name,sql in CHECKS.items()}
    counts = {table:int(s.target('SELECT COUNT(*) FROM `'+table+'`;')[0]) for table in (
        'units','customer_accounts','persons','customer_account_members','customer_account_evidence',
        'contract_buyer_snapshots','sales','sale_items','loan_accounts','drawdown_accounts','loans','contracts',
        'branches','project_accounting_branch_links','actors','login_identities','access_roles','access_permissions')}
    availability = {row.split('\t')[0]:int(row.split('\t')[1]) for row in s.target('SELECT availability_state,COUNT(*) FROM units GROUP BY availability_state;')}
    ledger = json.loads((s.ROOT/'outputs/unified-db/ledger-history-result.json').read_text())
    if ledger.get('status')!='VERIFIED':
        raise RuntimeError('Ledger copy has not finished verification.')
    auxiliary = json.loads((s.ROOT/'outputs/unified-db/financial-auxiliary-result.json').read_text())
    if auxiliary.get('status')!='VERIFIED':
        raise RuntimeError('Auxiliary financial copy has not finished verification.')
    result = {'status':'REHEARSAL_CHECKS_PASSED' if not any(checks.values()) else 'REVIEW_REQUIRED',
        'counts':counts,'availability':availability,'checks':checks,
        'ledger_verified':True,'financial_auxiliary_verified':True,'application_cutover_ready':False,
        'remaining_cutover_gates':['Remaining specialised/reference data and currency adapters','Historical actor/reference exceptions and permission behavior','Backend model/query/write adapters','Approval-to-sale idempotency','Attachment file verification and workflow UAT'],
        'scope':'Local only. Original databases retained; no application connection or production deployment changed.'}
    (s.ROOT/'outputs/unified-db/final-rehearsal-verification.json').write_text(json.dumps(result,indent=2)+'\n')
    print(json.dumps(result,indent=2))
    if any(checks.values()):
        raise RuntimeError('Final rehearsal reconciliation failed.')

if __name__ == '__main__':
    run()
