<?php
namespace KloksWP\Backend;
final class ApiService {
    public function register(string $route,callable $callback,string $namespace='klokswp/v1'):void{register_rest_route($namespace,$route,['methods'=>'POST','callback'=>$callback,'permission_callback'=>'__return_true']);}
}