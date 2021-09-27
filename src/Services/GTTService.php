<?php

namespace Ajtarragona\GTT\Services;


use Ajtarragona\GTT\Traits\CanReturnCached;
use Exception;
use Illuminate\Support\Str;
use SoapClient;
use SoapVar;


class GTTService
{

    use CanReturnCached;

    protected $options;
    protected static $business_name =  "";
    
    public function __construct($options=array()) { 
		$opts=config('gtt');
		if($options) $opts=array_merge($opts,$options);
		$this->options= json_decode(json_encode($opts), FALSE);
	}

        
    protected function client(){
        // dump($this->options->ws_url);
        return new SoapClient($this->options->ws_url.'?wsdl',
        array(
            
            'trace' => 1,
            "stream_context" => stream_context_create(
                array(
                    'ssl' => array(
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                    )
                )
            )
        ) );
    }
   
   

    protected function call($method, $arguments=[], $options=[]){

       
        
         
        
    }




    private function parseResults($method,$results, $options=[]){
       
    }
      

}