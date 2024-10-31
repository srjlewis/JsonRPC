<?php

namespace JsonRPC\Request\Logger;

/**
 * Created by PhpStorm.
 * User: StevenLewis
 * Date: 09/05/2017
 * Time: 12:30
 */
class DebugRequestLogger implements RequestLoggerInterface
{

    /**
     * recorded requests
     * @var array
     */
    protected array $logs = array();

    public function log($id, $method, $params, $response, int|float $timeTaken = 0, array $metadata = array())
    {
        $this->logs[] = array(
            'id'        => $id,
            'method'    => $method,
            'params'    => $params,
            'response'  => $response,
            'timeTaken' => $timeTaken,
            'metadata'  => $metadata
        );
    }

    public function getLogs(): array
    {
        return $this->logs;
    }
}