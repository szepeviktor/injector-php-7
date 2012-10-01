<?php

namespace Auryn;

use BadFunctionCallException,
    InvalidArgumentException,
    OutOfBoundsException,
    ReflectionException,
    Traversable,
    StdClass;

class Provider implements Injector {
    
    /**
     * @var string
     */
    const RAW_INJECTION_PREFIX = ':';
    
    /**
     * @var array
     */
    private $injectionDefinitions = array();
    
    /**
     * @var array
     */
    private $nonConcreteimplementations = array();
    
    /**
     * @var array
     */
    private $sharedClasses = array();

    /**
     * @var array
     */
    private $delegatedClasses = array();

    /**
     * @var ReflectionStorage
     */
    private $reflStorage;
    
    /**
     * @param ReflectionStorage $reflStorage
     * @return void
     */
    public function __construct(ReflectionStorage $reflStorage) {
        $this->reflStorage = $reflStorage;
    }
    
    /**
     * Instantiate a class according to a predefined or call-time injection definition
     * 
     * @param string $class Class name
     * @param array  $customDefinition An optional array of custom instantiation parameters
     * 
     * @return mixed A dependency-injected object
     * @throws InjectionException
     */
    public function make($class, array $customDefinition = NULL) {
        $lowClass = strtolower($class);
        
        if (isset($this->sharedClasses[$lowClass])) {
            return $this->sharedClasses[$lowClass];
        }
        if ($this->isDelegated($lowClass)) {
            try {
                $obj = call_user_func($this->delegatedClasses[$lowClass], $class);
            } catch (\Exception $error) {
                throw new InjectionException(
                    "Delegated function threw an exception while creating '$class'",
                    0,
                    $error
                );
            }

            if (!($obj instanceof $class)) {
                throw new InjectionException(
                    "Delegated function did not create an instance of '$class'"
                );
            }
        } else {
            if (!is_null($customDefinition)) {
                $definition = $customDefinition;
            } elseif ($this->isDefined($class)) {
                $definition = $this->injectionDefinitions[$lowClass];
            } else {
                $definition = array();
            }

            $obj = $this->getInjectedInstance($class, $definition);
        }
        
        if ($this->isShared($lowClass)) {
            $this->sharedClasses[$lowClass] = $obj;
        }
        
        return $obj;
    }
    
    /**
     * Defines a custom injection definition for the specified class
     * 
     * @param string $className
     * @param array $injectionDefinition An associative array matching constructor params to values
     * @return void
     * @throws InjectionException
     */
    public function define($className, array $injectionDefinition) {
        $this->validateInjectionDefinition($injectionDefinition);
        $lowClass = strtolower($className);
        $this->injectionDefinitions[$lowClass] = $injectionDefinition;
    }
    
    /**
     * @param array $injectionDefinition
     * @throws InjectionException
     */
    private function validateInjectionDefinition(array $injectionDefinition) {
        foreach ($injectionDefinition as $paramName => $value) {
            if (0 !== strpos($paramName, ':') && !is_string($value)) {
                throw new InjectionException(
                    "Invalid injection definition for parameter `$paramName`; raw parameter " .
                    "names must be prefixed with `r:` (r:$paramName) to differentiate them " .
                    'from provisionable class names.'
                );
            }
        }
    }
    
    /**
     * Retrieves the custom definition for the specified class
     * 
     * @param string $className
     * @return array
     */
    public function getDefinition($className) {
        if (!$this->isDefined($className)) {
            throw new OutOfBoundsException("No definition specified for $className");
        }
        $lowClass = strtolower($className);
        return $this->injectionDefinitions[$lowClass];
    }
    
    /**
     * Determines if an injection definition exists for the given class name
     * 
     * @param string $class Class name
     * @return bool Returns true if a definition is stored or false otherwise
     */
    public function isDefined($class) {
        $lowClass = strtolower($class);
        return isset($this->injectionDefinitions[$lowClass]);
    }
    
    /**
     * Defines multiple injection definitions at one time
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass or Traversable
     * @return int Returns the number of definitions stored by the operation.
     */
    public function defineAll($iterable) {
        if (!($iterable instanceof StdClass
            || is_array($iterable)
            || $iterable instanceof Traversable)
        ) {
            throw new InvalidArgumentException(
                get_class($this) . '::defineAll expects an array, StdClass or '
                .'Traversable object at Argument 1'
            );
        }
        
        $added = 0;
        foreach ($iterable as $class => $definition) {
            $this->define($class, $definition);
            ++$added;
        }
        
        return $added;
    }
    
    /**
     * Clear a previously defined injection definition
     * 
     * @param string $class Class name
     * @return void
     */
    public function clearDefinition($class) {
        $lowClass = strtolower($class);
        unset($this->injectionDefinitions[$lowClass]);
    }
    
    /**
     * Clear all injection definitions from the container
     * 
     * @return void
     */
    public function clearAllDefinitions() {
        $this->injectionDefinitions = array();
    }
    
