<?php

namespace JsonRPC\Response;


use Exception;
use JsonRPC\Exception\InvalidJsonFormatException;
use JsonRPC\Exception\InvalidJsonRpcFormatException;
use JsonRPC\Exception\ResponseException;

/**
 * Class ResponseParser
 *
 * @package JsonRPC\Request
 * @author  Frederic Guillot
 */
interface ResponseParserInterface
{
    /**
     * Set Return Exception Or Throw It
     *
     * @param $returnException
     * @return ResponseParserInterface
     */
    public function withReturnException($returnException);

    /**
     * Parse response
     *
     * @param $payload
     * @return array|Exception|null
     * @throws InvalidJsonFormatException
     * @throws InvalidJsonRpcFormatException
     * @throws ResponseException
     */
    public function parse($payload);
}