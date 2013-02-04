<?php

/**
 * ArangoDB PHP client: batch
 * 
 * @package ArangoDbPhpClient
 * @author Frank Mayer
 * @since 1.1
 * 
 */

namespace triagens\ArangoDb;

/**
 * Provides batching functionality
 * 
 * @package ArangoDbPhpClient
 * @example ArangoDb/examples/batch.php aaaa
 * 
 */


class Batch {


  /**
  * Flag that signals if this batch was processed or not. Processed => true ,or not processed => false
  * 
  * @var boolean $_processed  
  */
  private $_processed = false;


  /**
  * The array of BatchPart objects
  * 
  * @var array $_batchParts  
  */
  private $_batchParts = array();


  /**
  * The array of BatchPart objects
  * 
  * @var array $_batchParts  
  */
  private $_nextBatchPartId = null;


  /**
  * An array of BatchPartCursor options
  * 
  * @var array $_batchParts  
  */
  private $_batchPartCursorOptions =  array();


  /**
  * The connection object
  * 
  * @var object $_connection  
  */
  private $_connection = null;


  /**
  * The $_alphaKeys object for the batchpart creation
  * 
  * @var object $_alphaKeys  
  */
  private $_alphaKeys = 0;


 /**
  * The sanitize default value
  * 
  * @var object $_sanitize  
  */
  private $_sanitize = false;


  /**
  * Constructor for Batch instance. Batch instance by default starts capturing request after initiated. 
  * To disable this, pass startCapture=>false inside the options array parameter
  * 
  * @param Connection $connection that this batch class will monitor for requests in order to batch them. Connection parameter is mandatory.
  * @param $options An array of options for Batch construction. See below for options: 
  * 
  * <p>Options are : 
  * <li>'sanitize' - True to remove _id and _rev attributes from result documents returned from this batch. Defaults to false.</li>
  * <li>'$startCapture' - Start batch capturing immediately after batch instantiation. Defaults to true. 
  * </li>
  * </p>
  * 
  * @return Batch
  */
  public function __construct(Connection $connection, $options=array()) {
    $startCapture=true;
    $sanitize=false;
    $options = array_merge($options, $this->getCursorOptions($sanitize));
    extract($options, EXTR_IF_EXISTS);    
    $this->_sanitize=$sanitize;
    
    $this->setConnection($connection);
    
    // set default cursor options. Sanitize is currently the only local one.
    $this->_batchPartCursorOptions=array(Cursor::ENTRY_SANITIZE => (bool) $this->_sanitize);
    
    if ($startCapture===true){
      $this->startCapture();
    }
    return $this;
  }


  /**
  * Sets the connection for he current batch. (mostly internal function)
  * 
  * @param Connection $connection
  * @return Batch
  */
  public function setConnection($connection) {
    $this->_connection = $connection; 
  
      return $this;
  }


  /**
  * Start capuring requests. To stop capturing, use stopCapture()
  * 
  * see triagens\ArangoDb\Batch::stopCapture()
  * 
  * @param array $options
  * @return Batch
  * 
  */
  public function startCapture($options=array()) {
    $this->activate($options);
    return $this;
  }     


  /**
  * Stop capturing requests. If the batch has not been processed yet, more requests can be appended by calling startCapture() again.
  * 
  * see Batch::startCapture()
  *     
  * @param array $options
  * @return Batch
  */
  public function stopCapture($options=array()) {
    // check if this batch is the active one... and capturing. Ignore, if we're not capturing...
    if($this->isActive()){
      $this->setCapture(false);
      return $this;
    }else{
      throw new ClientException('Cannot stop capturing with this batch. Batch is not active...'); 
    }
  }     


  /**
  * Returns true, if this batch is active in its associated connection.
  * 
  * @return bool
  */
  public function isActive() {
    $activeBatch=$this->getActive($this->_connection);
    $result = $activeBatch===$this ? true : false;
    return $result;
  }     


  /**
  * Returns true, if this batch is capturing requests.
  * 
  * @return bool
  */
  public function isCapturing() {
    $result=$this->getCapture($this->_connection);
    return $result;
  }     


  /**
  * Activates the batch. This sets the batch active in its assiated connection and also starts capturing.
  * 
  * @param array $options
  * 
  * @return object $this
  */
  public function activate($options=array()) {
    $this->setActive($this);
    $this->setCapture(true);
    return $this;
  }     


