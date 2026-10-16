<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalDetail extends Model{
    use UnifiedMasterReadRelations;
	protected $table = 'journal_detail';
    public $timestamps = false;

    public function journal(){
    	return $this->unifiedMasterRelation('belongsTo','App\Models\JournalRequiry','journal_id','journal_requiry','journal');
    }
    public function account(){
    	return $this->unifiedMasterRelation('belongsTo','App\Models\CoaCategory','coa_id','coa_categories','account');
    }

    public function draw_act()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\DrawdownAccounts','coa_id','drawdown_account','draw_act');
    }
    public function branch()
    {
        return $this->unifiedMasterRelation('hasOne','App\Models\CompanyBranch','branch_code','company_branch','branch','branch_code');
    }

}