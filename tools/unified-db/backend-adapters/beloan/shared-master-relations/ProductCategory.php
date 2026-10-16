<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model {
    use \App\Models\UnifiedMasterReadRelations;
	protected $table = "product_category";
	public $timestamps = false;

	public function products(){
		return $this->hasMany('App\Models\Product','category_id','id');
	}
}
