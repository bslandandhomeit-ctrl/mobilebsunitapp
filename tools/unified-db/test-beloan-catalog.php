<?php
require '/project/vendor/autoload.php';
require '/project/app/Services/UnifiedUnitCatalog.php';
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
try {
 $pdo = new PDO('mysql:host=host.docker.internal;port=3309;dbname=bsunits_beloan_final;charset=utf8mb4', 'unified_beloan_catalog_reader', 'local-only-beloan-catalog-reader', array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
 $db = new Illuminate\Database\MySqlConnection($pdo, 'bsunits_beloan_final', '');
 $db->setFetchMode(PDO::FETCH_OBJ);
 $repo = new App\Services\UnifiedUnitCatalog($db, true);
 $rows = $repo->currentUnits(10);
 check(count($rows) === 10, 'Catalog page failed');
 foreach ($repo->currentUnits(10, 0, true) as $row) { check($row['availability'] === 'AVAILABLE', 'Availability failed: '.json_encode($row['availability'])); }
 $aliases = $db->table('beloan_unit_aliases')->take(20)->get();
 foreach ($aliases as $alias) {
  $row = $repo->findByLegacyUnitId($alias->source_id);
  check($row && $row['unified_unit_id'] == $alias->target_id, 'Legacy mapping failed');
  $unit = $db->table('units')->where('id', $alias->target_id)->first();
  check($row['current_unit_price'] === $unit->list_price, 'Exact price failed');
 }
 check($repo->findByLegacyUnitId('999999999') === null, 'Missing ID failed');
 $db->setFetchMode(PDO::FETCH_ASSOC);
 check(count($repo->currentUnits(1)) === 1, 'Array fetch mode failed');
 $db->setFetchMode(PDO::FETCH_OBJ);
 $db->setTablePrefix('tb_');
 $blocked = false;
 try { $repo->currentUnits(); } catch (LogicException $e) { $blocked = true; }
 check($blocked, 'Legacy table prefix rejection failed');
 $db->setTablePrefix('');
 $blocked = false;
 try { (new App\Services\UnifiedUnitCatalog($db))->currentUnits(); } catch (LogicException $e) { $blocked = true; }
 check($blocked, 'Default disabled gate failed');
 $blocked = false;
 try { $db->statement('UPDATE units SET list_price=list_price WHERE id=1'); } catch (Exception $e) { $blocked = true; }
 check($blocked, 'Write restriction failed');
 $blocked = false;
 try { $db->table('login_identities')->first(); } catch (Exception $e) { $blocked = true; }
 check($blocked, 'Private data restriction failed');
 echo json_encode(array('status'=>'PASSED','runtime'=>PHP_VERSION,'laravel'=>Illuminate\Foundation\Application::VERSION,'legacy_alias_samples'=>count($aliases),'catalog_page'=>count($rows),'default_disabled'=>true,'writes_denied'=>true,'private_data_denied'=>true), JSON_PRETTY_PRINT)."\n";
} catch (Exception $e) { fwrite(fopen('php://stderr','w'), 'Beloan catalog test failed: '.get_class($e).($e instanceof RuntimeException && !($e instanceof PDOException) ? ': '.$e->getMessage() : '')."\n"); exit(1); }
