<?php
namespace KloksWP\Functions;
final class LRUCache {
 private array $data=[]; public function __construct(private int $maxSize=100){if($maxSize<1)throw new \InvalidArgumentException('maxSize must be >= 1');}
 public function get(mixed $key):mixed{if(!array_key_exists($key,$this->data))return null;$v=$this->data[$key];unset($this->data[$key]);$this->data[$key]=$v;return $v;}
 public function set(mixed $key,mixed $value):void{if(array_key_exists($key,$this->data))unset($this->data[$key]);elseif(count($this->data)>=$this->maxSize)array_shift($this->data);$this->data[$key]=$value;}
 public function delete(mixed $key):bool{if(!array_key_exists($key,$this->data))return false;unset($this->data[$key]);return true;} public function clear():void{$this->data=[];} public function size():int{return count($this->data);}
}
final class TTLCache {
 private array $data=[]; public function __construct(private int $ttlMs){if($ttlMs<0)throw new \InvalidArgumentException('ttlMs must be >= 0');}
 public function get(mixed $key):mixed{if(!isset($this->data[$key]))return null;if(microtime(true)*1000>$this->data[$key]['expires']){unset($this->data[$key]);return null;}return $this->data[$key]['value'];}
 public function set(mixed $key,mixed $value):void{$this->data[$key]=['value'=>$value,'expires'=>microtime(true)*1000+$this->ttlMs];}
 public function delete(mixed $key):bool{if(!isset($this->data[$key]))return false;unset($this->data[$key]);return true;} public function clear():void{$this->data=[];}
}
final class FileCache {
 public function __construct(private string $cacheDir){if(!is_dir($cacheDir)&&!@mkdir($cacheDir,0777,true)&&!is_dir($cacheDir))throw new \RuntimeException('Unable to create cache directory');}
 private function file(string $key):string{return $this->cacheDir.'/'.rawurlencode($key).'.json';}
 public function get(string $key):mixed{$f=$this->file($key);if(!is_file($f))return null;$v=@json_decode((string)file_get_contents($f),true);return json_last_error()===JSON_ERROR_NONE?$v:null;}
 public function set(string $key,mixed $value):void{@file_put_contents($this->file($key),json_encode($value,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT),LOCK_EX);}
 public function delete(string $key):void{@unlink($this->file($key));}
 public function clear():void{foreach(glob($this->cacheDir.'/*.json')?:[] as $f)@unlink($f);}
}
final class CacheFunctions {
 public static function swr(TTLCache $cache,mixed $key,callable $fetch):mixed{$v=$cache->get($key);if($v!==null){$fresh=$fetch();$cache->set($key,$fresh);return $v;} $v=$fetch();$cache->set($key,$v);return $v;}
}