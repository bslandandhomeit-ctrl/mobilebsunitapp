"""Two PHP sessions contend on a disposable unit; final catalog is never written."""
import importlib.util
import json
import subprocess
import time
import uuid
from pathlib import Path

HERE = Path(__file__).resolve().parent
spec = importlib.util.spec_from_file_location('staging',HERE/'stage-properties.py')
s = importlib.util.module_from_spec(spec)
spec.loader.exec_module(s)

def run():
    suffix = uuid.uuid4().hex[:8]
    schema = 'bsunits_price_test_'+suffix
    account = 'unified_price_test_'+suffix
    secret = 'local-only-price-test'
    # Account DDL requires literal names, not the data-expression hex helper.
    user_spec = "'"+account+"'@'%'"
    s.target('CREATE DATABASE `'+schema+'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    user_created = False
    processes = []
    try:
        s.target('CREATE USER '+user_spec+" IDENTIFIED BY 'local-only-price-test';")
        user_created = True
        s.target('''CREATE TABLE `'''+schema+'''`.units LIKE units;
          INSERT INTO `'''+schema+'''`.units SELECT * FROM units WHERE catalog_state='CURRENT' AND deleted_at IS NULL ORDER BY id LIMIT 1;
          CREATE TABLE `'''+schema+'''`.actors (id BIGINT UNSIGNED PRIMARY KEY);
          INSERT INTO `'''+schema+'''`.actors SELECT id FROM actors;
          CREATE TABLE `'''+schema+'''`.actor_source_map LIKE actor_source_map;
          INSERT INTO `'''+schema+'''`.actor_source_map SELECT * FROM actor_source_map;
          CREATE TABLE `'''+schema+'''`.unit_price_changes LIKE unit_price_changes;''')
        for table in ('units','actor_source_map','unit_price_changes'):
            s.target('GRANT SELECT ON `'+schema+'`.`'+table+'` TO '+user_spec+';')
        s.target('GRANT UPDATE(list_price) ON `'+schema+'`.units TO '+user_spec+'; GRANT INSERT ON `'+schema+'`.unit_price_changes TO '+user_spec+';')
        unit,expected = s.target('SELECT id,list_price FROM `'+schema+'`.units;')[0].split('\t')
        env = {'UNIFIED_CATALOG_ENABLED':'true','UNIFIED_CATALOG_WRITES_ENABLED':'true',
            'UNIFIED_DB_HOST':'host.docker.internal','UNIFIED_DB_DATABASE':schema,
            'UNIFIED_WRITE_DB_USERNAME':account,'UNIFIED_WRITE_DB_PASSWORD':secret,
            'PRICE_TEST_UNIT':unit,'PRICE_TEST_EXPECTED':expected,'PRICE_TEST_KEY':'race-'+suffix}
        argv = ['docker','exec','-i']
        for key,value in env.items():
            argv += ['-e',key+'='+value]
        argv += ['bsunits-mobile-local-app-1','php']
        locker_code = '''<?php
try {
 $db = new PDO('mysql:host=host.docker.internal;port=3309;dbname='.getenv('UNIFIED_DB_DATABASE'),getenv('UNIFIED_WRITE_DB_USERNAME'),getenv('UNIFIED_WRITE_DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $db->beginTransaction(); $db->query('SELECT id FROM units FOR UPDATE')->fetchAll();
 echo "LOCKED\\n"; fflush(fopen('php://stdout','w')); sleep(10); $db->commit();
} catch (Throwable $e) { fwrite(fopen('php://stderr','w'),'Scratch lock failed.'); exit(1); }
'''
        locker = subprocess.Popen(argv,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
        processes.append(locker)
        locker.stdin.write(locker_code)
        locker.stdin.close()
        if locker.stdout.readline().strip()!='LOCKED':
            raise RuntimeError('Scratch lock could not be established.')
        worker_code = (HERE/'test-price-concurrency-worker.php').read_text()
        workers = []
        for _ in range(2):
            process = subprocess.Popen(argv,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
            process.stdin.write(worker_code)
            process.stdin.close()
            workers.append(process)
            processes.append(process)
        time.sleep(1)
        if any(p.poll() is not None for p in workers):
            raise RuntimeError('Worker failed before contending on the locked unit; details withheld.')
        locker.wait(timeout=20)
        outputs = []
        for worker in workers:
            worker.wait(timeout=30)
            if worker.returncode:
                raise RuntimeError('Concurrent worker failed; source details withheld.')
            outputs.append(json.loads(worker.stdout.read()))
        count = int(s.target('SELECT COUNT(*) FROM `'+schema+'`.unit_price_changes;')[0])
        final_price = s.target('SELECT list_price FROM `'+schema+'`.units;')[0]
        if count!=1 or final_price!='123456.78' or outputs[0]['change_id']!=outputs[1]['change_id']:
            raise RuntimeError('Concurrent retry produced inconsistent results.')
        report = {'status':'PASSED','concurrent_workers':2,'both_contended_on_locked_unit':True,
            'audit_records':count,'same_change_id':True,'final_catalog_written':False,
            'scope':'Real separate PHP/MySQL sessions in a disposable scratch schema; concurrent identical requests return one price-change event.'}
        (s.ROOT/'outputs/unified-db/unit-price-concurrency-result.json').write_text(json.dumps(report,indent=2)+'\n')
        print(json.dumps(report,indent=2))
    finally:
        for process in processes:
            if process.poll() is None:
                process.kill()
                process.wait()
        s.target('DROP DATABASE `'+schema+'`;')
        if user_created:
            s.target('DROP USER '+user_spec+';')

if __name__ == '__main__':
    run()
