<?php

namespace Codeception\Module;


use Codeception\Exception\ModuleException;
use Codeception\Lib\ModuleContainer;
use Codeception\Module;
use Codeception\TestCase;
use tad\WPBrowser\Environment\Constants;
use tad\WPBrowser\Iterators\Filters\FactoryQueriesFilter;
use tad\WPBrowser\Iterators\Filters\MainStatementQueriesFilter;
use tad\WPBrowser\Iterators\Filters\SetupTearDownQueriesFilter;

class WPQueries extends Module
{
    /**
     * @var array
     */
    protected $filteredQueries = [];

    /**
     * @var callable[]
     */
    protected $assertions = [];

    /**
     * @var Constants
     */
    private $constants;

    /**
     * WPQueries constructor.
     *
     * @param ModuleContainer $moduleContainer
     * @param null $config
     * @param Constants|null $constants
     */
    public function __construct(ModuleContainer $moduleContainer, $config, Constants $constants = null)
    {
        $this->constants = $constants ? $constants : new Constants();
        parent::__construct($moduleContainer, $config);
    }

    public function _initialize()
    {
        try {
            $this->moduleContainer->getModule('WPLoader');
        } catch (ModuleException $e) {
            throw new ModuleException(__CLASS__, "Module WPLoader is required for WPQueries to work");
        }

        $this->constants->defineIfUndefined('SAVEQUERIES', true);
    }

    /**
     * Runs before each test method.
     */
    public function _cleanup()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;
        $wpdb->queries = [];
    }

    /**
     * Runs after each test method.
     *
     * @param TestCase $test
     */
    public function _after(TestCase $test)
    {
        $this->readQueries();
        $this->runAssertions();
    }

    /**
     * Returns the saved queries after filtering.
     *
     * @param \wpdb $wpdb
     * @return \FilterIterator
     */
    public function _getFilteredQueriesIterator(\wpdb $wpdb = null)
    {
        if (null === $wpdb) {
            /** @var \wpdb $wpdb */
            global $wpdb;
        }

        $queriesArrayIterator = new \ArrayIterator($wpdb->queries);
        $filteredQueriesIterator = new SetupTearDownQueriesFilter(new FactoryQueriesFilter($queriesArrayIterator));

        return $filteredQueriesIterator;
    }

    /**
     * Asserts that at leas one query was made during the test.
     *
     * Queries generated by setUp, tearDown and factory methods are excluded by default.
     *
     * @param string $message
     */
    public function assertQueries($message = '')
    {
        $message = $message ? $message : 'Failed asserting that queries were made.';
        $this->assertions[] = function ($queries) use ($message) {
            \PHPUnit_Framework_Assert::assertNotEmpty($queries, $message);
        };
    }

    /**
     * Asserts that no queries were made.
     *
     * Queries generated by setUp, tearDown and factory methods are excluded by default.
     *
     * @param string $message
     */
    public function assertNotQueries($message = '')
    {
        $message = $message ? $message : 'Failed asserting that no queries were made.';
        $this->assertions[] = function ($queries) use ($message) {
            \PHPUnit_Framework_Assert::assertEmpty($queries, $message);
        };
    }

    /**
     * Asserts that n queries have been made.
     *
     * @param int $n
     * @param string $message
     */
    public function assertCountQueries($n, $message = '')
    {
        $message = $message ? $message : 'Failed asserting that ' . $n . ' queries were made.';
        $this->assertions[] = function ($queries) use ($n, $message) {
            \PHPUnit_Framework_Assert::assertCount($n, $queries, $message);
        };
    }

    /**
     * Runs all the assertions registered on the module.
     *
     * @throws \PHPUnit_Framework_AssertionFailedError
     */
    private function runAssertions()
    {
        foreach ($this->assertions as $f) {
            $f($this->filteredQueries);
        }
    }

    private function readQueries()
    {
        /** @var \wpdb $wpdb */
        global $wpdb;

        if (empty($wpdb->queries)) {
            $this->filteredQueries = [];
        } else {
            $filteredQueriesIterator = $this->_getFilteredQueriesIterator();
            $this->filteredQueries = iterator_to_array($filteredQueriesIterator);
        }

    }

    public function assertQueriesByStatement($statement, $message = '')
    {
        $message = $message ? $message : 'Failed asserting that queries beginning with statement [' . $statement . '] were made.';
        $this->assertions[] = function ($queries) use ($statement, $message) {
            $statementIterator = new MainStatementQueriesFilter(new \ArrayIterator($queries));
            \PHPUnit_Framework_Assert::assertNotEmpty(iterator_to_array($statementIterator), $message);
        };
    }

    public function assertQueriesCountByStatement($n, $statement, $message = '')
    {
        $message = $message ? $message : 'Failed asserting that ' . $n . ' queries beginning with statement [' . $statement . '] were made.';
        $this->assertions[] = function ($queries) use ($n, $statement, $message) {
            $statementIterator = new MainStatementQueriesFilter(new \ArrayIterator($queries));
            \PHPUnit_Framework_Assert::assertCount($n, iterator_to_array($statementIterator), $message);
        };
    }
}