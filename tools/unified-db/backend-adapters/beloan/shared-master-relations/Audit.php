<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model {
    use UnifiedMasterReadRelations { save as protected compatibilityReadSave; }

    public function save(array $options=array()){if(config('unified_customer_ui.enabled')&&config('unified_customer_ui.active')&&$this->audit_id==='')$this->audit_id=0;return $this->compatibilityReadSave($options);}
    public function getConnection(){return static::resolveConnection($this->getConnectionName());}
    public function getConnectionName(){if($this->unifiedReadSelected())return parent::getConnectionName();if(config('unified_customer_ui.enabled')&&config('unified_customer_ui.active')){config(array('database.connections.unified_customer_ui'=>config('unified_customer_ui.database')));return 'unified_customer_ui';}return parent::getConnectionName();}
  	protected $table = 'audit';

	public function audit1()
	{
		if($this->unifiedReadSelected())return $this->unifiedMasterRelation('hasOne','App\Models\UnifiedFinanceActorRead','id','actor_reads','audit1','user_id');
        return $this->hasOne('App\Models\User', 'id', 'user_id');
	}

	public function audit2()
	{
		if($this->unifiedReadSelected())return $this->unifiedMasterRelation('hasOne','App\Models\UnifiedFinanceActorRead','id','actor_reads','audit2','audit_id');
        return $this->hasOne('App\Models\User', 'id', 'audit_id');
	}
}
