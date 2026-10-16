<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model {
    use UnifiedMasterReadRelations;
  protected $table = 'projects';
  protected $hidden = ['created_at', 'updated_at'];

  public function projectBank(){
  	return $this->unifiedMasterRelation('hasMany','App\Models\DealerBanks','dealer_id','dealer_banks','projectBank',$this->unifiedReadSelected()?'source_project_id':null);
  }
  public function loans(){
  	return $this->unifiedMasterRelation('hasMany','App\Models\LoanDealer','dealer_id','loan_dealers','loans',$this->unifiedReadSelected()?'source_project_id':null);
  }
  public function product(){
  	return $this->unifiedMasterRelation('hasMany','App\Models\Product','dealer_id','products','product',$this->unifiedReadSelected()?'source_project_id':null);
  }
  public function bank()
  {
      return $this->unifiedMasterManyRelation('App\Models\Bank','banks','project_banks','project_id','bank_id','bank','dealer_banks','dealer_id');
  }
  public function Representative(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\Representative','sale_representative_id','representatives','Representative');
  }
  public function Banks(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\Bank','bank_account','banks','Banks');
  }
  public function Company(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\CompanyBranch',strpos($this->getTable(),'beloan_compat_')===0?'accounting_branch_id':'company_id','company_branch','Company');
  }

}
