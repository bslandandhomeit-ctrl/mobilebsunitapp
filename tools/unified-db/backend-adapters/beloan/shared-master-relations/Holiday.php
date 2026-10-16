<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model {
    use \App\Models\UnifiedMasterReadRelations;

	protected $table = "holiday";
	public $timestamps = false;

}