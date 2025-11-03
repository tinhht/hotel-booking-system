<?php

/**
 * Repository Optimization Validation Test
 * Tests to ensure repository optimizations work correctly
 */

require_once __DIR__ . '/../../vendor/autoload.php';

class MockPDOStatement
{
    private array $data;
    
    public function __construct(array $data)
    {
        $this->data = $data;
    }
    
    public function fetchAll(): array
    {
        return $this->data;
    }
    
    public function fetch()
    {
        if (empty($this->data)) {
            return false;
        }
        return array_shift($this->data);
    }
}

class RepositoryOptimizationTest
{
    public function runTests(): void
    {
        echo "Running Repository Optimization Tests...\n\n";
        
        $this->testFetchAllVsWhileLoop();
        $this->testArrayMapPerformance();
        
        echo "\n✓ All repository optimization tests completed!\n";
    }
    
    private function testFetchAllVsWhileLoop(): void
    {
        echo "Test 1: Comparing fetchAll vs while loop\n";
        
        // Mock data
        $mockData = [
            ['id' => 1, 'name' => 'Room 1', 'price' => 100],
            ['id' => 2, 'name' => 'Room 2', 'price' => 200],
            ['id' => 3, 'name' => 'Room 3', 'price' => 300],
        ];
        
        // Test fetchAll approach (optimized)
        $stmt1 = new MockPDOStatement($mockData);
        $start = microtime(true);
        $results1 = $stmt1->fetchAll();
        $time1 = microtime(true) - $start;
        
        // Test while loop approach (old)
        $stmt2 = new MockPDOStatement($mockData);
        $start = microtime(true);
        $results2 = [];
        while ($row = $stmt2->fetch()) {
            $results2[] = $row;
        }
        $time2 = microtime(true) - $start;
        
        echo "  - fetchAll time: " . number_format($time1 * 1000000, 2) . " µs\n";
        echo "  - while loop time: " . number_format($time2 * 1000000, 2) . " µs\n";
        
        if (count($results1) === count($results2) && count($results1) === 3) {
            echo "  ✓ PASS: Both methods return correct number of results\n";
        } else {
            echo "  ✗ FAIL: Result count mismatch\n";
        }
    }
    
    private function testArrayMapPerformance(): void
    {
        echo "\nTest 2: Testing array_map for entity mapping\n";
        
        $mockData = [
            ['id' => 1, 'name' => 'Room 1'],
            ['id' => 2, 'name' => 'Room 2'],
            ['id' => 3, 'name' => 'Room 3'],
        ];
        
        // Mapper function
        $mapper = function($data) {
            return [
                'id' => $data['id'],
                'name' => strtoupper($data['name'])
            ];
        };
        
        // Test array_map approach (optimized)
        $start = microtime(true);
        $results1 = array_map($mapper, $mockData);
        $time1 = microtime(true) - $start;
        
        // Test foreach approach (old)
        $start = microtime(true);
        $results2 = [];
        foreach ($mockData as $data) {
            $results2[] = $mapper($data);
        }
        $time2 = microtime(true) - $start;
        
        echo "  - array_map time: " . number_format($time1 * 1000000, 2) . " µs\n";
        echo "  - foreach time: " . number_format($time2 * 1000000, 2) . " µs\n";
        
        if (count($results1) === 3 && $results1[0]['name'] === 'ROOM 1') {
            echo "  ✓ PASS: array_map correctly maps entities\n";
        } else {
            echo "  ✗ FAIL: array_map mapping incorrect\n";
        }
    }
}

// Run tests
$test = new RepositoryOptimizationTest();
$test->runTests();
