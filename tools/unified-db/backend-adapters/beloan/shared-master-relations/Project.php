<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model {
    use UnifiedMasterReadRelations;
  protected $table = 'projects';
  protected $hidden = ['created_at', 'updated_at'];

  public function projectBank(){
  	return $this->hasMany('App\Models\DealerBanks','dealer_id');
  }
  public function loans(){
  	return $this->hasMany('App\Models\LoanDealer','dealer_id');
  }
  public function product(){
  	return $this->hasMany('App\Models\Product','dealer_id');
  }
  public function bank()
  {
      return $this->belongsToMany('App\Models\Bank','dealer_banks','dealer_id','bank_id');
  }
  public function Representative(){
    return $this->belongsTo('App\Models\Representative','sale_representative_id');
  }
  public function Banks(){
    return $this->belongsTo('App\Models\Bank','bank_account');
  }
  public function Company(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\CompanyBranch',strpos($this->getTable(),'beloan_compat_')===0?'accounting_branch_id':'company_id','company_branch','Company');
  }

}
