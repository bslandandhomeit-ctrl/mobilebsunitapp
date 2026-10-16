<?php
namespace App;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
class UnifiedOverdueRecipient extends Model {
 use Notifiable;
 protected $table='unified_overdue_recipients';
 public $timestamps=false;
 public function getMorphClass(){return 'App\\User';}
 public function notifications(){return $this->morphMany(UnifiedRequestNotification::class,'notifiable')->orderBy('created_at','desc');}
 public function save(array $options=[]){throw new \LogicException('Overdue recipient projection is read-only.');}
 public function delete(){throw new \LogicException('Overdue recipient projection is read-only.');}
}
