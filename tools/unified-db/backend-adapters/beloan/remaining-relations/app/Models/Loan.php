<?php 
namespace App\Models;
/**
 * Created by PhpStorm.
 * User: ChamroeunDeab
 * Date: 5/29/2015
 * Time: 4:24 PM
 */

use Illuminate\Database\Eloquent\Model;
use LoanCalculate;

class Loan extends Model {
    use UnifiedMasterReadRelations;
    protected $table = 'loans';
    protected $hidden = ['created_at', 'updated_at'];
    protected $lastActionTmp = null;

    public function product(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Product','product_id','products','product');
    }
    public function client(){
        if(strpos($this->getTable(),'beloan_compat_')===0)return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceCustomerRead','client_id','clients','client');
        return $this->belongsTo('App\Models\Client','client_id');
    }
    public function loanDealer(){

        return $this->unifiedMasterRelation('hasOne','App\Models\LoanDealer','loan_id','loan_dealers','loanDealer');
    }
    public function branch()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\CompanyBranch','company_branch_id','company_branch','branch');
    }
    public function payment(){
        return $this->unifiedMasterRelation('hasMany','App\Models\LoanPayments','loan_id','loan_payments','payment');
    }

    public function approval(){
        return $this->unifiedMasterRelation('hasOne','App\Models\LoanApproval','loan_id','loan_approval','approval');
    }
    public function close(){
        return $this->unifiedMasterRelation('hasOne','App\Models\LoanClose','loan_id','loan_close','close');
    }
    public function writeoff(){
        return $this->unifiedMasterRelation('hasOne','App\Models\LoanWriteOff','loan_id','loan_write_off','writeoff');
    }
    public function payoff(){
        return $this->unifiedMasterRelation('hasOne','App\Models\LoanPayOff','loan_id','loan_payoff','payoff');
    }
    public function collateral(){
        return $this->unifiedMasterRelation('hasMany','App\Models\LoanCollateral','loan_id','collateral','collateral');
    }
    public function loanapproval(){
        return $this->unifiedMasterRelation('hasOne','App\Models\LoanApproval','loan_id','loan_approval','loanapproval');
    }
    public function feecharge(){
        return $this->unifiedMasterRelation('hasMany','App\Models\FeeCharge','loan_id','fee_charges','feecharge');
    }
    public function costfee()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\LoanCostFee','loan_id','loan_cost_fees','costfee');
    }
    public function transaction()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\TransactionsRequiry','loan_id','transactions_requiry','transaction');
    }
    public function dealer(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Dealer','dealer_id','dealers','dealer');
    }
    public function user(){
        if ($this->unifiedReadSelected()) return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceActorRead','user_id','actor_reads','user');
        return $this->belongsTo('App\Models\User','user_id');
    }
    public function co_user(){
        if ($this->unifiedReadSelected()) return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceActorRead','co','actor_reads','co_user');
        return $this->belongsTo('App\Models\User','co');
    }
    public function schedule()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\RepaymentSchedule','loan_id','repayment_schedule','schedule');
    }
    public function schedule_type($type = 'loan')
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\RepaymentSchedule','loan_id','repayment_schedule','schedule')->where(($this->unifiedReadSelected()?'beloan_compat_repayment_schedule':'repayment_schedule').'.type','=', (string)$type);
    }
	public function getHolidayAttribute()
	{
        $date = add_month(date('Y-m-d', strtotime($this->start_date)), $this->loan_duration + 1)->format('Y-m-d');
		$holiday = new Holiday();
        if ($this->unifiedReadSelected()) { $holiday->setConnection($this->getConnectionName()); $holiday->setTable('beloan_compat_holiday'); }
        return $holiday->newQuery()->whereBetween('holiday_date',[$this->start_date,$date])->orderBy('holiday_date')->lists('holiday_date');
	}
    public function transaction_req(){
        return $this->unifiedMasterRelation('hasMany','App\Models\TransactionsRequiry','loan_id','transactions_requiry','transaction_req');
    }
    public function client_loan_account()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\ClientLoanAccounts','loan_account_id','client_loan_accounts','client_loan_account');
    }

    public function transaction_reqLastBalance(){
    	return $this->unifiedMasterRelation('hasMany','App\Models\TransactionsRequiry','loan_id','transactions_requiry','transaction_reqLastBalance');
    }

    public function accrued_journal_detail(){
    	return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','accrued_journal_detail', 'air_id');
    }

    public function coa_journal_detail(){
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','coa_journal_detail', 'coa_id');
    }
    public function accrued_repayment_schedule(){
    	return $this->unifiedMasterRelation('hasMany','App\Models\RepaymentSchedule','loan_id','repayment_schedule','schedule');
    }

    public function penalty_record(){
        return $this->unifiedMasterRelation('hasMany','App\Models\PenaltyRecord','loan_id','penalty_record','penalty_record');
    }

    public function scheduleOne()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\RepaymentSchedule','loan_id','repayment_schedule','schedule');
    }
    public function product_type(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Products\Product_type','loan_type','product_type','product_type');
    }
    public function transaction_req_last(){
    	return $this->unifiedMasterRelation('hasMany','App\Models\TransactionsRequiry','loan_id','transactions_requiry','transaction_req_last');
    }

    public function unittypes(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\UnitType','unit_type_id','unit_types','unittypes');
    }

    public function projects(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Project','project_id','projects','projects');
    }
    public function units(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Unit','unit_id','units','units');
    }

    public function RepaymentSchedules(){
        return $this->unifiedMasterRelation('hasMany','App\Models\RepaymentSchedule','loan_id','repayment_schedule','schedule');
    }
    function lastSchedule(){
        return $this->unifiedMasterRelation('hasOne','App\Models\RepaymentSchedule','loan_id','repayment_schedule','lastSchedule')->latest();
    }

    public function loan_drawdow_acc(){
        return $this->unifiedMasterRelation('hasOne','App\Models\DrawdownAccounts','account_no','drawdown_account','loan_drawdow_acc','drawdown_acc');
    }

    public function co_borrowers(){
        return $this->unifiedMasterRelation('hasOne','App\Models\CoBorrower','loan_id','co_borrowers','co_borrowers');
    }

    public function sale_persons(){
        return $this->unifiedMasterRelation('hasOne','App\Models\SalePerson','id','saleperson','sale_persons','sale_person');
    }

    public function sale_commission_persons(){
        return $this->unifiedMasterRelation('hasOne','App\Models\SalePerson','id','saleperson','sale_commission_persons','saleperson_id');
    }

    

    public function PaymentOptions(){
        return $this->unifiedMasterRelation('hasOne','App\Models\PaymentOption','id','payment_options','PaymentOptions','payment_option');
    }

    public function getOutstandingBalance(){
        $principal_original_sum = 0.00;
        $principal_sum = 0;
        $payment_schedule = LoanCalculate::loan_schedule($this->schedule, $this->start_date)[0];
        foreach ($payment_schedule as $p) {
            $principal_original_sum += $p[3];
        }
        $repayment = $this->payment;
        foreach($repayment as $pay){
            $principal_sum += $pay->paid_principal;
        }
        return $principal_original_sum - $principal_sum;
    }

    public function getLastAction(){
        if (!$this->lastActionTmp) {
            $action = CollectionAction::getListActionByLoanId($this->id, 1);
            if ($action) {
                $this->lastActionTmp = $action[0];
            }
        }
        return $this->lastActionTmp;
    }

    public function disburse_user(){
        if ($this->unifiedReadSelected()) return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceActorRead','disburse_byuserid','actor_reads','disburse_user');
        return $this->belongsTo('App\Models\User','disburse_byuserid');
    }

    public function SaleRepresentative(){
        return $this->unifiedMasterRelation('hasOne','App\Models\SalePerson','id','saleperson','SaleRepresentative','sale_person');
    }
	public function sale_person_parent_lavel1(){
        return $this->unifiedMasterRelation('hasOne','App\Models\SalePerson','id','saleperson','sale_person_parent_lavel1','sale_person_parent_l1');
    }
	public function sale_person_parent_lavel2(){
        return $this->unifiedMasterRelation('hasOne','App\Models\SalePerson','id','saleperson','sale_person_parent_lavel2','sale_person_parent_l2');
    }
    public function commission_withdrawal_transaction(){
        return $this->unifiedMasterRelation('hasMany','App\Models\CommissionWithdrawalTransaction','loan_id','commission_withdrawal_transaction','commission_withdrawal_transaction');
    }

}
