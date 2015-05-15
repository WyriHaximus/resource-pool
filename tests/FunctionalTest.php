<?php
namespace ResourcePool;

/**
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 */
class PoolTest extends \PHPUnit_Framework_TestCase
{
    public function testAllocate()
    {
        $pool = new Pool(1);

        $this->assertEquals(0, $pool->getUsage());

        $firstAllocation = null;

        $pool->allocate()->then(function ($allocation) use (&$firstAllocation) {
            $firstAllocation = $allocation;
        });
        $this->assertNotNull($firstAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $secondAllocation = null;

        $pool->allocate()->then(function ($allocation) use (&$secondAllocation) {
            $secondAllocation = $allocation;
        });
        $this->assertNull($secondAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $thirdAllocation = $pool->allocate()->now();
        $this->assertNull($secondAllocation);
        $this->assertEquals(2, $pool->getUsage());

        for ($i = 0; $i < 2; $i++) {
            $thirdAllocation->releaseAll();
            $this->assertNull($secondAllocation);
            $this->assertEquals(1, $pool->getUsage());
        }

        $firstAllocation->releaseAll();
        $this->assertNotNull($secondAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $secondAllocation->releaseAll();
        $this->assertEquals(0, $pool->getUsage());
    }

    public function testSizeIncrease()
    {
        $pool = new Pool(1);

        $this->assertEquals(0, $pool->getUsage());

        $firstAllocation = null;

        $pool->allocate()->then(function ($allocation) use (&$firstAllocation) {
            $firstAllocation = $allocation;
        });
        $this->assertNotNull($firstAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $secondAllocation = null;

        $pool->allocate()->then(function ($allocation) use (&$secondAllocation) {
            $secondAllocation = $allocation;
        });
        $this->assertNull($secondAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $pool->setSize(1);
        $this->assertNull($secondAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $pool->setSize(0);
        $this->assertNull($secondAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $pool->setSize(2);
        $this->assertNotNull($secondAllocation);
        $this->assertEquals(2, $pool->getUsage());
    }

    public function testAllocateAll()
    {
        $pool = new Pool(2);

        $this->assertEquals(0, $pool->getUsage());

        $firstAllocation = null;

        $pool->allocate()->then(function ($allocation) use (&$firstAllocation) {
            $firstAllocation = $allocation;
        });
        $this->assertNotNull($firstAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $secondAllocation = null;

        $pool->allocate()->then(function ($allocation) use (&$secondAllocation) {
            $secondAllocation = $allocation;
        });
        $this->assertNotNull($secondAllocation);
        $this->assertEquals(2, $pool->getUsage());

        $fullAllocation = null;

        $pool->allocateAll()->then(function ($allocation) use (&$fullAllocation) {
            $fullAllocation = $allocation;
        });
        $this->assertNull($fullAllocation);
        $this->assertEquals(2, $pool->getUsage());

        $pool->setSize(3);

        $firstAllocation->releaseAll();
        $this->assertNull($fullAllocation);
        $this->assertEquals(1, $pool->getUsage());

        $secondAllocation->releaseAll();
        $this->assertNotNull($fullAllocation);
        $this->assertEquals(3, $pool->getUsage());
    }
}
