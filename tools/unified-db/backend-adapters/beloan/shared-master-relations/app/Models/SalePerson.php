<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePerson extends Model {
    use UnifiedMasterReadRelations;

	protected $table = 'saleperson';
  	protected $hidden = ['created_at', 'updated_at'];

  	public function SaleTeam(){
  		if ($this->unifiedReadSelected()) return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceActorRead','sale_team_id','actor_reads','SaleTeam');
        return $this->belongsTo('App\Models\User','sale_team_id','id');
  	}
  	public function Nationalities(){
  		return $this->belongsTo('App\Models\Country\CountryDescription','national_id','id');
  	}
	public function parent(){
        return $this->unifiedMasterRelation('hasOne','App\Models\SalePerson','id','saleperson','parent','parent_id');
    }

}
