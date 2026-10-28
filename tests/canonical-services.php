<?php
require __DIR__.'/../vendor/autoload.php';
use KloksWP\Services\{ServiceA,ServiceB,ServiceC,ServiceD,ServiceFactory};
function expect($v,$m){if(!$v)throw new RuntimeException($m);}
$a=ServiceA::create();$a->start();expect($a->call('add',['a'=>2,'b'=>3])['result']===5.0,'add');
$b=ServiceB::create();$b->start();expect($b->call('increment',['delta'=>2])['count']===2,'counter');
$c=ServiceC::create();$c->start();$seen=null;$c->on('reading',function($p)use(&$seen){$seen=$p;});$c->call('publishReading',['metric'=>'cpu','value'=>4]);expect($seen['metric']==='cpu','event');
$d=ServiceD::create();$d->start();expect($d->call('uppercase',['text'=>'hello'])['result']==='HELLO','protocol service');
expect(ServiceFactory::create('kloks.service.a') instanceof KrnlWP\Service\WordPressService,'factory');
echo "canonical service tests: OK
";