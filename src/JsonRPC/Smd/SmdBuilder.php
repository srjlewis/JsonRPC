<?php
/**
 * Created by PhpStorm.
 * User: stevenlewis
 * Date: 10/05/2017
 * Time: 19:32
 */

namespace JsonRPC\Smd;


use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Used for building a Service Mapping Description (SMD)

 *
 * Class SmdBuilder
 * @package JsonRPC\Smd
 */
class SmdBuilder
{

    /**
     * List of procedures
     *
     * @access protected
     * @var array
     */
    protected $callbacks = array();

    /**
     * List of classes
     *
     * @access protected
     * @var array
     */
    protected $classes = array();

    /**
     * List of instances
     *
     * @access protected
     * @var array
     */
    protected $instances = array();


    /**
     * List of service classes
     *
     * @access protected
     * @var array
     */
    protected $services = array();

    /**
     * Pass callback from ProcedureHandler
     *
     * @param array $callbacks
     * @return $this
     */
    public function withCallbacks($callbacks)
    {
        $this->callbacks = $callbacks;
        return $this;
    }

    /**
     * Pass classes from ProcedureHandler
     *
     * @param array $classes
     * @return $this
     */
    public function withClasses($classes)
    {
        $this->classes = $classes;
        return $this;
    }

    /**
     * Pass instances from ProcedureHandler
     *
     * @param array $instances
     * @return $this
     */
    public function withInstances($instances)
    {
        $this->instances = $instances;
        return $this;
    }

    /**
     * Pass services from ProcedureHandler
     *
     * @param array $services
     * @return $this
     */
    public function withServices($services)
    {
        $this->services = $services;
        return $this;
    }

    /**
     * Build the Service Mapping Description (SMD)
     *
     * @param string $target URI of the service endpoint
     * @param bool|array|string $returnJSON
     * @return false|string|array
     * @throws \ReflectionException
     */
    public function build($target, $returnJSON = true) {
        $smd = array(
            'envelope'    => 'JSON-RPC-2.0',
            'transport'   => 'POST',
            'contentType' => 'application/json',
            'SMDVersion'  => '2.0',
            'target'      => $target,
            'services'    => array(),
            'methods'     => array(),
        );


        /**
         * ======================
         * Build callbacks
         * ======================
         */
        foreach ($this->callbacks as $procedure => $callback) {
            if(!isset($smd['services'][$procedure])) {
                $smd['services'][$procedure]  = $this->buildSmdService($procedure, new ReflectionFunction($callback));
            }
        }

        /**
         * ======================
         * Build classes
         * ======================
         */
        foreach ($this->classes as $procedure => $callback) {
            if(!isset($smd['services'][$procedure]) && method_exists($callback[0], $callback[1])) {
                $className       = is_object($callback[0]) ? get_class($callback[0]) : $callback[0];
                $methodReflection = (new ReflectionClass($className))->getMethod($callback[1]);

                if(!isset($smd['services'][$procedure])){
                    $smd['services'][$procedure]  = $this->buildSmdService($procedure, $methodReflection);
                }

            }
        }

        /**
         * ======================
         * Build services
         * ======================
         */
        foreach ($this->services as $namespace => $class) {
            $className       = is_object($class) ? get_class($class) : $class;
            $classReflection = new ReflectionClass($className);

            foreach ($classReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $methodReflection) {
                if (substr($methodReflection->name, 0, 1) !== '_' && !$methodReflection->isInternal()) {
                    $serviceName = $namespace . '.' . $methodReflection->getName();
                    if(!isset($smd['services'][$serviceName])){
                        $smd['services'][$serviceName]  = $this->buildSmdService($serviceName, $methodReflection);
                    }
                }
            }
        }

        /**
         * ======================
         * Build instances
         * ======================
         */
        foreach ($this->instances as $instance) {
            $className       = is_object($instance) ? get_class($instance) : $instance;
            $classReflection = new ReflectionClass($className);

            foreach ($classReflection->getMethods(ReflectionMethod::IS_PUBLIC) as $methodReflection) {
                $serviceName = $methodReflection->getName();
                if(!isset($smd['services'][$serviceName])){
                    $smd['services'][$serviceName]  = $this->buildSmdService($serviceName, $methodReflection);
                }
            }
        }

        $smd['methods'] = $smd['services'];

        if($returnJSON){
            return json_encode($smd);
        }
        return $smd;
    }

    /**
     * @param $name
     * @param ReflectionFunction|ReflectionMethod $reflection
     * @return array
     */
    protected function buildSmdService($name, $reflection)
    {
        $parameters = array();


        $paramDocTypes = array();
        $returnDoc     = array();
        if ($reflection->getDocComment()) {
            $docBlock = new DocBlock($reflection->getDocComment());
            if ($params = $docBlock->tag('param')) {
                foreach ($params as $param) {
                    if (substr($param['var'], 0, 1) === '$') {
                        $varName                 = substr($param['var'], 1);
                        $paramDocTypes[$varName] = $this->checkTypes(explode('|', $param['type']));
                        if (count($paramDocTypes[$varName]) === 1) {
                            $paramDocTypes[$varName] = $paramDocTypes[$varName][0];
                        }
                    }
                }
            }

            if ($returns = $docBlock->tag('return')) {
                if (!empty($returns[0]['type'])) {
                    $returnDoc = $this->checkTypes(explode('|', $returns[0]['type']));
                    if (count($returnDoc) === 1) {
                        $returnDoc = $returnDoc[0];
                    }
                }
            }
        }

        foreach ($reflection->getParameters() as $parameter) {
            $p = array(
                'name'     => $parameter->getName(),
                'type'     => $parameter->hasType() && $parameter->getType() ? $parameter->getType()->getName() : 'any',
                'optional' => $parameter->isOptional()
            );
            if ($parameter->isOptional()) {
                $p['default'] = $parameter->getDefaultValue();
            }
            if (!empty($paramDocTypes[$parameter->getName()])) {
                $p['type'] = $paramDocTypes[$parameter->getName()];
            }
            $parameters[] = $p;
        }

        $service = array(
            'name'        => $name,
            'envelope'    => 'JSON-RPC-2.0',
            'transport'   => 'POST',
            'description' => $reflection->getDocComment(),
            'parameters'  => $parameters,
            'returns'     => $reflection->hasReturnType() && $reflection->getReturnType() ? $reflection->getReturnType()->getName() : null
        );

        if (!empty($returnDoc)) {
            $service['returns'] = $returnDoc;
        }

        return $service;
    }

    /**
     * check and clean ver types
     *
     * @param array $types
     * @return array
     */
    protected function checkTypes($types)
    {
        $safeTypes = array(
            'any',
            'mixed',
            'array',
            'string',
            'float',
            'double',
            'bool',
            'boolean',
            'object',
            'null',
            'int',
            'integer',
        );
        if (!is_array($types)) {
            return [];
        }

        $types = array_unique($types);

        $returnTypes = array();
        foreach ($types as $type) {
            if (in_array(strtolower($type), $safeTypes)) {
                $returnTypes[] = strtolower($type);
            } elseif (class_exists($type)) {
                $returnTypes[] = 'object';
            }
            else {
                $returnTypes[] = 'any';
            }
        }

        return array_unique($returnTypes);
    }
}