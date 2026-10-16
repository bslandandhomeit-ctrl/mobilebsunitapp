<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DealerBanks extends Model{
    use UnifiedMasterReadRelations;
  protected $table = 'dealer_banks';
  protected $hidden = ['created_at', 'updated_at'];

  public function bank(){
  	return $this->unifiedMasterRelation('belongsTo','App\Models\Bank','bank_id','banks','bank');
  }

  public function dealer(){
  	return $this->unifiedMasterRelation('belongsTo','App\Models\Dealer','dealer_id','dealers','dealer');
  }
}
