<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 6/9/2015
 * Time: 3:55 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class FeeCharge extends Model{
    use UnifiedMasterReadRelations;
    protected $table = 'fee_charges';
    protected $primaryKey = 'id';
    protected $hidden = ['created_at', 'updated_at'];
} 