<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bank extends Model {
    use UnifiedMasterReadRelations;
  protected $table = 'banks';
  protected $fillable = array(
    'id',
    'bank_name',
    'account_name',
    'account_number'
    );
  protected $hidden = ['created_at', 'updated_at'];

  public function dealerBank(){
  	return $this->hasMany('App\Models\DealerBanks','bank_id');
  }
  public function loanDealer(){
    return $this->hasMany('App\Models\LoanDealer','dealer_bank_id');
  }
}
