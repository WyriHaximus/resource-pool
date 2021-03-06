<?php
namespace ResourcePool;

use React\Promise\FulfilledPromise;
use React\Promise\Deferred;

/**
 * This class exists to keep the public interface of Pool clean. Once PHP 5.3 support is dropped,
 * this functionality will probably be moved to Pool in the form of private methods.
 *
 * @author Josh Di Fabio <joshdifabio@gmail.com>
 *
 * @internal
 */
class PoolInternal
{
    private $size;
    private $usage = 0;
    private $queue;
    private $whenNextIdle;

    public function __construct($size = null)
    {
        $this->size = $size;
        $this->queue = new \SplQueue;
        $this->whenNextIdle = new Deferred;
        $this->whenNextIdle->resolve();
    }

    public function allocate($count)
    {
        return $this->createAllocationPromise('ResourcePool\PartialAllocationPromise', $count);
    }

    public function allocateAll()
    {
        return $this->createAllocationPromise('ResourcePool\AllocationPromise');
    }

    private function createAllocationPromise($promiseClass, $count = null)
    {
        $this->beforeAllocate();
        
        if ($this->canAllocate($count)) {
            $allocation = $this->createAllocation($count);
            $promise = new FulfilledPromise($allocation);
            $resolver = null;
        } else {
            $deferred = new Deferred;
            $promise = $deferred->promise();
            $isResolved = false;
            $resolver = $this->createResolver($isResolved, $count, $deferred);
            $promise->then(null, array($this, 'afterPromiseCancelled'));
            $this->queue->enqueue(array($count, $deferred, &$isResolved));
        }

        return new $promiseClass($promise, $resolver);
    }
    
    public function setSize($size)
    {
        $this->size = $size;
        $this->processQueue();
    }

    public function getAvailability()
    {
        return max(0, $this->size - $this->usage);
    }

    public function getUsage()
    {
        return $this->usage;
    }

    private function processQueue()
    {
        foreach ($this->queue as $allocationInfo) {
            if (true === $allocationInfo[2]) {
                $this->queue->dequeue();
                continue;
            }

            if (!$this->canAllocate($allocationInfo[0])) {
                break;
            }

            $this->queue->dequeue();
            $allocation = $this->createAllocation($allocationInfo[0]);
            $allocationInfo[1]->resolve($allocation);
        }
    }
    
    public function afterPromiseCancelled()
    {
        $this->decrementUsage(0);
    }

    public function decrementUsage($amount)
    {
        $this->usage -= $amount;
        $this->processQueue();
        if ($this->isIdle()) {
            $this->whenNextIdle->resolve();
        }
    }

    public function canAllocate($count = null)
    {
        if (null === $count) {
            return 0 === $this->usage && $this->size > 0;
        }

        return $count <= $this->getAvailability();
    }

    public function createAllocation($size)
    {
        if (null === $size) {
            $size = $this->size;
        }

        $this->usage += $size;

        return new Allocation(array($this, 'decrementUsage'), $size);
    }

    public function whenNextIdle($fulfilledHandler = null)
    {
        $promise = $this->whenNextIdle->promise();

        if (null !== $fulfilledHandler) {
            return $promise->then($fulfilledHandler);
        }

        return $promise;
    }

    private function beforeAllocate()
    {
        if ($this->isIdle()) {
            $this->whenNextIdle = new Deferred;
        }
    }

    private function isIdle()
    {
        return (0 == $this->usage && 0 == $this->queue->count());
    }
    
    private function createResolver(&$isResolved, $count, $deferred)
    {
        $that = $this;
        
        return function ($burst) use (&$isResolved, $that, $count, $deferred) {
            if ($isResolved) {
                throw new \LogicException;
            }

            $isResolved = true;
            
            if ($burst || $that->canAllocate($count)) {
                $allocation = $that->createAllocation($count);
                $deferred->resolve($allocation);
            } else {
                $deferred->reject(new \RuntimeException('The resource pool cannot allocate the specified number of resources'));
            }
        };
    }
}
