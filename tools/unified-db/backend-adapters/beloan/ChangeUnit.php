<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChangeUnit extends Model {
    use UnifiedMasterReadRelations;
	protected $table = 'change_unit';
  	protected $hidden = ['deleted_at','created_at', 'updated_at'];
}
