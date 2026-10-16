<?php namespace App\Models;
/**
 * Created by PhpStorm.
 * User: ChamroeunDeab
 * Date: 6/30/2015
 * Time: 4:24 PM
 */

use Illuminate\Database\Eloquent\Model;

class TransactionsRequiry extends Model {
    use UnifiedMasterReadRelations;
    protected $table = 'transactions_requiry';
    protected $hidden = ['created_at','updated_at'];
    public $timestamps = false;

    public function repayment(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\LoanPayments','repayment_id','loan_payments','repayment');
    }

    public function charge()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\FeeCharge','fee_charge_id','fee_charges','charge');
    }

    public function cost()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\LoanCostFee','cost_fee_id','loan_cost_fees','cost');
    }

    public function loan()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\Loan','loan_id','loans','loan');
    }
    public function journal(){
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalRequiry','tran_id','journal_requiry','journal');
    }
    public function user(){
        if($this->unifiedReadSelected())return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceActorRead','user_id','actor_reads','user');
        return $this->belongsTo('App\Models\User','user_id');
    }
    public function company_branch(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\CompanyBranch','branch_id','company_branch','company_branch');
    }

    public function audit()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\Audit','tbl_id','audit','audit')->where('tbl', 'transactions_requiry');
    }
}
