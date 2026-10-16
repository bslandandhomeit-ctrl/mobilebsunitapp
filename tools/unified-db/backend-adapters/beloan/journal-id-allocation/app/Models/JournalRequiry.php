<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;
class JournalRequiry extends Model{
    use UnifiedMasterReadRelations;
protected $table = 'journal_requiry';
    protected $hidden = ['created_at', 'updated_at'];

    /** Allocate the header ID in MySQL, then retain the original eight-digit invoice. */
    public function saveWithGeneratedInvoice()
    {
        if ($this->exists) throw new \LogicException('Generated invoices require a new journal.');
        $journal = $this;
        return $this->getConnection()->transaction(function () use ($journal) {
            if (!$journal->save()) throw new \RuntimeException('Journal header was not saved.');
            $journal->invoice_number = str_pad($journal->id, 8, '0', STR_PAD_LEFT);
            if (!$journal->save()) throw new \RuntimeException('Journal invoice was not saved.');
            return true;
        });
    }


	public function transaction(){
		return $this->unifiedMasterRelation('belongsTo','App\Models\TransactionsRequiry','tran_id','transactions_requiry','transaction');
	}
	public function user(){
		if($this->unifiedReadSelected())return $this->unifiedMasterRelation('belongsTo','App\Models\UnifiedFinanceActorRead','user_id','actor_reads','user');
        return $this->belongsTo('App\Models\User','user_id');
	}
	public function detail() {

		return $this->unifiedMasterRelation('hasMany','App\Models\JournalDetail','journal_id','journal_detail','detail');
	}

	final function AccountCustomer() {

		return $this->hasOne('App\Models\AccountCustomer', 'id','ref_name_id');
	}
	final function vendor () {

		return $this->hasOne('App\Models\Vendor', 'id','ref_name_id');
	}

	public function audit()
    {
        return $this->unifiedMasterRelation('hasMany','App\Models\Audit','tbl_id','audit','audit')->where('tbl', 'journal_requiry');
    }
}