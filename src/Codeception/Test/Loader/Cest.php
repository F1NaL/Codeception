<?php
namespace Codeception\Test\Loader;

use Codeception\Exception\TestParseException;
use Codeception\Lib\ExampleSuite;
use Codeception\Lib\Parser;
use Codeception\Test\Cest as CestFormat;
use Codeception\Test\Descriptor;
use Codeception\Util\Annotation;

class Cest implements LoaderInterface
{
    protected $tests = [];
    protected $settings = [];

    public function __construct($settings)
    {
        $this->settings = $settings;
    }

    public function getTests()
    {
        return $this->tests;
    }

    public function getPattern()
    {
        return '~Cest\.php$~';
    }

    public function loadTests($file)
    {
        Parser::load($file);
        $testClasses = Parser::getClassesFromFile($file);

        foreach ($testClasses as $testClass) {
            if (substr($testClass, -strlen('Cest')) !== 'Cest') {
                continue;
            }
            if (!(new \ReflectionClass($testClass))->isInstantiable()) {
                continue;
            }
            $unit = new $testClass;

            $methods = get_class_methods($testClass);
            foreach ($methods as $method) {
                if (strpos($method, '_') === 0) {
                    continue;
                }

                $rawExamples = Annotation::forMethod($unit, $method)->fetchAll('example');
                $rawExamplesFromConfig = Annotation::forMethod($unit, $method)->fetchAll('configExample');
                if (count($rawExamples) + count($rawExamplesFromConfig)) {
                    $examples = array_map(
                        function ($v) {
                            return Annotation::arrayValue($v);
                        },
                        $rawExamples
                    );
                    foreach ($rawExamplesFromConfig as $example) {
                        $examples = array_merge($examples, Annotation::arrayValue($example, $this->settings));
                    }
                    $dataProvider = new \PHPUnit_Framework_TestSuite_DataProvider();
                    foreach ($examples as $k => $example) {
                        if ($example === null) {
                            throw new TestParseException(
                                $file,
                                "Example for $testClass->$method contains invalid data:\n" .
                                $rawExamples[$k] . "\n" .
                                "Make sure this is a valid JSON (Hint: \"-char for strings) or a single-line annotation in Doctrine-style"
                            );
                        }
                        $test = new CestFormat($unit, $method, $file);
                        $test->getMetadata()->setCurrent(['example' => $example]);
                        $dataProvider->addTest($test);
                    }
                    $this->tests[] = $dataProvider;
                    continue;
                }
                $this->tests[] = new CestFormat($unit, $method, $file);
            }
        }
    }
}
