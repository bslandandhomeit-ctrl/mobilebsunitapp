<?php
require '/var/www/html/vendor/autoload.php';date_default_timezone_set('Asia/Phnom_Penh');
function editCheck($ok,$label){if(!$ok)throw new RuntimeException('Editor check failed: '.$label);}
try{
 $pdo=new PDO('mysql:host=host.docker.internal;port=3309;dbname=unified_customer_edit_test;charset=utf8mb4','unified_beloan_customer_writer','local-only-unified-customer-writer',array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
 $db=new Illuminate\Database\MySqlConnection($pdo,'unified_customer_edit_test','');$db->setFetchMode(PDO::FETCH_OBJ);
 $db->table('customer_account_evidence')->insert(array('account_id'=>1,'source_table'=>'test_original_contact','source_id'=>1,'evidence_kind'=>'CONTACT','ownership_state'=>'ACCOUNT_ONLY','source_payload'=>json_encode(array('phone_number'=>'0990000099')),'source_checksum'=>'original'));
 $editor=new App\Services\UnifiedCustomerAccountEditor($db);$before=$editor->state(1);
 editCheck($editor->update(1,'Updated Test Account',array('0990000001'),$before['version'],1),'first edit');
 $reader=new App\Services\UnifiedCustomersRepository($db);
 editCheck($reader->detail(1)['contacts']===array('0990000001'),'updated contacts visible');
 editCheck($reader->listing(array('phone'=>'0990000001'))->total()===1,'phone search finds override');
 editCheck($reader->listing(array('phone'=>'0990000002'))->total()===0,'phone search does not broaden');
 editCheck($reader->listing(array('phone'=>'0990000099'))->total()===0,'superseded contact omitted from search');
 editCheck($db->table('customer_account_evidence')->where('source_table','test_original_contact')->pluck('source_checksum')==='original','original evidence preserved');
 $after=$editor->state(1);editCheck($after['account']->display_name==='Updated Test Account','name updated');
 editCheck($db->table('customer_account_change_audit')->count()===1,'one audit');
 $blocked=false;try{$editor->update(1,'Stale Edit',array('0990000002'),$before['version'],1);}catch(LogicException $e){$blocked=true;}editCheck($blocked,'stale edit blocked');
 editCheck(!$editor->update(1,'Updated Test Account',array('0990000001'),$after['version'],1),'unchanged save no audit');
 $blocked=false;try{$editor->update(1,'Bad Phone',array('invalid'),$after['version'],1);}catch(InvalidArgumentException $e){$blocked=true;}editCheck($blocked,'invalid phone rejected');
 $blocked=false;try{$editor->update(1,'Missing Actor',array('0990000002'),$after['version'],99999999);}catch(LogicException $e){$blocked=true;}editCheck($blocked,'unmapped actor rejected');
 editCheck($editor->state(1)['version']===$after['version'],'failed edit rolled back');
 $blocked=false;try{$db->update('UPDATE bsunits_beloan_final.persons SET name_en=name_en WHERE id=1');}catch(Exception $e){$blocked=true;}editCheck($blocked,'person mutation denied');
 $blocked=false;try{$db->update('UPDATE bsunits_beloan_final.loans SET loan_amount=loan_amount WHERE id=1');}catch(Exception $e){$blocked=true;}editCheck($blocked,'loan mutation denied');
 editCheck($editor->update(1,'Updated Test Account',array(),$after['version'],1),'clear phone override');
 editCheck($reader->detail(1)['contacts']===array(),'cleared phone numbers stay empty');
 editCheck($reader->listing(array('phone'=>'0990000099'))->total()===0,'cleared contacts do not restore old phone');
 echo json_encode(array('status'=>'PASSED','contact_override_reads_and_search'=>true,'audit'=>true,'optimistic_concurrency'=>true,'no_op'=>true,'validation'=>true,'rollback'=>true,'person_and_loan_writes_denied'=>true,'target'=>'Disposable scratch schema'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){fwrite(fopen('php://stderr','w'),$e instanceof RuntimeException?$e->getMessage()."\n":'Editor test failed: '.get_class($e).' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
