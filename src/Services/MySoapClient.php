<?php

namespace Ajtarragona\GTT\Services;

use Ajtarragona\GTT\Helpers\GTTHelpers;
use DOMDocument;
use RobRichards\WsePhp\WSSESoap;
use RobRichards\XMLSecLibs\XMLSecurityKey;
use SoapClient;
use SoapFault;
use SoapVar;

define('PRIVATE_KEY', 'priv_key.pem');
define('CERT_FILE', 'pub_key.pem');
define('SERVICE_CERT', 'sitekey_pub.cer');

class MySoapClient extends SoapClient
{
    const GTT_NS_prefix  = 'ns1';
    // const WSA_NS    = 'http://schemas.xmlsoap.org/ws/2004/08/addressing';
    // const WSA_NS_prefix    = 'wsa';

    protected $operation;

    public function __doRequest($request, $location, $saction, $version, $one_way = NULL)
    {
        $doc = new DOMDocument('1.0');
        $doc->loadXML($request);

        $objWSSE = new WSSESoap($doc);

        /* add Timestamp with no expiration timestamp */
        $objWSSE->addTimestamp();

        /* create new XMLSec Key using AES256_CBC and type is private key */
        $objKey = new XMLSecurityKey(XMLSecurityKey::RSA_SHA1, array('type' => 'private'));

        /* load the private key from file - last arg is bool if key in file (true) or is string (false) */
        $objKey->loadKey(PRIVATE_KEY, true);

        /* Sign the message - also signs appropiate WS-Security items */
        $options = array("insertBefore" => false);
        $objWSSE->signSoapDoc($objKey, $options);

        /* Add certificate (BinarySecurityToken) to the message */
        $token = $objWSSE->addBinaryToken(file_get_contents(CERT_FILE));

        /* Attach pointer to Signature */
        $objWSSE->attachTokentoSig($token);

        $objKey = new XMLSecurityKey(XMLSecurityKey::AES256_CBC);
        $objKey->generateSessionKey();

        $siteKey = new XMLSecurityKey(XMLSecurityKey::RSA_OAEP_MGF1P, array('type' => 'public'));
        $siteKey->loadKey(SERVICE_CERT, true, true);

        $options = array("KeyInfo" => array("X509SubjectKeyIdentifier" => true));
        $objWSSE->encryptSoapDoc($siteKey, $objKey, $options);

        $retVal = parent::__doRequest($objWSSE->saveXML(), $location, $saction, $version);

        $doc = new DOMDocument();
        $doc->loadXML($retVal);

        $options = array("keys" => array("private" => array("key" => PRIVATE_KEY, "isFile" => true, "isCert" => false)));
        $objWSSE->decryptSoapDoc($doc, $options);

        return $doc->saveXML();
    }



    public function call($operation, $parameters=[]){
        
        $this->operation=$operation;
        
        $xml=GTTHelpers::to_xml($parameters, [
            'header'=>false,
            "root_node" => "RequestContribuyente",
            "root_node_attributes" => [
                "xmlns:xsd" =>'http://www.w3.org/2001/XMLSchema',
                "xmlns:xsi"=>'http://www.w3.org/2001/XMLSchema-instance',
                "xmlns"=>'tns:tributos'
            ]

        ]);


        $xmltag = new SoapVar("<".self::GTT_NS_prefix.":XmlRequest><".self::GTT_NS_prefix.":XmlDataRequest><![CDATA[{$xml}]]></".self::GTT_NS_prefix.":XmlDataRequest></".self::GTT_NS_prefix.":XmlRequest>", XSD_ANYXML);
            
        // $params = new \SoapVar($xml, XSD_ANYXML);

        // $XmlRequest = array("XmlDataRequest" => $params);

        try {
            // dd($xml,$xmltag,$client);
// 
            $args=[
                'XmlRequest' => $xmltag
            ];
            // dump($args);
            // $result=$this->GetDatos($args);
            $result = $this->__soapCall("GetDatos", $args);

            echo "====== REQUEST HEADERS =====" . PHP_EOL;
            dump($this->__getLastRequestHeaders());
            echo "========= REQUEST ==========" . PHP_EOL;
            dump($this->__getLastRequest());
            echo "========= RESPONSE =========" . PHP_EOL;
            dd($result);
            // return $this->parseResults($method,$results,$options);

        } catch (SoapFault $e) {
            dd($e);
            // return $client->__getLastResponse();

        }
    }
}