<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductBrand extends Model {
    use \App\Models\UnifiedMasterReadRelations;
	protected $table = "product_brand";
	public $timestamps = false;
	public function product(){
		return $this->belongsTo('App\Models\Product','product_id');
	}
}
