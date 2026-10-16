<?php
namespace App\Models;
/** Explicit compatibility reads only; default original models are unchanged. */
trait UnifiedMasterReadRelations {
 protected function unifiedReadSelected(){return is_string($this->getTable())&&strpos($this->getTable(),'beloan_compat_')===0;}
 protected function unifiedMasterRelation($kind,$class,$key,$table,$name,$localKey=null){
  if(!$this->unifiedReadSelected()){if($kind==='belongsTo')return parent::belongsTo($class,$key,null,$name);return $this->$kind($class,$key,$localKey);}
  $related=new $class;$related->setConnection($this->getConnectionName());$related->setTable('beloan_compat_'.$table);
  if($kind==='belongsTo')return new \Illuminate\Database\Eloquent\Relations\BelongsTo($related->newQuery(),$this,$key,$related->getKeyName(),$name);
  $relation=$kind==='hasMany'?'Illuminate\Database\Eloquent\Relations\HasMany':'Illuminate\Database\Eloquent\Relations\HasOne';
  return new $relation($related->newQuery(),$this,$related->getTable().'.'.$key,$localKey?:$this->getKeyName());
 }
 protected function unifiedMasterManyRelation($class,$table,$pivot,$foreignKey,$otherKey,$name,$originalPivot,$originalForeignKey){
  if(!$this->unifiedReadSelected())return parent::belongsToMany($class,$originalPivot,$originalForeignKey,$otherKey,$name);
  $related=new $class;$related->setConnection($this->getConnectionName());$related->setTable('beloan_compat_'.$table);
  return new \Illuminate\Database\Eloquent\Relations\BelongsToMany($related->newQuery(),$this,'beloan_compat_'.$pivot,$foreignKey,$otherKey,$name);
 }
 public function newEloquentBuilder($query){return $this->unifiedReadSelected()?new UnifiedMasterReadBuilder($query):parent::newEloquentBuilder($query);}
 public function newInstance($attributes=array(),$exists=false){$instance=parent::newInstance($attributes,$exists);if($this->unifiedReadSelected()){$instance->setTable($this->getTable());$instance->setConnection($this->getConnectionName());}return $instance;}
 public function save(array $options=array()){
  if($this->unifiedReadSelected())throw new \LogicException('Shared master projections are read only; use the authoritative write adapter.');
  return parent::save($options);
 }
 public function delete(){
  if($this->unifiedReadSelected())throw new \LogicException('Shared master projections are read only.');
  return parent::delete();
 }
}
