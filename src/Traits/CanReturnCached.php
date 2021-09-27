<?php

namespace Ajtarragona\GTT\Traits;


use Cache;

trait CanReturnCached{

    protected $cache_time= 360;

	protected function getHash($function, $token, $parameters=[]){
        
		return get_class($this)."\\".$function."\\".$token."\\".md5(serialize($parameters));
	}

	protected function returnCached($hash, $callable){
        if(is_callable($callable)){
			return Cache::remember($hash, $this->cache_time, function () use ($callable) {
				return $callable();
			});
		}
		return $callable;
	}
	
}
   
