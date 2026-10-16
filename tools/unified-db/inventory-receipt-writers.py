"""Trace receipt generators versus receipt references in latest Beloan code."""
import json
import re
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
CODE=Path('/Users/ramonoem/Documents/project/beloan-code')

def run():
    calls=[]
    generators=[]
    header_allocations=[]
    for base in ['app','helpers']:
        for path in sorted((CODE/base).rglob('*.php')):
            text=path.read_bytes().decode('utf8',errors='replace')
            method='file_scope'
            for number,line in enumerate(text.splitlines(),1):
                declaration=re.search(r'function\s+(\w+)\s*\(',line)
                if declaration:method=declaration[1]
                entry={'file':str(path),'line':number,'method':method}
                if 'record_journal_no_trans(' in line and not declaration and not line.lstrip().startswith('//'):
                    calls.append(entry)
                if re.search(r'->receipt_no\s*=',line):
                    kind='REFERENCE_COPY'
                    if "date('YmdHis')" in line:kind='BCASH_TIMESTAMP_GENERATOR'
                    elif "'RP' . $id" in line:kind='COMMISSION_RECEIPT_GENERATOR'
                    elif "'JR-'" in line:kind='JOURNAL_DISPLAY_NUMBER'
                    elif '$receipt_no' in line and method=='record_journal_no_trans':kind='JOURNAL_YEAR_SEQUENCE_GENERATOR'
                    generators.append(dict(entry,kind=kind))
                if re.search(r'->id\s*=\s*JournalRequiry::(?:max|select)' ,line):
                    header_allocations.append(dict(entry,kind='MANUAL_MAX_HEADER_ID_REVIEW'))
    result={'journal_helper_call_sites':calls,'receipt_assignments':generators,'manual_header_id_allocations':header_allocations,
            'policy':'Different receipt families remain separate. Copying a BCash receipt into its related journal is not a duplicate customer payment. Historical duplicate numbers are preserved; no blind global renumbering or uniqueness constraint.',
            'next_gates':['Classify journal helper calls by receipt flag and transaction owner before allocator activation.','Review BCash timestamp collisions separately from yearly journal sequences.','Review manual MAX(id)+1 header allocation under concurrency.','Rehearse original cashier/repayment plus linked receipts on one unified transaction.'],
            'status':'STATIC_REVIEW_NO_ACTIVATION'}
    (ROOT/'outputs/unified-db/receipt-writer-inventory.json').write_text(json.dumps(result,indent=2)+'\n')
    print('Receipt assignments: '+str(len(generators))+'; journal helper calls: '+str(len(calls))+'; manual header allocations: '+str(len(header_allocations)))

if __name__=='__main__':run()
