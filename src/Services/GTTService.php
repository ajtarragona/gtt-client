<?php

namespace Ajtarragona\GTT\Services;

use SoapFault;

class GTTService
{
    
   
    protected $options;
    
    public function __construct($options=array()) { 
		$opts=config('gtt');
		if($options) $opts=array_merge($opts,$options);
		$this->options = json_decode(json_encode($opts), true);
	}

        

    protected function client(){
        // return new MySoapClient($this->options["ws_url"]."?singleWsdl");
        return SignedSoapClient::newClient($this->options);
    }
    

    public function getDadesGTT($id_contribuent){

        $results = $this->client()->call('ServiciosTributos.DatosContribuyente.Datos', [
            "InformacionContribuyente" => [
                "Contribuyente" => [
                    "DatosPersonales" => [
                        "IdFiscal" =>$id_contribuent,
                    ]
                ], 
                // "ObjetosTributarios" =>[
                //     "TipoObjetoTributario" => "Vehiculo"
                // ]
                
            ]
        ]);



       
        // return $result;
    }


    protected function call($method, $arguments=[], $options=[]){
         
        
    }




    private function parseResults($method,$results, $options=[]){
       
    }
      

}