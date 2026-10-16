<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use UnifiedMasterReadRelations;
    use SoftDeletes;

    protected $table = 'units';

    protected $guarded = [];

    protected $hidden = ['deleted_at','created_at', 'updated_at'];

    public function UnitType()
    {
	    return $this->unifiedMasterRelation('belongsTo','App\Models\UnitType','unit_type_id','unit_types','UnitType');
    }
}
