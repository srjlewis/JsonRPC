<?php

namespace JsonRPC;

use Exception;
use JsonRPC\Request\RequestBuilder;
use JsonRPC\Response\ResponseParser;
use JsonRPC\Response\ResponseParserInterface;

/**
 * JsonRPC client class
 *
 * @package JsonRPC
 * @author  Frederic Guillot
 */
class Client
{
    /**
     * If the only argument passed to a function is an array
     * assume it contains named arguments
     *
     * @access protected
     * @var boolean
     */
    protected $isNamedArguments = true;

    /**
     * Do not immediately throw an exception on error. Return it instead.
     *
     * @access public
     * @var boolean
     */
    protected $returnException = false;

    /**
     * True for a batch request
     *
     * @access protected
     * @var boolean
     */
    protected $isBatch = false;

    /**
     * Batch payload
     *
     * @access protected
     * @var array
     */
    protected $batch = array();

    /**
     * Http Client
     *
     * @access protected
     * @var HttpClient
     */
    protected $httpClient;

    /**
     * @var Response\ResponseParserInterface
     */
    protected $responseParser;

    /**
     * Constructor
     *
     * @access public
     * @param string                       $url             Server URL
     * @param bool                         $returnException Return exceptions
     * @param HttpClient|null              $httpClient      HTTP client object
     * @param ResponseParserInterface|null $responseParser
     */
    public function __construct(
        $url = '',
        $returnException = false,
        ?HttpClient $httpClient = null,
        ?ResponseParserInterface $responseParser = null
    ) {
        $this->httpClient = $httpClient ?: new HttpClient($url);
        $this->returnException = $returnException;
        $this->responseParser = $responseParser ?: ResponseParser::create();
    }

    /**
     * Arguments passed are always positional
     *
     * @access public
     * @return $this
     */
    public function withPositionalArguments()
    {
        $this->isNamedArguments = false;
        return $this;
    }

    /**
     * Get HTTP Client
     *
     * @access public
     * @return HttpClient
     */
    public function getHttpClient()
    {
        return $this->httpClient;
    }

    /**
     * Set username and password
     *
     * @access public
     * @param string $username
     * @param string $password
     * @return $this
     */
    public function authentication($username, $password)
    {
        $this->httpClient
            ->withUsername($username)
            ->withPassword($password);

        return $this;
    }

    /**
     * Automatic mapping of procedures
     *
     * @access public
     * @param string $method Procedure name
     * @param array $params Procedure arguments
     * @return mixed
     */
    public function __call($method, array $params)
    {
        if ($this->isNamedArguments && count($params) === 1 && is_array($params[0])) {
            $params = $params[0];
        }

        return $this->execute($method, $params);
    }

    /**
     * Start a batch request
     *
     * @access public
     * @return Client
     */
    public function batch()
    {
        $this->isBatch = true;
        $this->batch   = array();
        return $this;
    }

    /**
     * Send a batch request
     *
     * @access public
     * @return array
     */
    public function send()
    {
        $this->isBatch = false;
        return $this->sendPayload('[' . implode(', ', $this->batch) . ']');
    }

    /**
     * Execute a procedure
     *
     * @access public
     * @param string $procedure Procedure name
     * @param array $params Procedure arguments
     * @param array $reqattrs
     * @param string|null $requestId Request Id
     * @param string[] $headers Headers for this request
     * @return mixed
     */
    public function execute(
        $procedure,
        array $params = array(),
        array $reqattrs = array(),
        $requestId = null,
        array $headers = array()
    ) {
        $payload = RequestBuilder::create()
            ->withProcedure($procedure)
            ->withParams($params)
            ->withRequestAttributes($reqattrs)
            ->withId($requestId)
            ->build();

        if ($this->isBatch) {
            $this->batch[] = $payload;
            return $this;
        }

        return $this->sendPayload($payload, $headers);
    }

    /**
     * Send payload
     *
     * @access protected
     * @param string $payload
     * @param string[] $headers
     * @return Exception|array
     * @throws Exception
     */
    protected function sendPayload($payload, array $headers = array())
    {
        return $this->responseParser
            ->withReturnException($this->returnException)
            ->parse($this->httpClient->execute($payload, $headers));
    }
}
