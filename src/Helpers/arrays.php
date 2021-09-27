<?php


if (! function_exists('isJson')) {
	function isJson($string) {
	 	try{
			if(!is_string($string)) return false;
			$ret=json_decode($string);
			if(!is_array($ret) && !is_object($ret)) return false; //es un tipo simple
			 
			return (json_last_error() == JSON_ERROR_NONE);
		}catch(Exception $e){
			return false;
		}
	}
}

if (! function_exists('to_object')) {
	function to_object($array, $firstlevel=true) {
        if($firstlevel){
            $tmp=json_decode(json_encode($array), FALSE);
            foreach($array as $key=>$value){
                $tmp->{$key} =  $value;
            }
            return $tmp;

        }else{
            return json_decode(json_encode($array), FALSE);
        }
		
	}
}


 
if (! function_exists('is_assoc')) {

	function is_assoc($array){
		if(!$array) return false;
		if(!is_array($array)) return false;
		return !(array_values($array) === $array);

	}
}



if (! function_exists('to_array')) {
	function to_array($object) {
	 	return json_decode(json_encode($object), true);
	}
}

if (! function_exists('to_array_first')) {
	function to_array_first($object) {
        $ret=[];
        foreach(array_keys(get_object_vars($object)) as $var){
            $ret[$var]=$object->{$var};
        }
        return $ret;
	}
}


if(!function_exists('array_to_csv'))
{
    function array_to_csv($input, $delimiter = ',', $enclosure = '"')
    {
        $out = "";
		foreach($input as $arr) {
			$row=[];
			foreach($arr as $col){
				$row[]= $enclosure. str_replace($enclosure, "\\".$enclosure, $col) .$enclosure;
			}
			
			$out.= implode($delimiter,$row). PHP_EOL;
		}
		return $out;
    }
 }

