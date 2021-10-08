<?php
namespace Ajtarragona\GTT\Helpers;

use Ajtarragona\GTT\Helpers\XML2Array;
use SimpleXMLElement;

class GTTHelpers
{

	public static function array_to_xml($array, &$xml_user_info) {
		foreach($array as $key => $value) {
			if(is_array($value)) {
				if(!is_numeric($key)){
					$subnode = $xml_user_info->addChild("$key");
					self::array_to_xml($value, $subnode);
				}else{
					$subnode = $xml_user_info->addChild("item$key");
					self::array_to_xml($value, $subnode);
				}
			}else {
				$xml_user_info->addChild("$key",htmlspecialchars("$value"));
			}
		}
		
	}


	

	public static function to_xml($data, $options=[]){

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
		if($options['root_node_attributes'] && is_array($options['root_node_attributes'])) {
			foreach($options['root_node_attributes'] as $key=>$value){
				$xml_string.=' '.$key.'="'.$value.'"';
			}
		}

		$xml_string.="></".$options["root_node"].">";
		
		$xml = new SimpleXMLElement($xml_string);
		// dump($xml->asXML(),$data);
		//function call to convert array to xml
		self::array_to_xml($data, $xml);
		
		if(!$options["header"]){
			$dom = dom_import_simplexml($xml);
			return $dom->ownerDocument->saveXML($dom->ownerDocument->documentElement);
		}else{
			return $xml->asXML();
		}


	}
	


	public static function from_xml($xmlnode) {
		return XML2Array::createObject($xmlnode);
		
	}


}