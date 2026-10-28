<?php
namespace KloksWP\Functions;
final class ObjectFunctions {
 public static function deepClone(mixed $v):mixed{if(is_array($v)){$o=[];foreach($v as $k=>$x)$o[$k]=self::deepClone($x);return $o;}if(is_object($v)){return unserialize(serialize($v));}return $v;}
 public static function deepMerge(mixed $base,...$overrides):mixed{$r=self::deepClone($base);foreach($overrides as $o){if(!is_array($o))continue;foreach($o as $k=>$v){if($v===null){$r[$k]=null;}elseif(is_array($v)&&isset($r[$k])&&is_array($r[$k]))$r[$k]=self::deepMerge($r[$k],$v);else$r[$k]=self::deepClone($v);}}return $r;}
 public static function deepEqual(mixed $a,mixed $b):bool{return $a===$b||serialize($a)===serialize($b);}
 public static function diff(mixed $a,mixed $b):array{$added=[];$removed=[];$changed=[];if(!is_array($a)||!is_array($b))return compact('added','removed','changed');foreach($b as $k=>$v){if(!array_key_exists($k,$a))$added[$k]=self::deepClone($v);elseif(!self::deepEqual($a[$k],$v))$changed[$k]=['from'=>self::deepClone($a[$k]),'to'=>self::deepClone($v)];}foreach($a as $k=>$v)if(!array_key_exists($k,$b))$removed[$k]=self::deepClone($v);return compact('added','removed','changed');}
 public static function getPath(mixed $v,string $path,mixed $default=null):mixed{if($path==='')return $v;foreach(explode('.',$path) as $p){if(!is_array($v)&&!is_object($v))return $default;$v=is_array($v)?($v[$p]??$default):($v->{$p}??$default);}return $v;}
 public static function setPath(array &$v,string $path,mixed $value):array{$parts=explode('.',$path);$r=&$v;foreach($parts as $p){if(!isset($r[$p])||!is_array($r[$p]))$r[$p]=[];$r=&$r[$p];}$r=$value;return $v;}
}