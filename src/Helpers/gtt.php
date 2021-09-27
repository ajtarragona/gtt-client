<?php

use App\Helpers\XML2Array;

if (! function_exists('gtt')) {
	function gtt($options=false){
		return new \Ajtarragona\GTT\Services\GTTService($options);
	}
}




if (! function_exists('array_to_xml')) {
	function array_to_xml($array, &$xml_user_info) {
		foreach($array as $key => $value) {
			if(is_array($value)) {
				if(!is_numeric($key)){
					$subnode = $xml_user_info->addChild("$key");
					array_to_xml($value, $subnode);
				}else{
					$subnode = $xml_user_info->addChild("item$key");
					array_to_xml($value, $subnode);
				}
			}else {
				$xml_user_info->addChild("$key",htmlspecialchars("$value"));
			}
		}
	}
}


if (! function_exists('to_xml')) {

	function to_xml($data, $options=null){

		$defaults=[
			"header" => true,
			"root_node" => "root",
			"encoding" => "UTF-8",
			"version" => "1.0",
			"case_sensitive" => false,
			"xmlns"=>false,
			"xmlns:xsd"=>false,
			"xmlns:xsi"=>false,
		];

		$options= is_array($options)? array_merge($defaults,$options) : $defaults;

		
		
		$xml_string="";

		$xml_string.= "<?xml version=\"".$options['version']."\" encoding=\"".$options['encoding']."\" ?>";
		$xml_string.="<".$options["root_node"]."";
		if($options['xmlns']) $xml_string.=' xmlns="'.$options['xmlns'].'" ';
		if($options['xmlns:xsd']) $xml_string.=' xmlns:xsd="'.$options['xmlns:xsd'].'"';
		if($options['xmlns:xsi']) $xml_string.=' xmlns:xsi="'.$options['xmlns:xsi'].'"';

		$xml_string.="></".$options["root_node"].">";
		
		$xml = new SimpleXMLElement($xml_string);
		// dump($xml->asXML(),$data);
		//function call to convert array to xml
		array_to_xml($data, $xml);
		
		if(!$options["header"]){
			$dom = dom_import_simplexml($xml);
			return $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
		}else{
			return $xml->asXML();
		}


	}
}


if (! function_exists('from_xml')) {
	function from_xml($xmlnode) {
		return XML2Array::createObject($xmlnode);
	} 
}

