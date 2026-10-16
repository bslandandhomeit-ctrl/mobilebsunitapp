<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Staff extends Model {
    use UnifiedMasterReadRelations;
  protected $table = 'staffs';
  protected $hidden = ['created_at', 'updated_at'];
  public function history(){
    return $this->unifiedMasterRelation('hasMany','App\Models\JobHistory','staff_id','job_history','history');
  }
  public function branch(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\CompanyBranch','branch_id','company_branch','branch');
  }
  public function role(){
    return $this->unifiedMasterRelation('belongsTo','App\Models\Role','role_id','roles','role');
  }
}
