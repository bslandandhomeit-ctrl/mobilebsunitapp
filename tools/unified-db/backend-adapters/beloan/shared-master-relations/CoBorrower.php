<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoBorrower extends Model {
    use UnifiedMasterReadRelations;
	protected $table = 'co_borrowers';
  	protected $hidden = ['created_at', 'updated_at'];
  	public function Clients(){
  		if ($this->unifiedReadSelected()) return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceCustomerRead','customer_id','clients','Clients');
        return $this->belongsTo('App\Models\Client','customer_id');
  	}
}
