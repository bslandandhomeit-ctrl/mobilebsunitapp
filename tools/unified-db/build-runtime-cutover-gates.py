"""Inventory actual remaining relationship and runtime gates from latest code.

Static evidence only; does not claim workflow coverage from model presence.
"""
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
MODELS = Path('/Users/ramonoem/Documents/project/beloan-code/app/Models')
SCOPED = ['Loan','DrawdownAccounts','ClientLoanAccounts','JournalRequiry','JournalDetail',
          'TransactionsRequiry','CoaCategory','LoanPayments','RepaymentSchedule','Project','UnitType','CompanyBranch','Staff','JobHistory','Role']


def run():
    rows = []
    for name in SCOPED:
        path = MODELS / (name + '.php')
        text = path.read_bytes().decode('utf8', errors='replace')
        methods = list(re.finditer(r'(?:public\s+|protected\s+|private\s+|final\s+)*function\s+(\w+)\s*\(', text))
        for i, match in enumerate(methods):
            body = text[match.start():methods[i+1].start() if i+1 < len(methods) else len(text)]
            calls = re.findall(r"\$this->(belongsTo|hasOne|hasMany|belongsToMany)\(\s*'([^']+)'", body)
            adapters = re.findall(r"unifiedMasterRelation\(\s*'([^']+)'\s*,\s*'([^']+)'", body)
            many_adapters = re.findall(r"unifiedMasterManyRelation\(\s*'([^']+)'", body)
            if not calls and not adapters and not many_adapters:
                continue
            explicit = ('unifiedMasterRelation(' in body or 'unifiedMasterManyRelation(' in body or ('unifiedReadSelected()' in body and 'Relations\\HasManyThrough(' in body))
            # A gated adapter plus original fallback is intentional, not an escape.
            rows.append({'model':name,'method':match.group(1),
                         'file':str(path),'line':text[:match.start()].count('\n')+1,
                         'related_models':sorted(set([c[1] for c in calls + adapters] + many_adapters)),
                         'status':'EXPLICIT_ADAPTER_WITH_ORIGINAL_FALLBACK' if explicit else 'UNADAPTED_RELATION',
                         'requires_review':not explicit})
    pending = [r for r in rows if r['requires_review']]
    report = {'source':'Latest beloan-code; scoped static scan, not execution coverage. Includes adapter-only methods as well as original relation calls.',
              'scoped_models':SCOPED,
              'relationships':rows,'unadapted_relationship_count':len(pending),
              'runtime_gates':[
                  'Authentication/role/permission source-qualified behavior is still on original runtime DB.',
                  'A single unified PDO must own every finance transaction, including audit/activity/notification writes.',
                  'Hardcoded tb_ SQL and query aliases need explicit compatibility review.',
                  'Read-only compatibility models deliberately reject save/delete; no write cutover is enabled.',
                  'Original full customer/profile models have a separate connection; cross-module transaction boundaries need resolution.',
                  'Shared unit availability/price writes must retain BS authority.',
                  'Original cashier, repayment and accounting end-to-end regression and rollback rehearsal pending.'
              ],'full_cutover_ready':False}
    out = ROOT / 'outputs/unified-db/runtime-cutover-gates.json'
    out.write_text(json.dumps(report,indent=2)+'\n')
    print('Reviewed '+str(len(rows))+' relationship methods; '+str(len(pending))+' require explicit review. Full cutover remains disabled.')


if __name__ == '__main__':
    run()