  /**
  * Sets the batch active in its assiated connection.
  * 
  * @param object $object
  * 
  * @return object $this 
  */
  public function setActive($object) {
    $this->_connection->setActiveBatch($object);
    return $this;
  }   


  /**
  * Sets the batch's assciated connection into capture mode.
  * 
  * @param boolean $state
  * 
  * @return object $this
  */
  public function setCapture($state) {
    $this->_connection->setCaptureBatch($state);
    return $this;
  }     


  /**
  * Gets active batch in given connection.
  * 
  * @param Connection $connection
  */
  public function getActive($connection) {
    $connection->getActiveBatch();
    return $this;
  }     


  /**
  * Returns true, if given connection is in batch mode.
  * 
  * @param Connection $connection
  */
  public function getCapture($connection) {
    $connection->getCaptureBatch();
    return $this;
  }  


  /**
  * Sets connection into Batch-Request mode. This is necessary to distinguish between normal and the batch request.
  * 
  * @param Connection $state
  */
  private function setBatchRequest($state) {
    $this->_connection->setBatchRequest($state);
    $this->_processed=true;
    return $this;
  }     


  /**
  * Sets the id of the next batchpart. The id can later be used to retrieve the batchpart.
  *   
  * @param mixed $batchPartId
  * @return Batch
  */
  public function nextBatchPartId($batchPartId) {
    $this->_nextBatchPartId=$batchPartId;
    return $this;
  }     


  /**
  * Set client side cursor options (for example: sanitize) for the next batch part.
  *   
  * @param mixed $batchPartCursorOptions
  * @return Batch
  */
  public function nextBatchPartCursorOptions($batchPartCursorOptions) {
    $this->_batchPartCursorOptions=$batchPartCursorOptions;
    return $this;
  }     


  /**
  * Append the request to the batchpart
  * 
  * @param mixed $method - The method of the request (GET, POST...)
  * @param mixed $request - The request that will get appended to the batch
  * @return HttpResponse
  */
  public function append($method, $request){
    if (preg_match('%/_api/simple/(?P<simple>\w*)|/_api/(?P<direct>\w*)%ix', $request, $regs)) {
      $result = $regs[0];
    } else {
      $result = "";
    }

    $type = $regs['direct']!='' ? $regs['direct'] : $regs['simple'] ;

    if ($type == $regs['direct'] && $method == 'GET'){
      $type = 'get'.$type;
      #if  (substr($request,strlen('GET /_api/'.$regs['direct']),1)=='?'){
        #$type.='ids';
      #}
 
    }

    $result  = 'HTTP/1.1 202 Accepted' . HttpHelper::EOL;
    $result .= 'location: /_api/document/0/0' . HttpHelper::EOL ;
    $result .= 'server: triagens GmbH High-Performance HTTP Server' . HttpHelper::EOL;
    $result .= 'content-type: application/json; charset=utf-8' . HttpHelper::EOL;
    $result .= 'etag: "0"' . HttpHelper::EOL;
    $result .= 'connection: Close' . HttpHelper::EOL . HttpHelper::EOL;
    $result .= '{"error":false,"_id":"0/0","id":"0","_rev":0,"hasMore":1, "result":[{}], "documents":[{}]}'. HttpHelper::EOL . HttpHelper::EOL;
    
    $response=new HttpResponse($result);
    $id = is_null($this->_nextBatchPartId) ? '' : $this->_nextBatchPartId;
    $batchpart= new BatchPart($this, $this->_nextBatchPartId, $type, $request, $response, array("cursorOptions"=>$this->_batchPartCursorOptions));
    if (is_null($this->_nextBatchPartId)) {
      $nextNumeric=count($this->_batchParts);
      $this->_batchParts[$nextNumeric]=$batchpart;
    }
    else {
       $this->_batchParts[$this->_nextBatchPartId]=$batchpart;
       $this->_nextBatchPartId=null;
    }
    return $response;
  }


  /**
  * Split batch request and use ContentId as array key
  *   
  * @param mixed $pattern
  * @param mixed $string
  * 
  * @return array $array - Array of batchparts
  */
  public function splitWithContentIdKey($pattern, $string) {
    $array=array();
    $exploded=explode($pattern,$string);
    foreach ($exploded as $key => $value) {
      $response=new HttpResponse($value);
      $contentId=$response->getHeader('Content-Id');

       if (!is_null($contentId)){
         $array[$contentId]=$value;
       }else{
         $array[$key]=$value;
       }
         
    } 
    return $array;
  }


