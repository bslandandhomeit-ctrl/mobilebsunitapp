<?php
namespace App\Services;
use Illuminate\Database\Connection;
/** Used only inside a caller-owned unified financial transaction. */
class UnifiedJournalWriter {
 private $db;
 public function __construct(Connection $db){if($db->getTablePrefix()!=='')throw new \LogicException('Journal writer requires no prefix.');$this->db=$db;}
 public function post(array $header,array $lines,$requestKey){
  if($this->db->transactionLevel()<1)throw new \LogicException('A caller-owned transaction is required.');
  if(!is_string($requestKey)||!preg_match('/^[A-Za-z0-9:_.-]{1,120}$/D',$requestKey))throw new \InvalidArgumentException('Invalid posting key.');
  if(count($lines)<2)throw new \InvalidArgumentException('Balanced journal lines required.');
  $sum=0;$accounts=array();
  foreach($lines as &$line){
   foreach(array('debit','credit') as $field){$amount=isset($line[$field])?(string)$line[$field]:'0';if(!preg_match('/^([0-9]{1,12})(?:\.([0-9]{1,4}))?$/D',$amount,$match))throw new \InvalidArgumentException('Invalid journal amount.');$fraction=isset($match[2])?$match[2]:'';$minor=(int)$match[1]*10000+(int)str_pad($fraction,4,'0');$line[$field]=$match[1].'.'.str_pad($fraction,4,'0');if($field==='debit'){$debit=$minor;$sum+=$minor;}else{$credit=$minor;$sum-=$minor;}}
   if(($debit>0)==($credit>0)||empty($line['coa_id']))throw new \InvalidArgumentException('One positive side required per line.');
   if(abs($sum)>9000000000000000)throw new \InvalidArgumentException('Journal amount limit exceeded.');
   $accounts[(int)$line['coa_id']]=true;
  }unset($line);
  if($sum!==0)throw new \InvalidArgumentException('Journal is not balanced.');
  $payload=hash('sha256',json_encode(array($header,$lines)));
  $this->db->statement('INSERT IGNORE INTO unified_journal_posting_keys (request_key,payload_hash,journal_id) VALUES (?,?,NULL)',array($requestKey,$payload));
  $key=$this->db->table('unified_journal_posting_keys')->where('request_key',$requestKey)->lockForUpdate()->first();
  if($key->payload_hash!==$payload)throw new \LogicException('Posting key payload changed.');
  if($key->journal_id)return (int)$key->journal_id;
  $ids=array_keys($accounts);sort($ids,SORT_NUMERIC);
  foreach($ids as $id){if(!$this->db->table('coa_categories')->where('id',$id)->lockForUpdate()->first())throw new \InvalidArgumentException('Unknown accounting account.');}
  $journalId=$this->db->table('journal_requiry')->insertGetId($header);
  foreach($lines as $line){
   $previous=$this->db->table('journal_detail')->where('coa_id',$line['coa_id'])->orderBy('id','desc')->first();
   $line['journal_id']=$journalId;$line['p_debit']=$previous?$previous->b_debit:'0.0000';$line['p_credit']=$previous?$previous->b_credit:'0.0000';
   // Decimal sums stay in SQL. Historical DOUBLE balances are not recomputed.
   $line['b_debit']=$this->db->raw('CAST('.$line['p_debit'].' AS DECIMAL(18,4))+CAST('.$line['debit'].' AS DECIMAL(18,4))');
   $line['b_credit']=$this->db->raw('CAST('.$line['p_credit'].' AS DECIMAL(18,4))+CAST('.$line['credit'].' AS DECIMAL(18,4))');
   $this->db->table('journal_detail')->insert($line);
  }
  $this->db->table('unified_journal_posting_keys')->where('request_key',$requestKey)->update(array('journal_id'=>$journalId));
  return $journalId;
 }
}
