<?php namespace App\Models\Products;

use Illuminate\Database\Eloquent\Model;

class Product_type extends Model
{
    use \App\Models\UnifiedMasterReadRelations;
    protected $table = 'product_type';
    protected $hidden = ['created_at', 'updated_at'];


}
