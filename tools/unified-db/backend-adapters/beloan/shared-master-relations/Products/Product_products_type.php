<?php namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;

class Product_products_type extends Model
{
    use \App\Models\UnifiedMasterReadRelations;
    protected $table = 'product_products_type';
    protected $hidden = ['created_at', 'updated_at'];
    public $timestamps = false;
}
