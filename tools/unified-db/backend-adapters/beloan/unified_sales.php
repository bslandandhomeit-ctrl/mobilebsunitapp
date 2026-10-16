<?php
return array(
 'loans_enabled'=>env('UNIFIED_LOAN_READS_ENABLED',false),
 'loan_database'=>array('driver'=>'mysql','host'=>env('UNIFIED_DB_HOST','127.0.0.1'),'port'=>env('UNIFIED_DB_PORT','3309'),'database'=>env('UNIFIED_DB_DATABASE','bsunits_beloan_final'),'username'=>'unified_beloan_loan_reader','password'=>env('UNIFIED_LOAN_DB_PASSWORD',''),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true),
 'customer_writes_enabled'=>env('UNIFIED_CUSTOMER_WRITES_ENABLED',false),
 'write_database'=>array('driver'=>'mysql','host'=>env('UNIFIED_DB_HOST','127.0.0.1'),'port'=>env('UNIFIED_DB_PORT','3309'),
 'database'=>env('UNIFIED_DB_DATABASE','bsunits_beloan_final'),'username'=>'unified_beloan_customer_writer','password'=>env('UNIFIED_CUSTOMER_DB_PASSWORD',''),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true),
 'customers_enabled'=>env('UNIFIED_CUSTOMER_READS_ENABLED',false),
 'enabled'=>env('UNIFIED_SALES_READS_ENABLED',false),
 'database'=>array('driver'=>'mysql','host'=>env('UNIFIED_DB_HOST','127.0.0.1'),'port'=>env('UNIFIED_DB_PORT','3309'),
 'database'=>env('UNIFIED_DB_DATABASE','bsunits_beloan_final'),'username'=>env('UNIFIED_SALES_DB_USERNAME','unified_beloan_sales_reader'),
 'password'=>env('UNIFIED_SALES_DB_PASSWORD',''),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true)
);
