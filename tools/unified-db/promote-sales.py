"""Canonical sales rehearsal preserving original monetary types and values."""
import importlib.util
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

def run():
    for name in ('sales','sale_items'):
        if s.target("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME="+s.literal(name)+';'):
            raise RuntimeError('Sales target exists; refusing to overwrite.')
    # LIKE keeps source decimal widths, enums and timestamps. No price recalculation.
    s.target('''CREATE TABLE sales LIKE beloan_migration_stage.tb_sale_order;
      ALTER TABLE sales CHANGE client_id customer_account_id BIGINT UNSIGNED NULL;
      CREATE TABLE sale_items LIKE beloan_migration_stage.tb_sale_items;
      ALTER TABLE sale_items MODIFY unit_id BIGINT UNSIGNED NULL,
       MODIFY project_id BIGINT UNSIGNED NULL, MODIFY unit_type_id BIGINT UNSIGNED NULL;
      CREATE TABLE sale_item_source_references (
       sale_item_id BIGINT UNSIGNED PRIMARY KEY,
       source_unit_id INT NULL, source_project_id INT NULL,
       source_unit_type_id INT NULL, source_branch_id INT NULL
      ) ENGINE=InnoDB;
    ''')
    statements = ['START TRANSACTION;','INSERT INTO sales SELECT * FROM beloan_migration_stage.tb_sale_order;',
        'INSERT INTO sale_items SELECT * FROM beloan_migration_stage.tb_sale_items;',
        'INSERT INTO sale_item_source_references SELECT id,unit_id,project_id,unit_type_id,company_branch_id FROM beloan_migration_stage.tb_sale_items;']
    for column,table in (('unit_id','tb_units'),('project_id','tb_projects'),('unit_type_id','tb_unit_types')):
        statements.append('UPDATE sale_items i JOIN source_record_map m ON m.source_system=\'beloan\' AND m.source_table='+s.literal(table)+' AND m.source_id=(CAST(i.'+column+' AS CHAR) COLLATE utf8mb4_unicode_ci) SET i.'+column+'=m.target_id;')
    statements.append('COMMIT;')
    s.target('\n'.join(statements))
    checks = {
        'missing_customer_accounts':'SELECT COUNT(*) FROM sales r LEFT JOIN customer_accounts a ON a.id=r.customer_account_id WHERE a.id IS NULL;',
        'missing_sales':'SELECT COUNT(*) FROM sale_items i LEFT JOIN sales r ON r.id=i.sale_id WHERE r.id IS NULL;',
        'unit_mapping_differences':"SELECT COUNT(*) FROM sale_items i JOIN sale_item_source_references r ON r.sale_item_id=i.id LEFT JOIN source_record_map m ON m.source_system='beloan' AND m.source_table='tb_units' AND m.source_id=(CAST(r.source_unit_id AS CHAR) COLLATE utf8mb4_unicode_ci) WHERE m.target_id IS NULL OR NOT(i.unit_id <=> m.target_id);",
        'changed_sale_amounts':'SELECT COUNT(*) FROM sales r JOIN beloan_migration_stage.tb_sale_order o ON o.id=r.id WHERE NOT(r.final_price <=> o.final_price) OR NOT(r.diposit_amount <=> o.diposit_amount) OR NOT(r.price_after_discount <=> o.price_after_discount);',
        'changed_item_prices':'SELECT COUNT(*) FROM sale_items i JOIN beloan_migration_stage.tb_sale_items o ON o.id=i.id WHERE NOT(i.unit_sale_price <=> o.unit_sale_price);',
    }
    results = {key:int(s.target(sql)[0]) for key,sql in checks.items()}
    counts = {name:int(s.target('SELECT COUNT(*) FROM '+name+';')[0]) for name in ('sales','sale_items')}
    for target,source in (('sales','tb_sale_order'),('sale_items','tb_sale_items')):
        if counts[target] != int(s.target('SELECT COUNT(*) FROM beloan_migration_stage.'+source+';')[0]):
            raise RuntimeError('Sale count mismatch.')
    report = {'counts':counts,'checks':results,'scope':'Historical sales only. Original actor, currency and accounting-branch references retained pending domain adapters. No approval event or financial posting executed.'}
    (s.ROOT/'outputs/unified-db/sales-promotion-result.json').write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report,indent=2))
    if any(results.values()):
        raise RuntimeError('Sales rehearsal requires exception review; applications must not switch.')

if __name__ == '__main__':
    run()
