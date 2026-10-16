<?php namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Audit extends Model {

    public function save(array $options=array()){if(config('unified_customer_ui.enabled')&&config('unified_customer_ui.active')&&$this->audit_id==='')$this->audit_id=0;return parent::save($options);}
    public function getConnection(){return static::resolveConnection($this->getConnectionName());}
    public function getConnectionName(){if(config('unified_customer_ui.enabled')&&config('unified_customer_ui.active')){config(array('database.connections.unified_customer_ui'=>config('unified_customer_ui.database')));return 'unified_customer_ui';}return parent::getConnectionName();}
  	protected $table = 'audit';

	public function audit1()
	{
		return $this->hasOne('App\Models\User', 'id', 'user_id');
	}

	public function audit2()
	{
		return $this->hasOne('App\Models\User', 'id', 'audit_id');
	}
}
