<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of JobHistory
 *
 * @author theary
 */
class JobHistory extends Model {
    use UnifiedMasterReadRelations;
    //put your code here
    protected $table = "job_history";
    public function branch(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\CompanyBranch','branch_id','company_branch','branch');
    }
    public function role(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Role','role_id','roles','role');
    }
}