  /**
  * Processes this batch. This sends the captured requests to the server as one batch.
  * 
  * @return bool - true if processing of the batch was  or the HttpResponse object in case of a failure. A successful process just means that tha parts were processed. Each part has it's own response though and should be checked on its own.
  */
  public function process(){
    $this->stopCapture();
    $this->setBatchRequest(true);
    $data = '';
    $batchParts = $this->getBatchParts();

    if (count($batchParts)==0) {
      throw new ClientException('Can\'t process empty batch.');
    }

    foreach ($batchParts as $partKey => $partValue) {
      $data .= '--' . HttpHelper::MIME_BOUNDARY . HttpHelper::EOL;
      $data .= 'Content-Type: application/x-arango-batchpart' . HttpHelper::EOL;
      
      if (!is_null($partValue->getId())){     
        $data .= 'Content-Id: ' . $partValue->getId() . HttpHelper::EOL . HttpHelper::EOL ;
      }else{
        $data .= HttpHelper::EOL;
      }

      $data .= $partValue->getRequest().HttpHelper::EOL;
    }
    $data .= '--'. HttpHelper::MIME_BOUNDARY . '--' . HttpHelper::EOL . HttpHelper::EOL;
    
    $params = array();
    $url = UrlHelper::appendParamsUrl(Urls::URL_BATCH, $params); 
    $this->_batchResponse = $this->_connection->post($url, ($data));
    if ($this->_batchResponse->getHttpCode()!==200){
      return $this->_batchResponse;
    }
    $body = $this->_batchResponse->getBody();
    $body = trim($body, '--'. HttpHelper::MIME_BOUNDARY. '--');
    $batchParts = $this->splitWithContentIdKey('--'. HttpHelper::MIME_BOUNDARY. HttpHelper::EOL , $body);

      foreach ($batchParts as $partKey => $partValue) {
        $response = new HttpResponse($partValue);
        $body = $response->getBody();
        $id = $response->getHeader('Content-Id');
        $response = new HttpResponse($body);
        $batchPartResponses[$partKey]= $response;
        $this->getPart($partKey)->setResponse($batchPartResponses[$partKey]);
      }

      return $this;
  }


  /**
  * Get the total count of the batch parts
  *   
  * @return integer $count
  */
  public function countParts(){
    $count = count($this->_batchParts);
    return $count;
  }


  /**
  * Get the batch part identified by the array key (0...n) or its id (if it was set with nextBatchPartId($id) )
  *   
  * @param $partId the batch part id. Either it's numeric key or a given name. 
  * 
  * @return mixed $batchPart
  */
  public function getPart($partId) {
    if (! isset($this->_batchParts[$partId])) {
      throw new ClientException('Request batch part does not exist.'); 
    }

    $batchPart = $this->_batchParts[$partId];
    return $batchPart;
  }


  /**
  * Get the batch part identified by the array key (0...n) or its id (if it was set with nextBatchPartId($id) )
  *   
  * @param $partId the batch part id. Either it's numeric key or a given name. 
  * 
  * @return mixed $partId
  */
  public function getPartResponse($partId) {
    $batchPart = $this->getPart($partId)->getResponse();
    return $batchPart;
  }


  /**
  * Get the batch part identified by the array key (0...n) or its id (if it was set with nextBatchPartId($id) )
  *   
  * @param $partId the batch part id. Either it's numeric key or a given name. 
  * 
  * @return mixed $partId
  */
  public function getProcessedPartResponse($partId) {
    $response = $this->getPart($partId)->getProcessedResponse();
    return $response;
  }


  /**
  * Returns the array of batchparts
  * 
  * @return array $_batchParts
  */
  public function getBatchParts() {
      return $this->_batchParts;
  }


  /**
   * Return an array of cursor options
   *
   * @return array - array of options
   */
  private function getCursorOptions() {
    return $this->_batchPartCursorOptions;
  }

  
  /**
   * Return this batch's connection
   *
   * @return Connection
   */  
  public function getConnection(){
    return $this->_connection;
  }

}
