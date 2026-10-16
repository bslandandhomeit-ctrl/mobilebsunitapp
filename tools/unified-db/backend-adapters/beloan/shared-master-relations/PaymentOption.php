<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentOption extends Model {
    use \App\Models\UnifiedMasterReadRelations;

	protected $table = 'payment_options';
	protected $hidden = [
		'created_at','updated_at'
	];

	public function UnitType(){
		return $this->unifiedMasterRelation('belongsTo','App\Models\UnitType','unit_type_id','unit_types','UnitType');
	}
}
