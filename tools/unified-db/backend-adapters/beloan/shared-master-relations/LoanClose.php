<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LoanClose extends Model{
    use UnifiedMasterReadRelations;
	protected $table="loan_close";
    protected $hidden = ['created_at', 'updated_at'];

	public function loan(){
		return $this->unifiedMasterRelation('belongsTo','App\Models\Loan','loan_id','loans','loan');
	}
}