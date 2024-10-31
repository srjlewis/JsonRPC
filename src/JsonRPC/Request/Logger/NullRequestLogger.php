<?php
namespace JsonRPC\Request\Logger;

/**
 * Created by PhpStorm.
 * User: StevenLewis
 * Date: 09/05/2017
 * Time: 12:29
 */
class NullRequestLogger implements RequestLoggerInterface
{

    public function log($id, $method, $params, $response, int|float $timeTaken = 0, array $metadata = array())
    {
    }
}