"""Verify every existing loan's agreed price/final price/deposit against raw staging."""
import hashlib
import importlib.util
import json
import pathlib

HERE = pathlib.Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
staging = importlib.util.module_from_spec(spec)
spec.loader.exec_module(staging)

def main():
    source,password,database,_ = staging.SOURCES['beloan']
    sql = 'SELECT id,unit_sale_price,final_price,diposit_amount FROM tb_loans ORDER BY id;'
    original = staging.mysql(source,password,database,sql)
    copied = staging.mysql(staging.TARGET_CONTAINER,'local-only-unified','beloan_migration_stage',sql)
    mismatches = sum(a!=b for a,b in zip(original,copied)) + abs(len(original)-len(copied))
    result = {'verified_loan_rows':len(original),'per_loan_decimal_price_mismatches':mismatches,
        'fields':['unit_sale_price','final_price','diposit_amount'],
        'original_sha256':hashlib.sha256('\n'.join(original).encode()).hexdigest(),
        'staging_sha256':hashlib.sha256('\n'.join(copied).encode()).hexdigest(),
        'scope':'Exact per-loan DECIMAL price-field verification; no personal rows exported or loan values changed.'}
    (staging.ROOT/'outputs'/'unified-db'/'loan-price-verification.json').write_text(json.dumps(result,indent=2))
    if mismatches:
        raise RuntimeError('Loan-price differences found; do not promote.')
    print('Verified '+str(len(original))+' loan rows: zero agreed-price/final-price/deposit differences.')

if __name__ == '__main__':
    main()
