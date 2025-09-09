<?php
/**
 * TPT Government Platform - TestCase Base Class
 *
 * Base class for all test cases with assertion methods,
 * mocking capabilities, and test lifecycle management
 */

abstract class TestCase
{
    protected $assertions = 0;
    protected $errors = [];
    protected $mocks = [];
    protected $setUpCalled = false;
    protected $tearDownCalled = false;

    /**
     * Test result statuses
     */
    const STATUS_PASSED = 'passed';
    const STATUS_FAILED = 'failed';
    const STATUS_ERROR = 'error';
    const STATUS_SKIPPED = 'skipped';

    /**
     * Run the test case
     */
    public function run()
    {
        $result = [
            'status' => self::STATUS_PASSED,
            'assertions' => 0,
            'errors' => [],
            'output' => ''
        ];

        try {
            // Setup
            ob_start();
            $this->setUp();
            $this->setUpCalled = true;

            // Run test methods
            $testMethods = $this->getTestMethods();
            foreach ($testMethods as $method) {
                $this->runTestMethod($method);
            }

            // Teardown
            $this->tearDown();
            $this->tearDownCalled = true;

            $result['output'] = ob_get_clean();
            $result['assertions'] = $this->assertions;

        } catch (Exception $e) {
            $result['status'] = self::STATUS_ERROR;
            $result['errors'][] = [
                'type' => 'exception',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ];
            $result['output'] = ob_get_clean();
        }

        // Check for failed assertions
        if (!empty($this->errors)) {
            $result['status'] = self::STATUS_FAILED;
            $result['errors'] = array_merge($result['errors'], $this->errors);
        }

        return $result;
    }

    /**
     * Setup method called before each test
     */
    protected function setUp()
    {
        // Override in subclasses
    }

    /**
     * Teardown method called after each test
     */
    protected function tearDown()
    {
        // Clean up mocks
        foreach ($this->mocks as $mock) {
            if (method_exists($mock, '__destruct')) {
                $mock->__destruct();
            }
        }
        $this->mocks = [];
    }

    /**
     * Get all test methods in this class
     */
    private function getTestMethods()
    {
        $methods = [];
        $reflection = new ReflectionClass($this);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (strpos($method->getName(), 'test') === 0) {
                $methods[] = $method->getName();
            }
        }

