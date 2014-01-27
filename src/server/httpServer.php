<?php

require_once(dirname(__FILE__).'/../core/coStreamSocketServer.php');

class httpServer extends coStreamSocketServer {
    
    function __construct($bindTo) {
        parent::__construct($bindTo);
    }
    
}

class httpRequest extends coStreamSocket {
    
    public $raw            = array('haders'=>array(), 'body' => array());
    public $reqMain        = "";
    public $reqHeaders     = array();
    public $reqGlobal      = array(
        '$_SERVER'  => array(), 
        '$_POST'    => array(), 
        '$_GET'     => array(), 
        '$_REQUEST' => array(), 
        '$_FILES'   => array(), 
        '$_ENV'     => array(), 
        '$_COOKIE'  => array(), 
        '$_SESSION' => array(), 
        '$GLOBALS'  => array()
    );
    public $haveReqHeaders = False;
    public $pendingHeaders = array();
    public $resHeaders     = array();
    public $resTransferEnc = "text/html";
    
    
    protected $reqMethod   = Null;
    protected $reqReady    = False;
    protected $reqRead     = False;
    protected $reqPrep     = False;
    protected $headersSent = False;
    protected $bodySent    = False;
    protected $bodyStarted = False;
    
    function __construct(&$stream, &$task) {
        //set the times of the request
        $this->reqTimeFloat  = microtime(true);
        $this->reqTime       = time();
        
        parent::__construct($stream);
        $this->task = $task;
    }
    
    function bootstrapRequest() {
        $this->task->addEvent( //read te request
            (new event($this->task, $this->readRequest($this->task, null), function() {
                return $this->task->super['conn']->canRead();
            }))
        );
        
        //prep the request
        $this->task->addEvent((new event($this->task, function() {
            return $this->prepRequest();
        }, function() {
                return $this->task->super['conn']->reqRead(); 
            })
        ));
        
        //write headers
        $this->task->addEvent((new event($this->task, $this->writeHeaders($this->task, null), function() {
                return $this->task->super['conn']->canWrite();
            })
        ));
        
        //is open
        $this->task->addEvent((new event($this->task, $this->isOpen($this->task, null))), "isOpen");
    }
    
    function parseReqHeaders() {
        foreach($this->raw['headers'] as $dat) {
            $header = explode(": ", $dat, 2);
            if(count($header) == 2) {
                $this->reqHeaders[$header[0]] = $header[1];
            }
            elseif(empty($this->reqMain)) {
                $main = explode(" ", $dat);
                $this->reqMethod = strtoupper($main[0]);
                $this->reqURI    = $main[1];
                $this->reqProto  = $main[2];
                $this->reqMain   = $main;
            }
        }
    }
    
    function addRawReqHeaders($header) {
        $this->raw['headers'][] = trim($header);
    }
    
    function reqReady() {
        if($this->reqReady == True) {
            return True;
        }
        return False;
    }
    
    function reqRead() { //the request has been read
        if($this->reqRead == True) {
            return True;
        }
        return False;
    }
    
    function reqPrep() { //the request has been read
        if($this->reqPrep == True) {
            return True;
        }
        return False;
    }
    
    function headersSent() {
        if($this->headersSent == True) {
            return True;
        }
        return False;
    }
    
    function bodySent() {
        if($this->bodySent == True) {
            return True;
        }
        return False;
    }
    
    function isOpen(&$that, $data) {
        yield;
        while(True) {
            if(microtime(true)-$that->super['conn']->reqTimeFloat >= 1) {
                print "lost".PHP_EOL;
                $that->setFinshed(True);
                break;
            }
            yield;
        }
    }
    
    
    function readRequest(&$that, $data){
        $http =& $that->super['conn'];
        yield;
        while(strlen($line = fgets($http->getStream())) > 0 && $http->haveReqHeaders == False) {
            if($line == "\r\n") {
                $http->haveReqHeaders = True;
                $http->parseReqHeaders();
                
                if(isset($http->reqHeaders['Content-Length'])) {
                    $that->addEvent(
                        (new event($this->task, $this->readBody($this->task, null), function() { 
                            return $this->task->super['conn']->canRead();
                    })));
                    break;
                }
                elseif (isset($http->reqHeaders['Transfer-Encoding']) &&  $http->reqHeaders['Transfer-Encoding'] == "Chunked") {
                    
                }
                $http->reqRead = True;
                break;
            }
            $http->addRawReqHeaders($line);
            
            yield;
            continue;
        }
    }
    
