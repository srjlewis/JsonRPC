<?php
use JsonRPC\ProcedureHandler;
use PHPUnit\Framework\TestCase;
/**
 * Created by PhpStorm.
 * User: StevenLewis
 * Date: 10/05/2017
 * Time: 12:25
 */
class ServerSMDTest extends TestCase
{

    public function testSMD()
    {
        $ph = new ProcedureHandler();
        $ph->withCallback('callbackFunction', function ($echo, string $echo2 = 'more') {
            return $echo.$echo2;
        });

        $ph->withServiceClass('test2', new TestService());
        $ph->withClassAndMethod('test', 'TestService', 'returnMulti');
        $ph->withObject('TestService');

        $smd = $ph->getSMD('/json.php');
        $this->assertEquals('22dbde75cae7352af192d1ba24442960', md5($smd));
    }
}



class TestService
{

    /**
     * will return a float
     *
     * @param mixed|int|float|int $var
     * @param $var2
     * @param string $var3
     * @return float|int
     */
    public function returnMulti($var, $var2, $var3 = 'foo')
    {
        return $var.$var2.$var3;
    }

    /**
     * Will echo back anything sent to it
     *
     * @param mixed|int|float $text
     * @return mixed
     */
    public function echoback($text)
    {
        return $text;
    }



    /**
     * Will send a class with property's
     *
     * @return \stdClass
     */
    public function returnObject()
    {
        $o = new \stdClass;
        return $o;
    }

    /**
     * @param string $message
     * @param int $code
     * @throws \Exception
     */
    public function exception($message = 'Test Exception', $code = 0){
        if(empty($message)){
            $message = 'Test Exception';
        }
        throw new \Exception($message, (int)$code);
    }

    public function _test() {

    }

}