        return $methods;
    }

    /**
     * Run a single test method
     */
    private function runTestMethod($methodName)
    {
        try {
            $this->$methodName();
        } catch (Exception $e) {
            $this->errors[] = [
                'type' => 'test_failure',
                'method' => $methodName,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
        }
    }

    /**
     * Assert that a condition is true
     */
    protected function assertTrue($condition, $message = '')
    {
        $this->assertions++;
        if (!$condition) {
            $this->fail("Expected true, got " . var_export($condition, true), $message);
        }
    }

    /**
     * Assert that a condition is false
     */
    protected function assertFalse($condition, $message = '')
    {
        $this->assertions++;
        if ($condition) {
            $this->fail("Expected false, got " . var_export($condition, true), $message);
        }
    }

    /**
     * Assert that two values are equal
     */
    protected function assertEquals($expected, $actual, $message = '')
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->fail("Expected " . var_export($expected, true) . ", got " . var_export($actual, true), $message);
        }
    }

    /**
     * Assert that two values are not equal
     */
    protected function assertNotEquals($expected, $actual, $message = '')
    {
        $this->assertions++;
        if ($expected === $actual) {
            $this->fail("Expected different values, but both are " . var_export($expected, true), $message);
        }
    }

    /**
     * Assert that a value is null
     */
    protected function assertNull($value, $message = '')
    {
        $this->assertions++;
        if ($value !== null) {
            $this->fail("Expected null, got " . var_export($value, true), $message);
        }
    }

    /**
     * Assert that a value is not null
     */
    protected function assertNotNull($value, $message = '')
    {
        $this->assertions++;
        if ($value === null) {
            $this->fail("Expected not null, got null", $message);
        }
    }

    /**
     * Assert that a value is empty
     */
    protected function assertEmpty($value, $message = '')
    {
        $this->assertions++;
        if (!empty($value)) {
            $this->fail("Expected empty, got " . var_export($value, true), $message);
        }
    }

    /**
     * Assert that a value is not empty
     */
    protected function assertNotEmpty($value, $message = '')
    {
        $this->assertions++;
        if (empty($value)) {
            $this->fail("Expected not empty, got empty", $message);
        }
    }

    /**
     * Assert that a string contains a substring
     */
    protected function assertContains($needle, $haystack, $message = '')
    {
        $this->assertions++;
        if (strpos($haystack, $needle) === false) {
            $this->fail("Expected string to contain '$needle', got '$haystack'", $message);
        }
    }

    /**
     * Assert that a string does not contain a substring
     */
    protected function assertNotContains($needle, $haystack, $message = '')
    {
        $this->assertions++;
        if (strpos($haystack, $needle) !== false) {
            $this->fail("Expected string to not contain '$needle', got '$haystack'", $message);
        }
    }

    /**
     * Assert that an array has a key
     */
    protected function assertArrayHasKey($key, $array, $message = '')
    {
        $this->assertions++;
        if (!is_array($array) || !array_key_exists($key, $array)) {
            $this->fail("Expected array to have key '$key'", $message);
        }
    }

    /**
     * Assert that an array does not have a key
     */
    protected function assertArrayNotHasKey($key, $array, $message = '')
    {
        $this->assertions++;
        if (!is_array($array) || array_key_exists($key, $array)) {
            $this->fail("Expected array to not have key '$key'", $message);
        }
    }

    /**
     * Assert that a value is an instance of a class
     */
    protected function assertInstanceOf($expectedClass, $actual, $message = '')
    {
        $this->assertions++;
        if (!$actual instanceof $expectedClass) {
            $actualClass = is_object($actual) ? get_class($actual) : gettype($actual);
            $this->fail("Expected instance of $expectedClass, got $actualClass", $message);
        }
    }

    /**
     * Assert that a value is not an instance of a class
     */
    protected function assertNotInstanceOf($expectedClass, $actual, $message = '')
    {
        $this->assertions++;
        if ($actual instanceof $expectedClass) {
            $this->fail("Expected not instance of $expectedClass", $message);
        }
    }

    /**
     * Assert that a method throws an exception
     */
    protected function assertThrows($expectedException, callable $callback, $message = '')
    {
        $this->assertions++;
        $thrown = false;
        $actualException = null;

        try {
            $callback();
        } catch (Exception $e) {
            $thrown = true;
            $actualException = $e;
        }

        if (!$thrown) {
            $this->fail("Expected exception $expectedException to be thrown, but none was thrown", $message);
        } elseif (!$actualException instanceof $expectedException) {
            $this->fail("Expected exception $expectedException, got " . get_class($actualException), $message);
        }
    }

    /**
     * Assert that a file exists
     */
    protected function assertFileExists($filename, $message = '')
    {
        $this->assertions++;
        if (!file_exists($filename)) {
            $this->fail("Expected file '$filename' to exist", $message);
        }
    }

    /**
     * Assert that a file does not exist
     */
    protected function assertFileNotExists($filename, $message = '')
    {
        $this->assertions++;
        if (file_exists($filename)) {
            $this->fail("Expected file '$filename' to not exist", $message);
        }
    }

    /**
     * Skip a test
     */
    protected function skip($message = '')
    {
        throw new Exception("Test skipped: $message");
    }

    /**
     * Mark a test as incomplete
     */
    protected function markIncomplete($message = '')
    {
        throw new Exception("Test incomplete: $message");
    }

    /**
     * Fail a test with a message
     */
    protected function fail($message, $customMessage = '')
    {
        $fullMessage = $customMessage ?: $message;
        throw new Exception($fullMessage);
    }

    /**
     * Create a mock object
     */
    protected function createMock($className)
    {
        $mock = new MockObject($className);
        $this->mocks[] = $mock;
        return $mock;
    }

    /**
     * Create a partial mock
     */
    protected function createPartialMock($className, array $methods = [])
    {
        $mock = new MockObject($className, $methods);
        $this->mocks[] = $mock;
        return $mock;
    }

    /**
     * Get a data provider for parameterized tests
     */
    protected function getDataProvider($methodName)
    {
        $providerMethod = $methodName . 'DataProvider';
        if (method_exists($this, $providerMethod)) {
            return $this->$providerMethod();
        }
        return [];
    }

    /**
     * Set up database for testing
     */
    protected function setUpDatabase()
    {
        // Override in subclasses to set up test database
    }

    /**
     * Clean up database after testing
     */
    protected function tearDownDatabase()
    {
        // Override in subclasses to clean up test database
    }

    /**
     * Get test database connection
     */
    protected function getTestDatabase()
    {
        // Return test database instance
        // This should be configured in test environment
        return new Database([
            'host' => 'localhost',
            'database' => 'test_db',
            'username' => 'test_user',
            'password' => 'test_pass'
        ]);
    }

    /**
     * Assert that two arrays are equal (deep comparison)
     */
    protected function assertArraysEqual($expected, $actual, $message = '')
    {
        $this->assertions++;
        if (!$this->arraysAreEqual($expected, $actual)) {
            $this->fail("Arrays are not equal.\nExpected: " . var_export($expected, true) .
                       "\nActual: " . var_export($actual, true), $message);
        }
    }

    /**
     * Helper method to compare arrays deeply
     */
    private function arraysAreEqual($array1, $array2)
    {
        if (count($array1) !== count($array2)) {
            return false;
        }

        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                return false;
            }

            if (is_array($value) && is_array($array2[$key])) {
                if (!$this->arraysAreEqual($value, $array2[$key])) {
                    return false;
                }
            } elseif ($value !== $array2[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Assert that a JSON string is valid
     */
    protected function assertJson($string, $message = '')
    {
        $this->assertions++;
        json_decode($string);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Invalid JSON: " . json_last_error_msg(), $message);
        }
    }

    /**
     * Assert that a JSON string matches expected structure
     */
    protected function assertJsonStructure($expectedStructure, $jsonString, $message = '')
    {
        $this->assertions++;
        $data = json_decode($jsonString, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->fail("Invalid JSON: " . json_last_error_msg(), $message);
        }

        if (!$this->validateJsonStructure($expectedStructure, $data)) {
            $this->fail("JSON structure does not match expected structure", $message);
        }
    }

    /**
     * Validate JSON structure recursively
     */
    private function validateJsonStructure($structure, $data)
    {
        if (!is_array($structure) || !is_array($data)) {
            return false;
        }

        foreach ($structure as $key => $type) {
            if (!array_key_exists($key, $data)) {
                return false;
            }

            if (is_array($type)) {
                if (!is_array($data[$key]) || !$this->validateJsonStructure($type, $data[$key])) {
                    return false;
                }
            } elseif ($type === 'string' && !is_string($data[$key])) {
                return false;
            } elseif ($type === 'int' && !is_int($data[$key])) {
                return false;
            } elseif ($type === 'float' && !is_float($data[$key])) {
                return false;
            } elseif ($type === 'bool' && !is_bool($data[$key])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the number of assertions made
     */
    public function getAssertionCount()
    {
        return $this->assertions;
    }

    /**
     * Get the list of errors
     */
    public function getErrors()
    {
        return $this->errors;
    }
}

/**
 * Simple Mock Object Implementation
 */
class MockObject
{
    private $className;
    private $methods = [];
    private $constructorArgs = [];
    private $partialMethods = [];

    public function __construct($className, array $partialMethods = [])
    {
        $this->className = $className;
        $this->partialMethods = $partialMethods;
    }

    /**
     * Mock a method to return a specific value
     */
    public function method($methodName)
    {
        return new MockMethod($this, $methodName);
    }

    /**
     * Set constructor arguments
     */
    public function withConstructorArgs(...$args)
    {
        $this->constructorArgs = $args;
        return $this;
    }

    /**
     * Create the actual mock instance
     */
    public function getMock()
    {
        return new MockInstance($this->className, $this->methods, $this->constructorArgs, $this->partialMethods);
    }

    /**
     * Add a mocked method
     */
    public function addMethod($methodName, $returnValue)
    {
        $this->methods[$methodName] = $returnValue;
    }
}

/**
 * Mock Method Builder
 */
class MockMethod
{
    private $mock;
    private $methodName;

    public function __construct($mock, $methodName)
    {
        $this->mock = $mock;
        $this->methodName = $methodName;
    }

    public function willReturn($value)
    {
        $this->mock->addMethod($this->methodName, $value);
        return $this->mock;
    }

    public function willThrow($exception)
    {
        $this->mock->addMethod($this->methodName, function() use ($exception) {
            throw $exception;
        });
        return $this->mock;
    }
}

/**
 * Mock Instance
 */
class MockInstance
{
    private $className;
    private $methods;
    private $constructorArgs;
    private $partialMethods;
    private $instance;

    public function __construct($className, $methods, $constructorArgs, $partialMethods)
    {
        $this->className = $className;
        $this->methods = $methods;
        $this->constructorArgs = $constructorArgs;
        $this->partialMethods = $partialMethods;

        $this->createInstance();
    }

    private function createInstance()
    {
        if (!empty($this->constructorArgs)) {
            $reflection = new ReflectionClass($this->className);
            $this->instance = $reflection->newInstanceArgs($this->constructorArgs);
        } else {
            $this->instance = new $this->className();
        }
    }

    public function __call($methodName, $args)
    {
        if (isset($this->methods[$methodName])) {
            $returnValue = $this->methods[$methodName];
            return is_callable($returnValue) ? $returnValue(...$args) : $returnValue;
        }

        if (in_array($methodName, $this->partialMethods)) {
            return $this->instance->$methodName(...$args);
        }

        // Return null for unmocked methods
        return null;
    }

    public function __get($property)
    {
        if (property_exists($this->instance, $property)) {
            return $this->instance->$property;
        }
        return null;
    }

    public function __set($property, $value)
    {
        if (property_exists($this->instance, $property)) {
            $this->instance->$property = $value;
        }
    }
}
