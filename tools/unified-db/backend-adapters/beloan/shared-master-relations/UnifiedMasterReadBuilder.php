<?php
namespace App\Models;
class UnifiedMasterReadBuilder extends \Illuminate\Database\Eloquent\Builder {
 public function getModels($columns=array('*')){$results=$this->query->get($columns);$models=array();foreach($results as $row)$models[]=$this->model->newFromBuilder($row,$this->model->getConnectionName());return $models;}
}
