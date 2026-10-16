<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 6/4/2015
 * Time: 11:22 AM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class CompanyBranch extends Model {
    use UnifiedMasterReadRelations;
    protected $table = 'company_branch';
    protected $hidden = ['created_at', 'updated_at'];
    public function staff(){
        return $this->belongsTo('App\Models\Staff','staff_id');
    }
    public function history(){
        return $this->belongsTo('App\Models\JobHistory','history_id');
    }
    public function role(){
        return $this->belongsTo('App\Models\Role','role_id');
    }
    public function getBranch() {
        return self::all();
    }

    public function projects(){
        return $this->unifiedMasterRelation('hasOne','App\Models\Project',strpos($this->getTable(),'beloan_compat_')===0?'accounting_branch_id':'company_id','projects','projects');
    }
} 