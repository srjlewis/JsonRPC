<?php

namespace JsonRPC\Response;

use BadFunctionCallException;
use InvalidArgumentException;
use Exception;
use JsonRPC\Exception\InvalidJsonFormatException;
use JsonRPC\Exception\InvalidJsonRpcFormatException;
use JsonRPC\Exception\ResponseException;
use JsonRPC\Validator\JsonFormatValidator;

/**
 * Class ResponseParser
 *
 * @package JsonRPC\Request
 * @author  Frederic Guillot
 */
class ResponseParser implements ResponseParserInterface
{
    /**
     * Do not immediately throw an exception on error. Return it instead.
     *
     * @var bool
     */
    protected $returnException = false;

    /**
     * Get new object instance
     *
     * @static
     * @access public
     * @return ResponseParserInterface
     */
    public static function create()
    {
        return new static();
    }

    /**
     * Set Return Exception Or Throw It
     *
     * @param $returnException
     * @return ResponseParserInterface
     */
    public function withReturnException($returnException)
    {
        $this->returnException = $returnException;
        return $this;
    }

    /**
     * Parse response
     *
     * @param $payload
     * @return array|Exception|null
     * @throws InvalidJsonFormatException
     * @throws InvalidJsonRpcFormatException
     * @throws ResponseException
     */
    public function parse($payload)
    {
        JsonFormatValidator::validate($payload);

        if ($this->isBatchResponse($payload)) {
            $results = array();

            foreach ($payload as $response) {
                $results[] = self::create()
                    ->withReturnException($this->returnException)
                    ->parse($response);
            }

            return $results;
        }

        if (isset($payload['error']['code'])) {
            try {
                $this->handleExceptions($payload);
            } catch (Exception $e) {
                if ($this->returnException) {
                    return $e;
                }
                throw $e;
            }
        }

        return $payload['result'] ?? null;
    }

    /**
     * Handle exceptions
     *
     * @access protected
     * @param $payload
     * @throws InvalidJsonFormatException
     * @throws InvalidJsonRpcFormatException
     * @throws ResponseException
     */
    protected function handleExceptions($payload)
    {
        switch ($payload['error']['code']) {
            case -32700:
                throw new InvalidJsonFormatException(
                    'Parse error: '.$payload['error']['message'],
                    $payload['error']['code']
                );
            case -32600:
                throw new InvalidJsonRpcFormatException(
                    'Invalid Request: '.$payload['error']['message'],
                    $payload['error']['code']
                );
            case -32601:
                throw new BadFunctionCallException(
                    'Procedure not found: '.$payload['error']['message'],
                    $payload['error']['code']
                );
            case -32602:
                throw new InvalidArgumentException(
                    'Invalid arguments: '.$payload['error']['message'],
                    $payload['error']['code']
                );
            default:
                throw new ResponseException(
                    $payload['error']['message'],
                    $payload['error']['code'],
                    null,
                    $payload['error']['data'] ?? null
                );
        }
    }

    /**
     * Return true if we have a batch response
     *
     * @access protected
     * @return boolean
     */
    protected function isBatchResponse($payload)
    {
        return array_keys($payload) === range(0, count($payload) - 1);
    }
}
