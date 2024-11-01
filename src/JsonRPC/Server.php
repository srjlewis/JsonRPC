<?php

namespace JsonRPC;

use Closure;
use Exception;
use JsonRPC\Request\BatchRequestParser;
use JsonRPC\Request\Logger\RequestLoggerInterface;
use JsonRPC\Request\RequestParser;
use JsonRPC\Response\ResponseBuilder;
use JsonRPC\Validator\HostValidator;
use JsonRPC\Validator\JsonFormatValidator;
use JsonRPC\Validator\UserValidator;
use Psr\Log\LoggerInterface;

/**
 * JsonRPC server class
 *
 * @package JsonRPC
 * @author  Frederic Guillot
 */
class Server
{
    /**
     * Allowed hosts
     *
     * @access protected
     * @var array
     */
    protected $hosts = array();

    /**
     * Data received from the client
     *
     * @access protected
     * @var array
     */
    protected $payload = array();

    /**
     * List of exceptions that should not be relayed to the client
     *
     * @access protected
     * @var array()
     */
    protected $localExceptions = array();

    /**
     * Username
     *
     * @access protected
     * @var string
     */
    protected $username = '';

    /**
     * Password
     *
     * @access protected
     * @var string
     */
    protected $password = '';

    /**
     * Allowed users
     *
     * @access protected
     * @var array
     */
    protected $users = array();

    /**
     * $_SERVER
     *
     * @access protected
     * @var array
     */
    protected $serverVariable;

    /**
     * ProcedureHandler object
     *
     * @access protected
     * @var ProcedureHandler
     */
    protected $procedureHandler;

    /**
     * MiddlewareHandler object
     *
     * @access protected
     * @var MiddlewareHandler
     */
    protected $middlewareHandler;

    /**
     * Response builder
     *
     * @access protected
     * @var ResponseBuilder
     */
    protected $responseBuilder;

    /**
     * Response builder
     *
     * @access protected
     * @var RequestParser
     */
    protected $requestParser;

    /**
     *
     * Batch request parser
     *
     * @access protected
     * @var BatchRequestParser
     */
    protected $batchRequestParser;

    /**
     * Server call logger
     *
     * @access protected
     * @var RequestLoggerInterface
     */
    protected $requestLogger;

    protected ?LoggerInterface $psr3Logger = null;

    /**
     * Constructor
     *
     * @access public
     * @param string                      $request
     * @param array                       $server
     * @param ResponseBuilder|null        $responseBuilder
     * @param RequestParser|null          $requestParser
     * @param BatchRequestParser|null     $batchRequestParser
     * @param ProcedureHandler|null       $procedureHandler
     * @param MiddlewareHandler|null      $middlewareHandler
     * @param RequestLoggerInterface|null $requestLogger
     */
    public function __construct(
        string $request = '',
        array $server = array(),
        ?ResponseBuilder $responseBuilder = null,
        ?RequestParser $requestParser = null,
        ?BatchRequestParser $batchRequestParser = null,
        ?ProcedureHandler $procedureHandler = null,
        ?MiddlewareHandler $middlewareHandler = null,
        ?RequestLoggerInterface $requestLogger = null,
        ?LoggerInterface $psr3Logger = null
    ) {
        if ($request !== '') {
            $this->payload = json_decode($request, true);
        } else {
            $this->payload = json_decode(file_get_contents('php://input'), true);
        }

        $this->serverVariable     = $server ?: $_SERVER;
        $this->responseBuilder    = $responseBuilder ?: ResponseBuilder::create();
        $this->requestParser      = $requestParser ?: RequestParser::create();
        $this->batchRequestParser = $batchRequestParser ?: BatchRequestParser::create();
        $this->procedureHandler   = $procedureHandler ?: new ProcedureHandler();
        $this->middlewareHandler  = $middlewareHandler ?: new MiddlewareHandler();
        $this->requestLogger      = $requestLogger ?: new Request\Logger\NullRequestLogger();
        $this->psr3Logger         = $psr3Logger;
    }

    /**
     * Define alternative authentication header
     *
     * @access public
     * @param string $header Header name
     * @return $this
     */
    public function setAuthenticationHeader($header)
    {
        if (!empty($header)) {
            $header = 'HTTP_' . str_replace('-', '_', strtoupper($header));
            $value  = $this->getServerVariable($header);

            if (!empty($value)) {
                list($this->username, $this->password) = explode(':', base64_decode($value));
            }
        }

        return $this;
    }

    /**
     * Get ProcedureHandler
     *
     * @access public
     * @return ProcedureHandler
     */
    public function getProcedureHandler()
    {
        return $this->procedureHandler;
    }

