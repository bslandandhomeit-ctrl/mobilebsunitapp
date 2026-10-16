<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientLoanAccounts extends Model {
    use UnifiedMasterReadRelations;
    public function getBranchAttribute($value){return $this->unifiedReadSelected()&&ctype_digit((string)$value)?(int)$value:$value;}
    protected $table = 'client_loan_accounts';
    protected $hidden = ['created_at', 'updated_at'];
    protected $guarded = [];
    public function client(){
        if(strpos($this->getTable(),'beloan_compat_')===0)return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceCustomerRead','client_id','clients','client');
        return $this->belongsTo('App\Models\Client','client_id');
    }

    public function loan()
    {
        return $this->unifiedMasterRelation('hasOne','App\Models\Loan','loan_account_id','loans','loan','id')->orderBy(($this->unifiedReadSelected()?'beloan_compat_loans':'loans').'.id', 'DESC')->whereIn(($this->unifiedReadSelected()?'beloan_compat_loans':'loans').'.status',[3,8,5,6,9,10]);
    }
    public function journal_detail_coa()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','journal_detail_coa', 'coa_id');
    }
    public function journal_detail_air()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','journal_detail_air', 'air_id');
    }
    public function journal_detail_int_inc()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','journal_detail_int_inc', 'int_inc_id');
    }
    public function journal_detail_sus()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','journal_detail_sus', 'sus_id');
    }
    public function schedule()
    {
    	return $this->hasMany('App\Models\RepaymentSchedule','loan_id');
    }
    public function currencies()
    {
        return $this->unifiedMasterRelation('hasOne','App\Models\Currency','id','currency','currencies','currency');
    }
	public function get_branch()
    {
        if($this->unifiedReadSelected())return $this->unifiedMasterRelation('hasOne','App\Models\CompanyBranch','id','company_branch','get_branch','branch');
        return $this->hasOne('App\Models\CompanyBranch', 'branch_code', 'branch');
    }
    public function projects(){
        return $this->unifiedMasterRelation('hasOne','App\Models\Project','id','projects','projects','project_id');
    }
    public function drawdown_acc(){
        return $this->unifiedMasterRelation('hasOne','App\Models\DrawdownAccounts','client_loan_id','drawdown_account','drawdown_acc','id');
    }
}
