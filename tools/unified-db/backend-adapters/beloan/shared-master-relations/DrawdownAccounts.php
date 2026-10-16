<?php 
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DrawdownAccounts extends Model {
    use UnifiedMasterReadRelations;
    public function getBranchAttribute($value){return $this->unifiedReadSelected()&&ctype_digit((string)$value)?(int)$value:$value;}

    protected $table = 'drawdown_account';
    protected $hidden = ['created_at', 'updated_at'];

    public function client(){
        if(strpos($this->getTable(),'beloan_compat_')===0)return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceCustomerRead','client_id','clients','client');
        return $this->belongsTo('App\Models\Client', 'client_id');
    }

    public function Client_cbc_info(){
        return $this->belongsTo('App\Models\Cbc\Client_cbc_info', 'client_id');
    }

    public function coa() {
        return $this->unifiedMasterRelation('belongsTo','App\Models\CoaCategory','coa_id','coa_categories','coa');
    }

    public function currency_tbl(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Currency','currency','currency','currency_tbl');
    }
    public function company_branch(){
        if(strpos($this->getTable(),'beloan_compat_')===0)return $this->unifiedMasterRelation('belongsTo','App\Models\CompanyBranch','branch','company_branch','company_branch');
        return $this->belongsTo('App\Models\CompanyBranch','branch','branch_code');
    }
    public function journal_detail(){
        return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','coa_id','journal_detail','journal_detail', 'coa_id');
    }
    public function projects(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Project','project_id','projects','projects');
    }
    public function unitType(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\UnitType','unit_type_id','unit_types','unitType');
    }
    public function units(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Unit','unit_id','units','units');
    }
    public function loans(){
        return $this->unifiedMasterRelation('hasMany','App\Models\Loan','drawdown_acc','loans','loans','account_no');
    }
}