    /**
     * Defines an implementation class for all occurrences of a given interface or abstract
     * 
     * @param string $nonConcreteType
     * @param string $className
     * @return void
     */
    public function implement($nonConcreteType, $className) {
        $lowNonConcrete = strtolower($nonConcreteType);
        $this->nonConcreteimplementations[$lowNonConcrete] = $className;
    }
    
    /**
     * Retrive the assigned implementation class for the non-concrete type
     * 
     * @param string $nonConcreteType
     * @return string Returns the concrete class implementation name
     * @throws \OutOfBoundsException
     */
    public function getImplementation($nonConcreteType) {
        if (!$this->isImplemented($nonConcreteType)) {
            throw new OutOfBoundsException(
                "The non-concrete typehint $nonConcreteType has no assigned implementation"
            );
        }
        $lowNonConcrete = strtolower($nonConcreteType);
        return $this->nonConcreteimplementations[$lowNonConcrete];
    }
    
    /**
     * Determines if an implementation is specified for the non-concrete type
     * 
     * @param string $nonConcreteType
     * @return bool
     */
    public function isImplemented($nonConcreteType) {
        $lowNonConcrete = strtolower($nonConcreteType);
        return isset($this->nonConcreteimplementations[$lowNonConcrete]);
    }
    
    /**
     * Defines multiple type implementations at one time
     * 
     * @param mixed $iterable The variable to iterate over: an array, StdClass or Traversable
     * @return int Returns the number of implementations stored by the operation.
     */
    public function implementAll($iterable) {
        if (!($iterable instanceof StdClass
            || is_array($iterable)
            || $iterable instanceof Traversable)
        ) {
            throw new InvalidArgumentException(
                get_class($this) . '::implementAll expects an array, StdClass or '
                .'Traversable object at Argument 1'
            );
        }
        
        $added = 0;
        foreach ($iterable as $nonConcreteType => $implementationClass) {
            $this->implement($nonConcreteType, $implementationClass);
            ++$added;
        }
        
        return $added;
    }
    
    /**
     * Clears an existing implementation definition for the non-concrete type
     * 
     * @param string $nonConcreteType
     * @return void
     */
    public function clearImplementation($nonConcreteType) {
        $lowNonConcrete = strtolower($nonConcreteType);
        unset($this->nonConcreteimplementations[$lowNonConcrete]);
    }
    
    /**
     * Clears an existing implementation definition for the non-concrete type
     * 
     * @param string $nonConcreteType
     * @return void
     */
    public function clearAllImplementations() {
        $this->nonConcreteimplementations = array();
    }
    
    /**
     * Stores a shared instance of the specified class
     * 
     * If an instance of the class is specified, it will be stored and shared
     * for calls to `Provider::make` for that class until the shared instance
     * is manually removed or refreshed.
     * 
     * If a string class name is specified, the Provider will mark the class
     * as "shared" and the next time the Provider is used to instantiate the
     * class it's instance will be stored and shared.
     * 
     * @param mixed $classNameOrInstance
     * @return void
     * @throws InvalidArgumentException
     */
    public function share($classNameOrInstance) {
        if (is_string($classNameOrInstance)) {
            $lowClass = strtolower($classNameOrInstance);
            $this->sharedClasses[$lowClass] = NULL;
        } elseif (is_object($classNameOrInstance)) {
            $lowClass = strtolower(get_class($classNameOrInstance));
            $this->sharedClasses[$lowClass] = $classNameOrInstance;
        } else {
            $parameterType = gettype($classNameOrInstance);
            throw new InvalidArgumentException(
                get_class($this).'::share() requires a string class name or object instance at ' .
                "Argument 1; $parameterType specified"
            );
        }
    }
    
    /**
     * Shares all specified classes/instances
     * 
     * @param mixed $arrayOrTraversable
     * @return void
     * @throws InvalidArgumentException
     */
    public function shareAll($arrayOrTraversable) {
        if (!(is_array($arrayOrTraversable) || $arrayOrTraversable instanceof Traversable)) {
            $type = is_object($arrayOrTraversable)
                ? get_class($arrayOrTraversable)
                : gettype($arrayOrTraversable);
            throw new InvalidArgumentException(
                get_class($this).'::shareAll() requires an array or Traversable object at ' .
                "Argument 1; $type specified"
            );
        }
        
        foreach ($arrayOrTraversable as $toBeShared) {
            $this->share($toBeShared);
        }
    }
    
    /**
     * Determines if a given class name is marked as shared
     * 
     * @param string $class Class name
     * @return bool Returns true if a shared instance is stored or false if not
     */
    public function isShared($class) {
        $lowClass = strtolower($class);
        return isset($this->sharedClasses[$lowClass])
            || array_key_exists($lowClass, $this->sharedClasses);
    }
    
    /**
     * Forces re-instantiation of a shared class the next time it's requested
     * 
     * @param string $class Class name
     * @return void
     */
    public function refresh($class) {
        $lowClass = strtolower($class);
        if (isset($this->sharedClasses[$lowClass])) {
            $this->sharedClasses[$lowClass] = NULL;
        }
    }
    
    /**
     * Unshares the specified class
     * 
     * @param string $class Class name
     * @return void
     */
    public function unshare($class) {
        $lowClass = strtolower($class);
        unset($this->sharedClasses[$lowClass]);
    }

