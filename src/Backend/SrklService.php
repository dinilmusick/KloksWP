<?php
namespace KloksWP\Backend;
final class SrklService {
    public function resolve(string $service,string $operation):array{return ['service'=>$service,'operation'=>$operation,'endpoint'=>rest_url('klokswp/v1/'.$service.'/'.$operation)];}
}