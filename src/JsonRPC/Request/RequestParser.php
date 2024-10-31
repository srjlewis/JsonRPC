<?php

namespace JsonRPC\Request;

use Exception;
use JsonRPC\Exception\AccessDeniedException;
use JsonRPC\Exception\AuthenticationFailureException;
use JsonRPC\Exception\InvalidJsonRpcFormatException;
use JsonRPC\MiddlewareHandler;
use JsonRPC\ProcedureHandler;
use JsonRPC\Request\Logger\RequestLoggerInterface;
use JsonRPC\Response\ResponseBuilder;
use JsonRPC\Validator\JsonFormatValidator;
use JsonRPC\Validator\RpcFormatValidator;
use Psr\Log\LoggerInterface;

/**
 * Class RequestParser
 *
 * @package JsonRPC
 * @author  Frederic Guillot
 */
class RequestParser
{
    /**
     * Request payload
     *
     * @access protected
     * @var mixed
     */
    protected $payload;

    /**
     * List of exceptions that should not be relayed to the client
     *
     * @access protected
     * @var array()
     */
    protected $localExceptions = array(
        'JsonRPC\Exception\AuthenticationFailureException',
        'JsonRPC\Exception\AccessDeniedException',
    );

    /**
     * ProcedureHandler
     *
     * @access protected
     * @var ProcedureHandler
     */
    protected $procedureHandler;

    /**
     * MiddlewareHandler
     *
     * @access protected
     * @var MiddlewareHandler
     */
    protected $middlewareHandler;

    /**
     * Server call logger
     *
     * @access protected
     * @var RequestLoggerInterface
     */
    protected $requestLogger;

    protected ?LoggerInterface $psr3Logger;

    /**
     * Get new object instance
     *
     * @static
     * @access public
     * @return RequestParser
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Set payload
     *
     * @access public
     * @param  mixed $payload
     * @return $this
     */
    public function withPayload($payload)
    {
        $this->payload = $payload;
        return $this;
    }

    /**
     * Exception classes that should not be relayed to the client
     *
     * @access public
     * @param  mixed $exception
     * @return $this
     */
    public function withLocalException($exception)
    {
        if (is_array($exception)) {
            $this->localExceptions = array_merge($this->localExceptions, $exception);
        } else {
            $this->localExceptions[] = $exception;
        }
        
        return $this;
    }

    /**
     * Set procedure handler
     *
     * @access public
     * @param  ProcedureHandler $procedureHandler
     * @return $this
     */
    public function withProcedureHandler(ProcedureHandler $procedureHandler)
    {
        $this->procedureHandler = $procedureHandler;
        return $this;
    }

    /**
     * Set middleware handler
     *
     * @access public
     * @param  MiddlewareHandler $middlewareHandler
     * @return $this
     */
    public function withMiddlewareHandler(MiddlewareHandler $middlewareHandler)
    {
        $this->middlewareHandler = $middlewareHandler;
        return $this;
    }

    public function withPsr3Logger(?LoggerInterface $logger): static
    {
        $this->psr3Logger = $logger;
        return $this;
    }

    /**
     * @param RequestLoggerInterface $logger
     * @return $this
     */
    public function withRequestLogger(RequestLoggerInterface $logger)
    {
        $this->requestLogger = $logger;
        return $this;
    }

    /**
     * Parse incoming request
     *
     * @access public
     * @return string
     * @throws AccessDeniedException
     * @throws AuthenticationFailureException
     */
    public function parse()
    {
        $response = '';
        $startTime = round(microtime(true),5, PHP_ROUND_HALF_UP);
        try {

            JsonFormatValidator::validate($this->payload);
            RpcFormatValidator::validate($this->payload);

            $this->middlewareHandler
                ->withProcedure($this->payload['method'])
                ->execute();

            $result = $this->procedureHandler->executeProcedure(
                $this->payload['method'],
                empty($this->payload['params']) ? array() : $this->payload['params']
            );

            if (! $this->isNotification()) {
                $response = ResponseBuilder::create()
                    ->withId($this->payload['id'])
                    ->withResult($result)
                    ->build();
            }
        } catch (Exception $e) {
            $response = $this->handleExceptions($e);
        }

        $endTime  = round(microtime(true),5, PHP_ROUND_HALF_UP);
        $callTime = round(($endTime - $startTime),5, PHP_ROUND_HALF_UP);

        if($this->requestLogger){
            $this->requestLogger->log(
                (empty($this->payload['id']) ? 0 : $this->payload['id']),
                (empty($this->payload['method']) ? '' : $this->payload['method']),
                (empty($this->payload['params']) ? array() : $this->payload['params']),
                $response,
                $callTime
            );
        }

        return $response;
    }

    /**
     * Handle exceptions
     *
     * @access protected
     * @param  Exception $e
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

        if ($e instanceof InvalidJsonRpcFormatException || ! $this->isNotification()) {
            return ResponseBuilder::create()
                ->withPsr3Logger($this->psr3Logger)
                ->withId($this->payload['id'] ?? null)
                ->withException($e)
                ->build();
        } elseif($this->psr3Logger) {
            $this->psr3Logger->error(
                $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTrace()]
            );
        }

        return '';
    }

    /**
     * Return true if the message is a notification
     *
     * @access protected
     * @return bool
     */
    protected function isNotification()
    {
        return is_array($this->payload) && !isset($this->payload['id']);
    }
}
