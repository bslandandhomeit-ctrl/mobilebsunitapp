<?php
namespace App\Services;
use App\Models\LoanPayments;
/** Links only the payment rows created by this posting transaction. */
class OriginalRepaymentInvoiceLinker {
 public static function attach($posting, array $ids, $loanId, $type) {
  $ids=array_values(array_unique($ids));
  if (!$ids) return;
  if (!is_array($posting)||empty($posting['invoice_number'])) throw new \RuntimeException('Repayment journal did not complete.');
  $model=new LoanPayments();$db=$model->getConnection();
  if ($db->transactionLevel()<1) throw new \LogicException('Repayment invoice linking requires its posting transaction.');
  $rows=$model->whereIn('id',$ids)->lockForUpdate()->get();
  if (count($rows)!==count($ids)) throw new \RuntimeException('Repayment payment rows are missing.');
  foreach($rows as $row) {
   if ($row->loan_id!=$loanId||$row->loan_repayment_type!==$type||$row->invoice_number!=='00000000') throw new \RuntimeException('Repayment invoice link does not belong to this posting.');
  }
  $model->whereIn('id',$ids)->update(array('invoice_number'=>$posting['invoice_number']));
 }
}
