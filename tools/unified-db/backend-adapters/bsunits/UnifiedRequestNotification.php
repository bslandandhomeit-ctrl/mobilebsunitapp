<?php
namespace App;
class UnifiedRequestNotification extends \Illuminate\Notifications\DatabaseNotification {
 protected $table='unified_request_notifications';
 public function getConnectionName(){return !$this->connection && config('unified_catalog.notification_reads_enabled',false)?(config('unified_catalog.request_writes_enabled',false)?\Illuminate\Support\Facades\DB::getDefaultConnection():'unified_notifications'):parent::getConnectionName();}
}
