<?php
declare(strict_types=1);
require __DIR__.'/reporting.php';
function verifyReport(bool $test,string $message): void {if(!$test)throw new RuntimeException($message);}
$records=[['id'=>1,'type'=>'invoices','data'=>['date'=>'2026-10-19','status'=>'Partially paid','initial'=>'300','amount'=>'900','line_items'=>json_encode([['service'=>'Consultation','quantity'=>1,'price'=>600,'category'=>'Professional Fee'],['service'=>'Medicine: Test','quantity'=>10,'price'=>30,'category'=>'Oral Medicines']])]],['id'=>2,'type'=>'invoices','data'=>['date'=>'2026-10-19','status'=>'Cancelled','amount'=>'100','description'=>'Ignore']],['id'=>3,'type'=>'invoices','data'=>['date'=>'2026-09-01','status'=>'Paid','amount'=>'100','description'=>'Older']]];
$report=serviceIncomeReport($records,'2026-10-01','2026-10-31');
verifyReport($report['totals']===['billed'=>90000,'collected'=>30000,'balance'=>60000,'invoices'=>1],'Totals and filters');
verifyReport($report['services'][0]['service']==='Consultation'&&$report['services'][0]['collected']===20000,'Proportional service collection');
verifyReport($report['services'][1]['quantity']===10&&$report['services'][1]['collected']===10000,'Medicine quantity and income');
$rounding=[['id'=>4,'type'=>'invoices','data'=>['date'=>'2026-10-19','initial'=>'0.01','line_items'=>json_encode([['service'=>'A','quantity'=>1,'price'=>1],['service'=>'B','quantity'=>1,'price'=>1],['service'=>'C','quantity'=>1,'price'=>1]])]]];
$small=serviceIncomeReport($rounding,'2026-10-01','2026-10-31');verifyReport(array_sum(array_column($small['services'],'collected'))===1,'Rounding reconciles to receipt');
verifyReport(serviceIncomeReport([],'2026-10-01','2026-10-31')['totals']['collected']===0,'Empty reports');
echo "Passed: date filtering, cancelled invoices excluded, service and medicine totals, proportional partial payments, cent rounding, empty reports.\n";
