<?php
return array('enabled'=>env('UNIFIED_ORIGINAL_CUSTOMERS_ENABLED',false),'writes_enabled'=>env('UNIFIED_ORIGINAL_CUSTOMER_WRITES_ENABLED',false),'active'=>false,
'database'=>array('driver'=>'mysql','host'=>env('UNIFIED_DB_HOST','127.0.0.1'),'port'=>env('UNIFIED_DB_PORT','3309'),'database'=>env('UNIFIED_DB_DATABASE','bsunits_beloan_final'),'username'=>'unified_beloan_customer_ui','password'=>env('UNIFIED_ORIGINAL_CUSTOMER_DB_PASSWORD',''),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'beloan_ui_','strict'=>true));
