<?php
namespace App\Services;
use Illuminate\Database\Connection;
/** Preserves Beloan RP + accounting branch + year + sequence receipt format. */
class UnifiedReceiptAllocator {
 private $db;
 public function __construct(Connection $db){if($db->getTablePrefix()!=='')throw new \LogicException('Receipt allocator requires no prefix.');$this->db=$db;}
 public function next($branchId,$entryDate){
  if($this->db->transactionLevel()<1)throw new \LogicException('Receipt allocation requires the posting transaction.');
  $date=\DateTime::createFromFormat('!Y-m-d',substr((string)$entryDate,0,10));
  if(!$date||$date->format('Y-m-d')!==substr((string)$entryDate,0,10))throw new \InvalidArgumentException('Invalid receipt date.');
  $year=(int)$date->format('Y');if($year<1901||$year>2155)throw new \InvalidArgumentException('Unsupported receipt year.');
  $branch=$this->db->table('branches')->where('id',$branchId)->first();
  if(!$branch||!preg_match('/^[A-Za-z0-9-]{1,5}$/D',$branch->code))throw new \InvalidArgumentException('Accounting branch receipt code is invalid.');
  // The source sequence is shared across branches for each year.
  // Acquire an exclusive sequence-row lock immediately; INSERT IGNORE can
  // give concurrent callers shared duplicate-key locks that deadlock on upgrade.
  $this->db->statement('INSERT INTO unified_receipt_sequences (receipt_year,next_number) VALUES (?,1) ON DUPLICATE KEY UPDATE next_number=next_number',array($year));
  $sequence=$this->db->table('unified_receipt_sequences')->where('receipt_year',$year)->lockForUpdate()->first();
  $last=$this->db->table('journal_requiry')->where('years',$year)->orderBy('no','desc')->lockForUpdate()->first();
  $number=max((int)$sequence->next_number,$last?(int)$last->no+1:1);
  if($number<1||$number>=2147483647)throw new \LogicException('Receipt sequence exhausted.');
  $this->db->table('unified_receipt_sequences')->where('receipt_year',$year)->update(array('next_number'=>$number+1));
  return array('years'=>$year,'no'=>$number,'receipt_no'=>'RP'.$branch->code.$date->format('y').str_pad($number,5,'0',STR_PAD_LEFT));
 }
}
