<?php
/**
 * Created by PhpStorm.
 * User: SOPHEAK
 * Date: 6/15/2015
 * Time: 2:30 PM
 */

namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class LoanCostFee extends Model{
    use UnifiedMasterReadRelations;
    protected $table = 'loan_cost_fees';
    protected $hidden = ['created_at', 'updated_at'];
    public function loan(){
        return $this->unifiedMasterRelation('belongsTo','App\Models\Loan','loan_id','loans','loan');
    }
} 