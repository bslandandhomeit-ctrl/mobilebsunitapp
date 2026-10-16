<?php
require '/var/www/html/vendor/autoload.php';$app=require '/var/www/html/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=config('unified_posting.database');$cfg['database']='unified_journal_writer_test';$cfg['username']='unified_journal_test';$cfg['password']='local-only-journal-test';config(array('database.connections.journal_test'=>$cfg));
$db=Illuminate\Support\Facades\DB::connection('journal_test');$writer=new App\Services\UnifiedJournalWriter($db);
function journalCheck($ok,$message){if(!$ok)throw new RuntimeException($message);}
try{
 $header=array('tran_id'=>0,'entry_date'=>'2026-10-08 00:00:00','description'=>'Synthetic test','user_id'=>3,'trans_type'=>'Cash Deposit','is_audit'=>'0');
 $lines=array(array('coa_id'=>1,'branch_code'=>'TEST','debit'=>'0.1001','credit'=>'0','is_audit'=>'0'),array('coa_id'=>2,'branch_code'=>'TEST','debit'=>'0','credit'=>'0.1001','is_audit'=>'0'));
 if(getenv('JOURNAL_CONCURRENCY_TEST')){$db->beginTransaction();$id=$writer->post($header,$lines,'test:concurrent');usleep(200000);$db->commit();echo json_encode(array('journal_id'=>$id))."\n";exit;}
 if(getenv('RECEIPT_CONCURRENCY_TEST')){$db->beginTransaction();$allocation=(new App\Services\UnifiedReceiptAllocator($db))->next(1,'2026-10-08');$receiptHeader=array_merge($header,$allocation);$db->table('journal_requiry')->insert($receiptHeader);usleep(200000);$db->commit();echo json_encode(array('receipt_no'=>$allocation['receipt_no'],'sequence'=>$allocation['no']))."\n";exit;}
 $required=false;try{$writer->post($header,$lines,'test:one');}catch(LogicException $e){$required=true;}journalCheck($required,'Outside transaction accepted');
 $db->beginTransaction();$id=$writer->post($header,$lines,'test:one');$replay=$writer->post($header,$lines,'test:one');journalCheck($id===$replay&&$db->table('journal_detail')->count()===2,'Replay duplicated journal');
 $debit=$db->table('journal_detail')->where('coa_id',1)->first();journalCheck($debit->debit==='0.1001'&&$debit->b_debit==='0.1001','Decimal precision changed');
 $changed=$header;$changed['description']='Changed';$rejected=false;try{$writer->post($changed,$lines,'test:one');}catch(LogicException $e){$rejected=true;}journalCheck($rejected,'Changed replay accepted');$db->commit();
 $db->beginTransaction();$writer->post($header,$lines,'test:rollback');$db->rollBack();journalCheck($db->table('journal_requiry')->count()===1&&$db->table('journal_detail')->count()===2&&$db->table('unified_journal_posting_keys')->count()===1,'Rollback incomplete');
 $db->beginTransaction();$bad=$lines;$bad[1]['credit']='0.1002';$rejected=false;try{$writer->post($header,$bad,'test:unbalanced');}catch(InvalidArgumentException $e){$rejected=true;}$db->rollBack();journalCheck($rejected,'Unbalanced posting accepted');
 $db->beginTransaction();$writer->post($header,$lines,'test:second');$db->commit();$last=$db->table('journal_detail')->where('coa_id',1)->orderBy('id','desc')->first();journalCheck($last->p_debit==='0.1001'&&$last->b_debit==='0.2002','Prior balance lineage incorrect');
 $allocator=new App\Services\UnifiedReceiptAllocator($db);
 $db->beginTransaction();$receipt=$allocator->next(1,'2026-10-08');journalCheck($receipt['receipt_no']==='RP1002600001','Original receipt format changed');$db->rollBack();
 journalCheck($db->table('unified_receipt_sequences')->count()===0,'Receipt allocation survived rollback');
 $db->beginTransaction();$first=$allocator->next(1,'2026-10-08');$second=$allocator->next(2,'2026-10-08');journalCheck($first['no']===1&&$second['no']===2&&$second['receipt_no']==='RP2002600002','Branches share wrong sequence');$db->commit();
 $db->beginTransaction();$nextYear=$allocator->next(1,'2027-01-01');journalCheck($nextYear['no']===1,'Year sequence did not restart');$db->rollBack();
 $db->beginTransaction();$badBranch=false;try{$allocator->next(99999,'2026-10-08');}catch(InvalidArgumentException $e){$badBranch=true;}$db->rollBack();journalCheck($badBranch,'Unknown branch accepted');
 echo json_encode(array('status'=>'PASSED','transaction_required'=>true,'balanced_decimal_lines'=>true,'replay'=>true,'changed_replay_rejected'=>true,'atomic_rollback'=>true,'prior_balance_lineage'=>true,'scope'=>'Disposable schema only; cashier integration and concurrent process tests pending'),JSON_PRETTY_PRINT)."\n";
}catch(Exception $e){if($db->transactionLevel())$db->rollBack();fwrite(fopen('php://stderr','w'),'Journal test failed: '.get_class($e).' code='.$e->getCode().($e instanceof Illuminate\Database\QueryException&&isset($e->errorInfo[1])?' driver='.$e->errorInfo[1]:'').' at '.basename($e->getFile()).':'.$e->getLine()."\n");exit(1);}