    function readBody(&$that, $data) {
        $http      =& $that->super['conn'];
        $readUpto  = (int)$http->reqHeaders['Content-Length'];
        $readTotal = 0;
        $body      = "";
        yield;
        while(True) {
            $now = fread($http->getStream(), $readUpto-$readTotal);
            $readTotal += strlen($now);
            $body .= $now;
            if($readTotal >= $readUpto) {
                $http->raw['body'] = $body;
                break;
            }
            yield;
        }
        $http->reqRead = True;
    }
    
    function client499() {
        print "499".PHP_EOL;
        return $this->task->setFinshed(True);
    }
    
    function writeHeaders(&$that, $data) {
        $http =& $that->super['conn'];
        yield;
        while(True) {
            if(!empty($this->pendingHeaders)) foreach($this->pendingHeaders as $dex=>$dat) {
                if(@fwrite($http->getStream(), "$dex: $dat\r\n") === False) {
                    $this->client499();
                }
                $this->headersSent = True;
                unset($this->pendingHeaders[$dex]);
            }
            
            if(!empty($this->pendingBody)) {
                $that->addEvent(
                        (new event($this->task, $this->writeBody($this->task, null), function() { 
                            return $this->task->super['conn']->canWrite();
                    })));
                break;
            }
            yield;
        }
    }
    
    function writeBody(&$that, $data) {
        $http =& $that->super['conn'];
        yield;
        if(@fwrite($http->getStream(), "\r\n") === False) {//blank line for reponse
            $http->client(499);
        }
        
        while(True) {
            if(!empty($this->pendingBody)) {
                $write = array_shift($this->pendingBody);
                if(@fwrite($http->getStream(), $write) === False) {
                    $http->client499();
                }
                $this->bodySent = True;
            }
            
            if(empty($this->pendingBody) && $this->bodySent()) {
                break;
            }
        }
    }
    
    function prepRequest() {
        $this->reqURL      = $this->buildReqUrl();
        $this->reqUrlParse = parse_url($this->reqURL);
        $this->reqGlobal   = array(
            '$_SERVER' => $this->getServerGlobal(),
            '$_GET'    => $this->getGetGlobal(),
            '$_POST'    => $this->getPostGlobal(),
        );
        $this->reqReady = True;
    }
    
    function buildReqUrl() {
        $url = 'http://';
        if(isset($this->reqHeaders['Authorization'])) {
            $url .= base64_decode(str_replace("Basic ", "", $this->reqHeaders['Authorization']))."@";
        }
        $url .= $this->reqHeaders['Host'].$this->reqURI;
        return $url;
    }
    
    function getServerGlobal() {
        $remote = explode(":", $this->task->super['remote']); //remote addr & port
        $local  = explode(":", $this->task->super['local']); //remote addr & port
        
        $build = array(
            'REQUEST_TIME'       => $this->reqTime, 
            'REQUEST_TIME_FLOAT' => $this->reqTimeFloat, 
            'REQUEST_METHOD'     => $this->reqMethod,
            'REQUEST_URI'        => $this->reqURI,
            'SERVER_PROTOCOL'    => $this->reqProto,
            'GATEWAY_INTERFACE'  => $this->reqProto,
            'REMOTE_ADDR'        => array_slice($remote, 0, -1)[0], 
            'REMOTE_PORT'        => array_slice($remote, -1)[0],
            'SERVER_ADDR'        => array_slice($local, 0, -1)[0],
            'SERVER_PORT'        => array_slice($local, -1)[0],
            'QUERY_STRING'       => isset($this->reqUrlParse['query']) ? $this->reqUrlParse['query'] : null,
        );
        foreach($this->reqHeaders as $dex=>$dat) {
            $build['HTTP_'.str_replace("-", "_", strtoupper($dex))] = $dat;
        }
        return $build;
    }
    
    function getGetGlobal() {
        $get = array();
        if(isset($this->reqUrlParse['query'])) {
            parse_str($this->reqUrlParse['query'], $get);
        }
        return $get;
    }
    
    function getPostGlobal() {
        $post = array();
        if(isset($this->reqHeaders['Content-Type'])) {
            if($this->reqHeaders['Content-Type'] == 'application/x-www-form-urlencoded') {
                parse_str($this->raw['body'], $post);
            }
        }
        return $post;
    }
    
    function addHeader($header, $value) {
        if( !isset($this->resHeaders[$header]) || isset($this->pendingHeaders[$header]) ) {
            $this->resHeaders[$header]     = $value;
            $this->pendingHeaders[$header] = $value;
            return True;
        }
        return False;
    }
    
    function bodyWrite($body) {
        $this->pendingBody[] = $body;
    }
}

class websocketServer {
    
}

?>
