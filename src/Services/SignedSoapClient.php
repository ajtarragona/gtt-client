<?php

namespace Ajtarragona\GTT\Services;

use Ajtarragona\GTT\Helpers\GTTHelpers;
use SoapClient;
use SoapFault;
use SoapHeader;
use SoapVar;

/**
 *
 * SOAP Client class with message signing and HTTPS connections
 *
 * SSL settings should be passed on instance creation within `options` associated array.
 * Available settings are identical to the HTTPRequest class settings, e.g.
 *
 *    $client = new SignedSoapClient('https://example.com?wsdl', 
 *                  array('ssl' => array('cert' => '/file',
 *                        'certpasswd' => 'password')
 *                        )
 *                  );
 *
 * SSL certificate could be in PEM or PKCS12 format.
 *
 * >>> This class uses external utility xmlling (usually found in libxml2-utils package) <<<
 * It is required to canonicalize XML before signing it, as required by standard.
 *
 * This is a basic example, which signes SOAP-ENV:Body part of the request. To change this see how
 * buildSignedInfo method works and update __doRequest accordingly (see the part where wsu:Id is set
 * on Body). Make sure that signed element has an wsu:Id attribute.
 *
 */

class SignedSoapClient extends SoapClient
{
    
    // namespaces defined by standard
    const WSU_NS    = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
    const WSSE_NS   = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
    const SOAP_NS   = 'http://schemas.xmlsoap.org/soap/envelope/';
    const DS_NS     = 'http://www.w3.org/2000/09/xmldsig#';
    const GTT_NS    = 'http://www.gtt.es/WS';
    const GTT_NS_prefix  = 'ns1';
    const WSA_NS    = 'http://schemas.xmlsoap.org/ws/2004/08/addressing';
    const WSA_NS_prefix    = 'wsa';
    const EC_NS     = 'http://www.w3.org/2001/10/xml-exc-c14n#';

    protected $_ssl_options     = array();
    protected $_timeout         = 60;

    protected $config;
    protected $operation;


    function __construct($wsdl, $options=array())
    {

        $config=config('gtt');
		$this->config= json_decode(json_encode($config), FALSE);

        if (isset($options['ssl'])) {
            $this->_ssl_options = $options['ssl'];
            if (isset($this->_ssl_options['cert'])) {
                $certinfo = pathinfo($this->_ssl_options['cert']);
                if (in_array(strtolower($certinfo['extension']), array('p12', 'pfx')))
                    $this->_ssl_options['certtype'] = 'P12';
            }
        }
        if (isset($options['connection_timeout']) && intval($options['connection_timeout']))
            $this->_timeout = intval($options['connection_timeout']);
        
        
        return parent::__construct($wsdl, $options);
    }
    /**
     * Sample UUID function, based on random number or provided data
     *
     * @param mixed $data
     * @return string
     */
    function getUUID( $data=null,$prefix="uuid:")
    {
        if ($data === null)
            $data = microtime() . uniqid();
        $id = md5($data);
        return $prefix.sprintf('%08s-%04s-%04s-%04s-%012s', substr($id, 0, 8), substr($id, 8, 4), substr($id, 12, 4),
            substr(16, 4), substr($id, 20));
    }


    

