<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use \App\Models\UnifiedMasterReadRelations;

    protected $hidden = ['created_at', 'updated_at'];

    public function category()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\ProductCategory','category_id','product_category','category');
    }
    public function prod_prod_type()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\Products\Product_products_type','product_type_id','product_products_type','prod_prod_type');
    }

    public function brand()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\ProductBrand','brand_id','product_brand','brand');
    }

    public function dealer()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\Dealer','dealer_id','dealers','dealer');
    }

    public function record()
    {
        return $this->hasMany('App\Models\ProductRecord', 'product_id');
    }

    public function loan()
    {
        return $this->hasOne('App\Models\Loan', 'product_id');
    }
    public function product_types()
    {
        return $this->unifiedMasterRelation('belongsTo','App\Models\Products\Product_type','product_type_id','product_type','product_types');
    }
}
