<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 5/26/2015
 * Time: 8:25 AM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class Role extends Model{
    use UnifiedMasterReadRelations;
    protected $table = 'roles';
    protected $hidden = ['created_at', 'updated_at'];
    public function staff(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Staff','staff_id','staffs','staff');
    }
    final function users()
    {
        if ($this->unifiedReadSelected()) return $this->unifiedMasterRelation('hasMany','App\Models\UnifiedFinanceActorRead','role_id','actor_reads','users');
        return $this->hasMany('App\Models\User','role_id');
    }
} 