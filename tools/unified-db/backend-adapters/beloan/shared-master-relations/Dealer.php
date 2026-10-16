<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dealer extends Model {
    use UnifiedMasterReadRelations;
  protected $table = 'dealers';
  protected $hidden = ['created_at', 'updated_at'];

  public function dealerBank(){
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

}
