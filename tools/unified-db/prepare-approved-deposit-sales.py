"""Local prerequisite: exact cent/percentage storage and one request-to-sale link."""
import importlib.util,json
from pathlib import Path
s=importlib.util.spec_from_file_location('s',Path(__file__).with_name('stage-properties.py'));m=importlib.util.module_from_spec(s);s.loader.exec_module(m)
fields={'sales':['clearance_amount','discount_promotion','discount_other','discount_payment_option','amount_discount_payment_option','price_after_discount','vat','diposit_amount','final_price'],'sale_items':['discount_promotion','discount_other','discount_payment_option','amount_discount_payment_option']}
checks={}
for table,cols in fields.items():
 source='tb_sale_order' if table=='sales' else 'tb_sale_items'
 comparisons=' OR '.join(f'NOT(t.`{c}` <=> s.`{c}`)' for c in cols)
 def check():return int(m.target(f'SELECT COUNT(*) FROM {table} t JOIN beloan_migration_stage.{source} s ON s.id=t.id WHERE {comparisons}')[0])
 if check():raise RuntimeError('Historical amounts already differ; refusing schema change.')
 for c in cols:
  # Retain nullability; expand existing whole-number precision without reducing integer capacity.
  nullable=m.target("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+m.literal(table)+' AND COLUMN_NAME='+m.literal(c))[0]
  m.target(f'ALTER TABLE `{table}` MODIFY `{c}` DECIMAL(20,2) '+('NULL DEFAULT NULL' if nullable=='YES' else 'NOT NULL'))
 checks[table]=check()
 if checks[table]:raise RuntimeError('Historical amount verification failed.')
m.target('''CREATE TABLE IF NOT EXISTS approved_deposit_sale_links (
 source_system VARCHAR(20) NOT NULL,
 deposit_request_id BIGINT UNSIGNED NOT NULL,
 customer_account_id BIGINT UNSIGNED NOT NULL,
 sale_id BIGINT UNSIGNED NOT NULL,
 approved_actor_id BIGINT UNSIGNED NOT NULL,
 request_snapshot JSON NOT NULL,
 snapshot_sha256 CHAR(64) NOT NULL,
 created_at DATETIME NOT NULL,
 PRIMARY KEY(source_system,deposit_request_id),
 UNIQUE KEY one_sale_per_request(sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;''')
report={'status':'PASSED','historical_amount_mismatches':checks,'expanded_columns':fields,'request_sale_uniqueness':'Composite primary key and unique sale_id prepared; consumer not yet implemented.','scope':'Local final DB only. No account or sale created; loan schemas and amounts unchanged.'}
Path('outputs/unified-db/approved-deposit-sale-preparation.json').write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