    /**
     * Canonicalize DOMNode instance and return result as string
     *
     * @param \DOMNode $node
     * @return string
     */
    function canonicalizeNode($node, $dom=null)
    {
        dump("canonicalize:");

        $xmlapelo=$node->ownerDocument->saveXml( $node ); //xml tal cual
        $xml=$node->C14N(true); //xml canonizado
        dump($xmlapelo);
        dump($xml);
        return $xml;
        

        // $xml= $this->canonicalizeXML($node);
        // dump($xml);
        
        // $xml=$node->ownerDocument->saveXml($node);
        // return $xml;


        // $dom = new \DOMDocument('1.0', 'utf-8');
        // $dom->appendChild($dom->importNode($node, true));
        // return $dom->saveXML($dom->documentElement);
    }
    /**
     * Prepares SignedInfo DOMElement with required data
     *
     * $ids array should contain values of wsu:Id attribute of elements to be signed
     *
     * @param \DOMDocument $dom
     * @param array $ids
     * @return \DOMNode
     */
    function buildSignedInfo($dom, $signNode, $ids)
    {
        // dump($dom);
        $xp = new \DOMXPath($dom);
        // dump('buildSignedInfo',$ids);
        // dd($xp);
        $xp->registerNamespace('SOAP-ENV', self::SOAP_NS);
        // $xp->registerNamespace('wsa', self::WSA_NS);
        $xp->registerNamespace(self::GTT_NS_prefix, self::GTT_NS);
        $xp->registerNamespace('wsu', self::WSU_NS);
        $xp->registerNamespace('wsse', self::WSSE_NS);
        $xp->registerNamespace('ds', self::DS_NS);
        $xp->registerNamespace(self::WSA_NS_prefix, self::WSA_NS);

        $signedInfo = $dom->createElementNS(self::DS_NS, 'ds:SignedInfo');
        // canonicalization algorithm
        $method = $signedInfo->appendChild($dom->createElementNS(self::DS_NS, 'ds:CanonicalizationMethod'));
        $method->setAttributeNS(self::DS_NS, 'Algorithm', self::EC_NS);


        $InclusiveNamespaces = $method->appendChild($dom->createElementNS(self::EC_NS, 'ec:InclusiveNamespaces'));
        $InclusiveNamespaces->setAttribute('PrefixList', self::WSA_NS_prefix.' SOAP-ENV '.self::GTT_NS_prefix);

        // signature algorithm
        $method = $signedInfo->appendChild($dom->createElementNS(self::DS_NS, 'ds:SignatureMethod'));
        $method->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        foreach ($ids as $id) {
            // find a node and canonicalize it
            // dump($xp, $id);
            // $nodes = $xp->query("/SOAP-ENV:Envelope/SOAP-ENV:Header/ns2:To");
            $nodes = $xp->query("//*[@wsu:Id='{$id}']");

            // dd($nodes);
            if ($nodes->length == 0)
                continue;

//  
            $node=$nodes->item(0);

            $canonicalized = $this->canonicalizeNode( $node, $dom);
            // $newelement = $dom->createTextNode($canonicalized);


            $newelement = $dom->createDocumentFragment();
            $newelement->appendXML($canonicalized);
            $node->parentNode->replaceChild($newelement, $node);
            

            
            // dd($canonicalized);
            // create node Reference
            $reference = $signedInfo->appendChild($dom->createElementNS(self::DS_NS, 'ds:Reference'));
            $reference->setAttribute('URI', "#{$id}");
            $transforms = $reference->appendChild($dom->createElementNS(self::DS_NS, 'ds:Transforms'));
            $transform = $transforms->appendChild($dom->createElementNS(self::DS_NS, 'ds:Transform'));

            // mark node as canonicalized
            $transform->setAttribute('Algorithm', 'http://www.w3.org/2001/10/xml-exc-c14n#');


            $InclusiveNamespaces2 = $transform->appendChild($dom->createElementNS(self::EC_NS, 'ec:InclusiveNamespaces'));
            $InclusiveNamespaces2->setAttribute('PrefixList', 'SOAP-ENV '.self::GTT_NS_prefix);
        
            // and add a SHA1 digest
            $method = $reference->appendChild($dom->createElementNS(self::DS_NS, 'ds:DigestMethod'));
            $method->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
            $reference->appendChild($dom->createElementNS(self::DS_NS, 'ds:DigestValue', base64_encode(sha1($canonicalized, true))));
        }


        $signNode->appendChild($signedInfo);

        $signedxml=$this->canonicalizeNode($signedInfo);
        $newelement = $dom->createDocumentFragment();
        $newelement->appendXML($signedxml);
        // dd($signInfo->parentNode);
        // dd($signedInfo);
        $signedInfo->parentNode->replaceChild($newelement, $signedInfo);
        return $signedInfo;
    }


    
    /**
     * Prepares wsse:SecurityToken element based on public certificate
     *
     * @param \DOMDocument $dom
     * @param string $cert
     * @param string $certpasswd
     * @param resource $pkeyid
     * @param string $tokenId
     * @return \DOMNode
     */
    function buildSecurityToken($dom, &$pkeyid, &$tokenId)
    {
        $cert=$this->_ssl_options['cert'];

        $certinfo = pathinfo($cert);
        $cert = file_get_contents($cert);
        if (in_array(strtolower($certinfo['extension']), array('p12', 'pfx'))) {
            // for PKCS12 files
            openssl_pkcs12_read($cert, $certs, empty($this->_ssl_options['certpasswd']) ? '' : $this->_ssl_options['certpasswd']);
            $pkeyid = openssl_pkey_get_private($certs['pkey']);
            $pubcert = explode("\n", $certs['cert']);
            array_shift($pubcert);
            while (!trim(array_pop($pubcert))) {
            }
            array_walk($pubcert, 'trim');
            $pubcert = implode('', $pubcert);
            unset($certs);
        } else {
            // for PEM files
            $pkeyid = openssl_pkey_get_private($cert);
            echo $cert.PHP_EOL;
            $tempcert = openssl_x509_read($cert);
            openssl_x509_export($tempcert, $pubcert);
            // openssl_x509_free($tempcert);
        }
        $tokenId = $this->getUUID($pubcert,"Security-Token-");
        // add public key reference to the token
        $token = $dom->createElementNS(self::WSSE_NS, 'wsse:BinarySecurityToken', $pubcert);
        $token->setAttribute('ValueType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3');
        $token->setAttribute('EncodingType', 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary');
        $token->setAttributeNS(self::WSU_NS, 'wsu:Id', $tokenId);
        return $token;
    }





    /**
     * Replace generic request with our own signed HTTPS request
     *
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int $version
     * @return string
     */
    function __doRequest($request, $location, $action, $version, $one_way = NULL)
    {
        // dd($this);
        // update request with security headers
        $dom = new \DOMDocument('1.0', 'utf-8');
       
        
        
        // dd($request);
        
        $dom->loadXML($request);
        // dd($dom);
        $xp = new \DOMXPath($dom);
        $xp->registerNamespace(self::GTT_NS_prefix, self::GTT_NS);
        $xp->registerNamespace('SOAP-ENV', self::SOAP_NS);
        $xp->registerNamespace(self::WSA_NS_prefix, self::WSA_NS);
        $bodynode = $xp->query('/SOAP-ENV:Envelope/SOAP-ENV:Body')->item(0);
        // find or create SoapHeader
        $headernode = $xp->query('/SOAP-ENV:Envelope/SOAP-ENV:Header')->item(0);
        if (!$headernode) {
            $headernode = $dom->documentElement->insertBefore($dom->createElementNS(self::SOAP_NS, 'SOAP-ENV:Header'), $bodynode);
        }
        /**
         * mark soapenv:Body with wsu:Id for signing
         *
         * >> if you want to sign other elements - mark them on this step and provide id's on the later step
         *
         */
        // $bodyId=$this->getUUID(null,'bodyid-');
        // $bodynode->setAttributeNS(self::WSU_NS, 'wsu:Id', $bodyId);


        //To node
        // dd($headernode);

        // dd($tonode);
        
        
        $headernode->appendChild($dom->createElementNS(self::GTT_NS, self::GTT_NS_prefix.':SesionOrga', $this->config->sesion_orga));
        $headernode->appendChild($dom->createElementNS(self::GTT_NS, self::GTT_NS_prefix.':SesionId', null));
        $headernode->appendChild($dom->createElementNS(self::GTT_NS, self::GTT_NS_prefix.':Operacion', $this->operation));
        $headernode->appendChild($dom->createElementNS(self::WSA_NS, self::WSA_NS_prefix.':Action', self::GTT_NS.'/IProxyBase/GetDatos'));
        
        // $tonode = $xp->query('/SOAP-ENV:Envelope/SOAP-ENV:Header/'.self::WSA_NS_prefix.':To')->item(0);
        // if($tonode){
            $tonode = $headernode->appendChild($dom->createElementNS(self::WSA_NS, self::WSA_NS_prefix.':To', $this->config->ws_url));
            $tonodeid = $this->getUUID(null,'TO-');
            $tonode->setAttributeNS(self::WSU_NS,'wsu:Id', $tonodeid);
        // }
         $headernode->appendChild($dom->createElementNS(self::WSA_NS, self::WSA_NS_prefix.':MessageID', $this->getUUID()));

        
        // $tonode->setAttribute('xmlns:wsu', self::WSU_NS);


       
        // dd($bodynode);

        // prepare Security element

        $headerchildren = $xp->query('/SOAP-ENV:Envelope/SOAP-ENV:Header/*');
        // dd($headerchildren->item(0));
        $secNode = $headernode->insertBefore($dom->createElementNS(self::WSSE_NS, 'wsse:Security'),$headerchildren->item(0));
        $secId = $this->getUUID(null,'sec-');
        
        $secNode->setAttributeNS(self::WSU_NS, 'wsu:Id', $secId);
        

        $timestamp =  $secNode->appendChild($dom->createElementNs(self::WSU_NS, 'wsu:Timestamp'));

        $timestampid = $this->getUUID(null,'TS-');
        $timestamp->setAttribute('wsu:Id', $timestampid);

        $currentTime = time();
        $created     = $dom->createElement('wsu:Created', gmdate("Y-m-d\TH:i:s", $currentTime) . 'Z');
        $timestamp->appendChild($created);
        $secondsToExpire = 300;

        $expire = $dom->createElement('wsu:Expires', gmdate("Y-m-d\TH:i:s", $currentTime + $secondsToExpire) . 'Z');
        $timestamp->appendChild($expire);


        // $secNode->setAttribute('wsu:Id', $secId);
        // update with token data
        $pkeyid=null;
        
        $secNode->appendChild($this->buildSecurityToken($dom, $pkeyid, $tokenId));

        // dump('pkeyid',$pkeyid);
        /**
         * create Signature element and build SignedInfo for elements with provided ids
         *
         * >> if you are signing other elements, add id's to the second argument of buildSignedInfo
         *
         */

         
        $signNode = $secNode->appendChild($dom->createElementNS(self::DS_NS, 'ds:Signature'));
        $signNode->setAttribute('wsu:Id', $this->getUUID(null, 'SEC-'));
        
        $signInfo = $this->buildSignedInfo($dom, $signNode, array($tonodeid));
        
        // dump($signInfo->ownerDocument->saveXml($signInfo));
        // now that SignedInfo is built, sign it actually
        // $signedxml=$this->canonicalizeNode($signInfo);
        $signedxml=$signInfo->ownerDocument->saveXml( $signInfo ); //xml tal cual

        // dump($signInfo);
        // dump($signedxml);

      
        
        
        openssl_sign($signedxml, $signature, $pkeyid, OPENSSL_ALGO_SHA1);
        // var_dump($signature);
        // var_dump($pkeyid);
        // openssl_free_key($pkeyid);
        // dd(base64_encode($signature));
        $signNode->appendChild($dom->createElementNS(self::DS_NS, 'ds:SignatureValue', base64_encode($signature)));
        $keyInfo = $signNode->appendChild($dom->createElementNS(self::DS_NS, 'ds:KeyInfo'));
        $keyInfo->setAttribute('wsu:Id', $this->getUUID(null, 'KI-'));
        $secTokRef = $keyInfo->appendChild($dom->createElementNS(self::WSSE_NS, 'wsse:SecurityTokenReference'));
        $secTokRef->setAttribute('wsu:Id', $this->getUUID(null, 'STR-'));
        $keyRef = $secTokRef->appendChild($dom->createElementNS(self::WSSE_NS, 'wsse:Reference'));
        $keyRef->setAttribute('URI', "#{$tokenId}");
        // convert new document to string
        $request = $dom->saveXML();
       
        

        
         dump($request); //,$location, $action, $version);
         $result = parent::__doRequest($request, $location, $action, $version);
         
	     return $result;
    }


    protected function makeHeaders(){
        // $headers = array();

        // $headers[] = new SoapHeader(self::GTT_NS, "SesionOrga", $this->config->sesion_orga);
        // $headers[] = new SoapHeader(self::GTT_NS, "SesionId", null);
        // $headers[] = new SoapHeader(self::GTT_NS, "Operacion", $this->operation);

        

        // $ACTION_ISSUE = self::GTT_NS.'/IProxyBase/GetDatos';// Url With method name
        // $headers[] = new SoapHeader(self::WSA_NS, 'Action', $ACTION_ISSUE, false);
    
        
        // $headers[] = new SoapHeader(self::WSA_NS, 'To', $this->config->ws_url, false);
        // $headers[] = new SoapHeader(self::WSA_NS, 'MessageID', $this->getUUID(), false);
       
        // // $client->__setSoapHeaders($headerbody);
        // $this->__setSoapHeaders($headers);

    } 
    

    
    public static function newClient($options){
        return new SignedSoapClient($options["ws_url"].'?singleWsdl',[
            'trace'    => true,
            'ssl'            => [
                'cert'       => storage_path('app'.DIRECTORY_SEPARATOR.$options["cert_path"]),
                'certpasswd' => $options["cert_password"],
            ],
            'ssl_method' => SOAP_SSL_METHOD_TLS,
            'cache_wsdl'    => WSDL_CACHE_NONE,
            'stream_context' => stream_context_create([
                'ssl' => [
                    // 'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                    // 'ciphers' => 'SHA256',
                    'verify_peer'=>false,
                    'verify_peer_name'=>false, 
                    // 'allow_self_signed' => true //can fiddle with this one.
                ],
            ])
           
        ]);
    }


    public function call($operation, $parameters=[]){
        
        $this->operation=$operation;
        $this->makeHeaders();

        $xml=GTTHelpers::to_xml($parameters, [
            'header'=>false,
            "root_node" => "RequestContribuyente",
            "root_node_attributes" => [
                "xmlns:xsd" =>'http://www.w3.org/2001/XMLSchema',
                "xmlns:xsi"=>'http://www.w3.org/2001/XMLSchema-instance',
                "xmlns"=>'tns:tributos'
            ]

        ]);


        // dump($xml);

        // $xml = new XMLWriter();

        // $xml->openMemory();
        // $xml->startElement('ns1:XmlRequest');

        // $xml->startElement('ns1:XmlDataRequest');
        // $xml->startCData();
        // $xml->text("<RequestContribuyente xmlns:xsd='http://www.w3.org/2001/XMLSchema' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xmlns='tns:tributos'><InformacionContribuyente><Contribuyente><IdContribuyente>5189617</IdContribuyente></Contribuyente><ObjetosTributarios><TipoObjetoTributario>Vehiculo</TipoObjetoTributario></ObjetosTributarios></InformacionContribuyente></RequestContribuyente>");
        // $xml->endCData();
        // $xml->endElement();
        // $xml->endElement();
        // $data = $xml->outputMemory(true);

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
?>
