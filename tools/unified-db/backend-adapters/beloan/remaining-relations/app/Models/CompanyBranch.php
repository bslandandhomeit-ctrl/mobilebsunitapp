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
        return $this->unifiedMasterRelation('belongsTo','App\Models\Staff','staff_id','staffs','staff');
    }
    public function history(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\JobHistory','history_id','job_history','history');
    }
    public function role(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Role','role_id','roles','role');
    }
    public function getBranch() {
        return self::all();
    }

    public function projects(){
        return $this->unifiedMasterRelation('hasOne','App\Models\Project',strpos($this->getTable(),'beloan_compat_')===0?'accounting_branch_id':'company_id','projects','projects');
    }
} 