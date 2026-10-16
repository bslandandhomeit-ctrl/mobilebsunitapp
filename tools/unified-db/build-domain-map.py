"""Classify all source tables for migration planning; never drops source tables."""
import collections
import json
import pathlib

ROOT = pathlib.Path(__file__).resolve().parents[2]
audit = json.loads((ROOT/'outputs'/'unified-db'/'source-audit.json').read_text())
SHARED = {'companies','company_branch','projects','project','unit_types','units','image_types',
          'clients','client_cbc_general','client_cbc_identification','client_cbc_address',
          'client_cbc_contact','client_cbc_employer','client_cbc_spouse',
          'banks','payment_options','promotions','unit_type_promotions','sale_representatives'}
SECURITY = {'users','roles','permission','permissions','user_permission','role_has_permissions',
            'model_has_roles','model_has_permissions','accessible','admin_password','failed_logins',
            'password_resets','oauth_clients','oauth_access_tokens','oauth_refresh_tokens',
            'oauth_auth_codes','oauth_personal_access_clients'}
FINANCE = ('loan','repayment','journal','coa','drawdown','client_loan','client_transaction',
           'company_drawdown','invoice','transactions','trn_','till','bcash','commission',
           'currency','clear_balance','transfer_balance','penalty','depre','fixed_asset','fa_status')
REQUESTS = ('unit_hold','unit_deposit','unit_contract','contract','sale_order','sale_items',
            'sale_commission','change_unit','transfer_clients','migrate_deposit','migrate_installment')
ARCHIVES = {'countries_copy1','repayment_schedule_bk_6_5_24','trn_master_loans_backup'}
TECHNICAL = {'migrations','jobs','failed_jobs','job_history','system_date','audit','user_activity',
             'notification','notifications','save_draft','reschedule_repayment_temp'}

def domain(table):
    name = table.removeprefix('tb_')
    if name in ARCHIVES:
        return 'archive_preserve'
    if name in SECURITY:
        return 'identity_access_review'
    if name in SHARED:
        return 'shared_master_resolve'
    if name.startswith(REQUESTS):
        return 'requests_sales_contract_history'
    if name.startswith(FINANCE):
        return 'financial_preserve_and_reconcile'
    if name in TECHNICAL:
        return 'technical_audit_review'
    return 'reference_or_specialised_manual_review'

result = {}
summary = {}
for source,data in audit['sources'].items():
    tables = sorted({row[0] for row in data['schema']})
    result[source] = [{'table':t,'domain':domain(t),'decision':'PENDING',
        'policy':'Preserve raw staging. Classification does not authorize deleting or merging records.'} for t in tables]
    summary[source] = dict(collections.Counter(row['domain'] for row in result[source]))
(ROOT/'outputs'/'unified-db'/'table-domain-map.json').write_text(json.dumps(result,indent=2))
print(json.dumps(summary,indent=2))