    /**
     * Get MiddlewareHandler
     *
     * @access public
     * @return MiddlewareHandler
     */
    public function getMiddlewareHandler()
    {
        return $this->middlewareHandler;
    }

    /**
     * Get username
     *
     * @access public
     * @return string
     */
    public function getUsername()
    {
        return $this->username ?: $this->getServerVariable('PHP_AUTH_USER');
    }

    /**
     * Get password
     *
     * @access public
     * @return string
     */
    public function getPassword()
    {
        return $this->password ?: $this->getServerVariable('PHP_AUTH_PW');
    }

    /**
     * IP based client restrictions
     *
     * @access public
     * @param array $hosts List of hosts
     * @return $this
     */
    public function allowHosts(array $hosts)
    {
        $this->hosts = $hosts;
        return $this;
    }

    /**
     * HTTP Basic authentication
     *
     * @access public
     * @param array $users Dictionary of username/password
     * @return $this
     */
    public function authentication(array $users)
    {
        $this->users = $users;
        return $this;
    }

    /**
     * Register a new procedure
     *
     * @access     public
     * @param string  $procedure Procedure name
     * @param closure $callback  Callback
     * @return $this
     * @deprecated Use $server->getProcedureHandler()->withCallback($procedure, $callback)
     */
    public function register($procedure, Closure $callback)
    {
        $this->procedureHandler->withCallback($procedure, $callback);
        return $this;
    }

    /**
     * Bind a procedure to a class
     *
     * @access     public
     * @param string $procedure Procedure name
     * @param mixed  $class     Class name or instance
     * @param string $method    Procedure name
     * @return $this
     * @deprecated Use $server->getProcedureHandler()->withClassAndMethod($procedure, $class, $method);
     */
    public function bind($procedure, $class, $method = '')
    {
        $this->procedureHandler->withClassAndMethod($procedure, $class, $method);
        return $this;
    }

    /**
     * Bind a class instance
     *
     * @access     public
     * @param mixed $instance Instance name
     * @return $this
     * @deprecated Use $server->getProcedureHandler()->withObject($instance);
     */
    public function attach($instance)
    {
        $this->procedureHandler->withObject($instance);
        return $this;
    }

    /**
     * Exception classes that should not be relayed to the client
     *
     * @access public
     * @param Exception|string $exception
     * @return $this
     */
    public function withLocalException($exception)
    {
        $this->localExceptions[] = $exception;
        return $this;
    }

    /**
     * Parse incoming requests
     *
     * @access public
     * @return string
     */
    public function execute()
    {
        try {
            JsonFormatValidator::validate($this->payload);
            HostValidator::validate($this->hosts, $this->getServerVariable('REMOTE_ADDR'));
            UserValidator::validate($this->users, $this->getUsername(), $this->getPassword());

            $this->middlewareHandler
                ->withUsername($this->getUsername())
                ->withPassword($this->getPassword());

            $response = $this->parseRequest();
        } catch (Exception $e) {
            $response = $this->handleExceptions($e);
        }

        $this->responseBuilder->sendHeaders();
        return $response;
    }

    /**
     * Handle exceptions
     *
     * @access protected
     * @param Exception $e
     * @return string
     * @throws Exception
     */
    protected function handleExceptions(Exception $e)
    {
        foreach ($this->localExceptions as $exception) {
            if ($e instanceof $exception) {
                throw $e;
            }
        }

        return $this->responseBuilder->withPsr3Logger($this->psr3Logger)->withException($e)->build();
    }

    /**
     * Parse incoming request
     *
     * @access protected
     * @return string
     */
    protected function parseRequest()
    {
        if (BatchRequestParser::isBatchRequest($this->payload)) {
            return $this->batchRequestParser
                ->withPayload($this->payload)
                ->withProcedureHandler($this->procedureHandler)
                ->withMiddlewareHandler($this->middlewareHandler)
                ->withLocalException($this->localExceptions)
                ->withRequestLogger($this->requestLogger)
                ->withPsr3Logger($this->psr3Logger)
                ->parse();
        }

        return $this->requestParser
            ->withPayload($this->payload)
            ->withProcedureHandler($this->procedureHandler)
            ->withMiddlewareHandler($this->middlewareHandler)
            ->withLocalException($this->localExceptions)
            ->withRequestLogger($this->requestLogger)
            ->withPsr3Logger($this->psr3Logger)
            ->parse();
    }

    /**
     * Check existence and get value of server variable
     *
     * @access protected
     * @param string $variable
     * @return string|null
     */
    protected function getServerVariable($variable)
    {
        return $this->serverVariable[$variable] ?? null;
    }
}
