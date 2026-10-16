<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanDealer extends Model {
    use UnifiedMasterReadRelations;
  protected $table = 'loan_dealers';
  protected $hidden = ['created_at', 'updated_at'];
  
  public function dealer(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\Dealer','dealer_id','dealers','dealer');
  }
  public function loan(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\Loan','loan_id','loans','loan');
  }
  public function bank(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\Bank','bank_id','banks','bank');
  }
}
