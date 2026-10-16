<?php
return array('enabled'=>env('UNIFIED_POSTING_READS_ENABLED',false),'database'=>array(
'driver'=>'mysql','host'=>env('UNIFIED_DB_HOST','127.0.0.1'),'port'=>env('UNIFIED_DB_PORT','3309'),
'database'=>env('UNIFIED_DB_DATABASE','bsunits_beloan_final'),'username'=>'unified_beloan_posting_reader',
'password'=>env('UNIFIED_POSTING_DB_PASSWORD',''),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true));
