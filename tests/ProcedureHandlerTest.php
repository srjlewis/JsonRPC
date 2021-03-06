<?php

use JsonRPC\ProcedureHandler;
use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../vendor/autoload.php';

class A
{
    public function getAll($p1, $p2, $p3 = 4)
    {
        return $p1 + $p2 + $p3;
    }

    public function add($a, $b)
    {
        return $a + $b;
    }

    public function subtract($a, $b)
    {
        return $a - $b;
    }
}

class B
{
    public function getAll($p1)
    {
        return $p1 + 2;
    }

    public function add($a, $b)
    {
        return $a + $b;
    }

    public function subtract($a, $b)
    {
        return $a - $b;
    }
}

class ClassWithBeforeMethod
{
    private $foobar = '';

    public function before($procedure)
    {
        $this->foobar = $procedure;
    }

    public function myProcedure()
    {
        return $this->foobar;
    }
}

class ProcedureHandlerTest extends TestCase
{
    public function testProcedureNotFound()
    {
        $this->expectException('BadFunctionCallException');
        $handler = new ProcedureHandler;
        $handler->executeProcedure('a');
    }

    public function testCallbackNotFound()
    {
        $this->expectException('BadFunctionCallException');
        $handler = new ProcedureHandler;
        $handler->withCallback('b', function() {});
        $handler->executeProcedure('a');
    }

    public function testClassNotFound()
    {
        $this->expectException('BadFunctionCallException');
        $handler = new ProcedureHandler;
        $handler->withClassAndMethod('getAllTasks', 'c', 'getAll');
        $handler->executeProcedure('getAllTasks');
    }

    public function testMethodNotFound()
    {
        $this->expectException('BadFunctionCallException');
        $handler = new ProcedureHandler;
        $handler->withClassAndMethod('getAllTasks', 'A', 'getNothing');
        $handler->executeProcedure('getAllTasks');
    }

    public function testIsPositionalArguments()
    {
        $handler = new ProcedureHandler;
        $this->assertFalse($handler->isPositionalArguments(
            array('a' => 'b', 'c' => 'd')
        ));

        $handler = new ProcedureHandler;
        $this->assertTrue($handler->isPositionalArguments(
            array('a', 'b', 'c')
        ));
    }

    public function testBindNamedArguments()
    {
        $handler = new ProcedureHandler;
        $handler->withClassAndMethod('getAllA', 'A', 'getAll');
        $handler->withClassAndMethod('getAllB', 'B', 'getAll');
        $handler->withClassAndMethod('getAllC', new B, 'getAll');
        $handler->withServiceClass('serviceA', 'A');
        $handler->withServiceClass('serviceB', new B());
        $this->assertEquals(6, $handler->executeProcedure('getAllA', array('p2' => 4, 'p1' => -2)));
        $this->assertEquals(10, $handler->executeProcedure('getAllA', array('p2' => 4, 'p3' => 8, 'p1' => -2)));
        $this->assertEquals(6, $handler->executeProcedure('getAllB', array('p1' => 4)));
        $this->assertEquals(5, $handler->executeProcedure('getAllC', array('p1' => 3)));

        $this->assertEquals(5, $handler->executeProcedure('serviceA.add', array('b' => 3, 'a' => 2)));
        $this->assertEquals(2, $handler->executeProcedure('serviceA.subtract', array('b' => 3, 'a' => 5)));
        $this->assertEquals(6, $handler->executeProcedure('serviceA.getAll', array('p2' => 4, 'p1' => -2)));
        $this->assertEquals(10, $handler->executeProcedure('serviceA.getAll', array('p2' => 4, 'p3' => 8, 'p1' => -2)));

        $this->assertEquals(5, $handler->executeProcedure('serviceB.add', array('b' => 3, 'a' => 2)));
        $this->assertEquals(2, $handler->executeProcedure('serviceB.subtract', array('b' => 3, 'a' => 5)));
        $this->assertEquals(6, $handler->executeProcedure('serviceB.getAll', array('p1' => 4)));
    }

    public function testBindPositionalArguments()
    {
        $handler = new ProcedureHandler;
        $handler->withClassAndMethod('getAllA', 'A', 'getAll');
        $handler->withClassAndMethod('getAllB', 'B', 'getAll');
        $handler->withServiceClass('serviceA', 'A');
        $handler->withServiceClass('serviceB', new B());
        $this->assertEquals(6, $handler->executeProcedure('getAllA', array(4, -2)));
        $this->assertEquals(2, $handler->executeProcedure('getAllA', array(4, 0, -2)));
        $this->assertEquals(4, $handler->executeProcedure('getAllB', array(2)));

        $this->assertEquals(5, $handler->executeProcedure('serviceA.add', array(2, 3)));
        $this->assertEquals(2, $handler->executeProcedure('serviceA.subtract', array(5, 3)));
        $this->assertEquals(6, $handler->executeProcedure('serviceA.getAll', array(4, -2)));
        $this->assertEquals(2, $handler->executeProcedure('serviceA.getAll', array(4, 0, -2)));

        $this->assertEquals(5, $handler->executeProcedure('serviceB.add', array(2, 3)));
        $this->assertEquals(2, $handler->executeProcedure('serviceB.subtract', array(5, 3)));
        $this->assertEquals(4, $handler->executeProcedure('serviceB.getAll', array(2)));
    }

    public function testRegisterNamedArguments()
    {
        $handler = new ProcedureHandler;
        $handler->withCallback('getAllA', function($p1, $p2, $p3 = 4) {
            return $p1 + $p2 + $p3;
        });

        $this->assertEquals(6, $handler->executeProcedure('getAllA', array('p2' => 4, 'p1' => -2)));
        $this->assertEquals(10, $handler->executeProcedure('getAllA', array('p2' => 4, 'p3' => 8, 'p1' => -2)));
    }

    public function testRegisterPositionalArguments()
    {
        $handler = new ProcedureHandler;
        $handler->withCallback('getAllA', function($p1, $p2, $p3 = 4) {
            return $p1 + $p2 + $p3;
        });

        $this->assertEquals(6, $handler->executeProcedure('getAllA', array(4, -2)));
        $this->assertEquals(2, $handler->executeProcedure('getAllA', array(4, 0, -2)));
    }

    public function testTooManyArguments()
    {
        $this->expectException('InvalidArgumentException');

        $handler = new ProcedureHandler;
        $handler->withClassAndMethod('getAllC', new B, 'getAll');
        $handler->executeProcedure('getAllC', array('p1' => 3, 'p2' => 5));
    }

    public function testNotEnoughArguments()
    {
        $this->expectException('InvalidArgumentException');

        $handler = new ProcedureHandler;
        $handler->withClassAndMethod('getAllC', new B, 'getAll');
        $handler->executeProcedure('getAllC');
    }

    public function testUndefinedArguments()
    {
        $this->expectException('InvalidArgumentException');
        $handler = new ProcedureHandler;
        $handler->withClassAndMethod('getAllA', new A, 'getAll');
        $handler->executeProcedure('getAllA', ['p1' => 3, 'p2' => 5, 'p333' => 7]);
    }

    public function testBeforeMethod()
    {
        $handler = new ProcedureHandler;
        $handler->withObject(new ClassWithBeforeMethod);
        $handler->withBeforeMethod('before');
        $this->assertEquals('myProcedure', $handler->executeProcedure('myProcedure'));
    }
}
