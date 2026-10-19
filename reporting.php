<?php
declare(strict_types=1);
function serviceIncomeReport(array $records,string $from,string $to): array {
 $groups=[];$billed=0;$collected=0;$count=0;
 foreach($records as $record){
  if($record['type']!=='invoices')continue;$d=is_array($record['data'])?$record['data']:json_decode($record['data'],true);
  if(($d['status']??'')==='Cancelled'||($d['date']??'')<$from||($d['date']??'')>$to)continue;
  $items=json_decode($d['line_items']??'[]',true);$lines=[];
  if(is_array($items)&&$items){foreach($items as $item){$cents=(int)round((float)($item['price']??0)*100)*(int)($item['quantity']??1);$lines[]=['name'=>$item['service']??'Unspecified service','quantity'=>(int)($item['quantity']??1),'category'=>$item['category']??'','cents'=>max(0,$cents)];}}
  elseif(trim($d['charges']??'')!==''){foreach(preg_split('/\R/',$d['charges']) as $line){if(trim($line)==='')continue;$parts=explode('|',$line);$amount=array_pop($parts);$lines[]=['name'=>trim(implode('|',$parts)),'quantity'=>1,'category'=>'Legacy charges','cents'=>max(0,(int)round((float)$amount*100))];}}
  else{$lines[]=['name'=>($d['description']??'')?:'Unspecified service','quantity'=>1,'category'=>'Legacy charges','cents'=>max(0,(int)round(((float)($d['amount']??0)-(float)($d['takehome']??0))*100))];}
  $extra=max(0,(int)round((float)($d['takehome']??0)*100));if($extra)$lines[]=['name'=>'Additional take-home medicines','quantity'=>1,'category'=>'Take Home Medicines','cents'=>$extra];
  $total=array_sum(array_column($lines,'cents'));$paid=($d['initial']??'')!==''?(int)round((float)$d['initial']*100):(($d['status']??'')==='Paid'?$total:0);$paid=max(0,min($total,$paid));
  $shares=[];$allocated=0;foreach($lines as $i=>$item){$raw=$total>0?$paid*$item['cents']/$total:0;$shares[$i]=['value'=>(int)floor($raw),'fraction'=>$raw-floor($raw)];$allocated+=(int)floor($raw);}
  $order=array_keys($lines);usort($order,fn($a,$b)=>$shares[$b]['fraction']<=>$shares[$a]['fraction']);for($i=0;$i<$paid-$allocated;$i++)$shares[$order[$i%count($order)]]['value']++;
  foreach($lines as $i=>$item){$key=strtolower(trim($item['name']));if(!isset($groups[$key]))$groups[$key]=['service'=>trim($item['name']),'category'=>$item['category']?:'Uncategorized','quantity'=>0,'billed'=>0,'collected'=>0,'invoices'=>[]];$groups[$key]['quantity']+=$item['quantity'];$groups[$key]['billed']+=$item['cents'];$groups[$key]['collected']+=$shares[$i]['value'];$groups[$key]['invoices'][$record['id']]=true;}
  $billed+=$total;$collected+=$paid;$count++;
 }
 foreach($groups as &$group){$group['invoice_count']=count($group['invoices']);unset($group['invoices']);$group['balance']=$group['billed']-$group['collected'];}unset($group);
 $groups=array_values($groups);usort($groups,fn($a,$b)=>$b['collected']<=>$a['collected']);
 return ['from'=>$from,'to'=>$to,'services'=>$groups,'totals'=>['billed'=>$billed,'collected'=>$collected,'balance'=>$billed-$collected,'invoices'=>$count],'currency'=>'PHP'];
}