    /**
     * Delegates the creation of $class to $callable.  Passes $class to $callable as the only
     * argument
     *
     * @param string $class
     * @param callable $callable
     * @throws \BadFunctionCallException
     */
    public function delegate($class, $callable) {
        if (!is_callable($callable)) {
            throw new BadFunctionCallException(
                get_class($this) . '::delegate expects the second parameter to be callable'
            );
        }

        $this->delegatedClasses[strtolower($class)] = $callable;
    }
    
    /**
     * @param string $className
     * @return mixed Returns a dependency-injected object
     * @throws InjectionException
     */
    protected function getInjectedInstance($className, array $definition) {
        try {
            $ctorParams = $this->reflStorage->getConstructorParameters($className);
        } catch (ReflectionException $e) {
            throw new InjectionException(
                "Provider instantiation failure: $className doesn't exist".
                ' and could not be found by any registered autoloaders.',
                NULL, $e
            );
        }
        
        if (!$ctorParams) {
        
            return $this->buildWithoutConstructorParams($className);
            
        } else {
        
            try {
                $args = $this->buildNewInstanceArgs($ctorParams, $definition);
            } catch (InjectionException $e) {
                $msg = $e->getMessage() . " in $className::__construct";
                throw new InjectionException($msg);
            }
            
            $reflClass = $this->reflStorage->getClass($className);
            
            return $reflClass->newInstanceArgs($args);
        }
    }
    
    /**
     * @param string $className
     * @return mixed
     */
    private function buildWithoutConstructorParams($className) {
        if ($this->isInstantiable($className)) {
            return new $className;
        } elseif ($this->isImplemented($className)) {
            return $this->buildImplementation($className);
        } else {
            $reflClass = $this->reflStorage->getClass($className);
            $type = $reflClass->isInterface() ? 'interface' : 'abstract';
            throw new InjectionException(
                "Cannot instantiate $type $className without an injection definition or " .
                "implementation"
            );
        }
    }
    
    /**
     * @param string $className
     * @return bool
     */
    private function isInstantiable($className) {
        $reflInstance = $this->reflStorage->getClass($className);
        return $reflInstance ->isInstantiable();
    }
    
    /**
     * @param string $interfaceOrAbstractName
     * @return mixed
     */
    private function buildImplementation($interfaceOrAbstractName) {
        $implClass = $this->getImplementation($interfaceOrAbstractName);
        $implObj   = $this->make($implClass);
        $implRefl  = $this->reflStorage->getClass($implClass);
        
        if (!$implRefl->isSubclassOf($interfaceOrAbstractName)) {
            throw new InvalidImplementationException(
                "Bad implementation: {$implRefl->name} does not implement $interfaceOrAbstractName"
            );
        }
        
        return $implObj;
    }
    
    /**
     * @param arrayarray $reflectedCtorParams
     * @param array $definition
     * @return array
     * @throws InjectionException 
     */
    private function buildNewInstanceArgs(array $reflectedCtorParams, array $definition) {
        $instanceArgs = array();
        
        for ($i=0; $i<count($reflectedCtorParams); $i++) {
            
            $paramName = $reflectedCtorParams[$i]->name;
            
            if (isset($definition[$paramName])) {
                $instanceArgs[] = $this->make($definition[$paramName]);
                continue;
            }
            
            $rawParamKey = self::RAW_INJECTION_PREFIX . $paramName;
            if (isset($definition[$rawParamKey])) {
                $instanceArgs[] = $definition[$rawParamKey];
                continue;
            }
            
            $reflectedParam = $reflectedCtorParams[$i];
            $typehint = $this->reflStorage->getTypehint($reflectedParam);
            
            if ($typehint && $this->isInstantiable($typehint)) {
                $instanceArgs[] = $this->make($typehint);
            } elseif ($typehint) {
                $instanceArgs[] = $this->buildAbstractTypehintParam($typehint, $paramName, $i+1);
            } elseif ($reflectedParam->isDefaultValueAvailable()) {
                $instanceArgs[] = $reflectedParam->getDefaultValue();
            } else {
                $instanceArgs[] = NULL;
            }
        }
        
        return $instanceArgs;
    }
    
    /**
     * @param string $typehint
     * @param string $paramName
     * @param int $argNum
     * @return mixed
     * @throws InjectionException
     */
    private function buildAbstractTypehintParam($typehint, $paramName, $argNum) {
        if ($this->isImplemented($typehint)) {
            try {
                return $this->buildImplementation($typehint);
            } catch (InvalidImplementationException $e) {
                throw new InvalidImplementationException(
                    'Bad implementation definition encountered while attempting to provision ' .
                    "non-concrete parameter \$$paramName of type $typehint at argument $argNum",
                    NULL,
                    $e
                );
            }
        }
        
        throw new InjectionException(
            'Injection definition/implementation required for non-concrete constructor '.
            "parameter \$$paramName of type $typehint at argument $argNum"
        );
    }

    private function isDelegated($class) {
        return array_key_exists($class, $this->delegatedClasses);
    }
}
