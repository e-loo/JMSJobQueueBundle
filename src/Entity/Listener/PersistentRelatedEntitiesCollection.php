<?php
/**
 * © Eloo <info@eloo.nl> This bundle of is a fork of the JMS Job Queue Bundle and is thus NOT part of the Trade Secret license.
 */

namespace JMS\JobQueueBundle\Entity\Listener;

use ArrayIterator;
use Closure;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Collections\Expr\ClosureExpressionVisitor;
use Doctrine\Common\Collections\Order;
use Doctrine\Common\Collections\Selectable;
use Doctrine\ORM\EntityManagerInterface;
use JMS\JobQueueBundle\Entity\Job;
use LogicException;
use ReturnTypeWillChange;
use Stringable;
use Traversable;

/**
 * Collection for persistent related entities.
 *
 * We do not support all of Doctrine's built-in features.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class PersistentRelatedEntitiesCollection implements Collection, Selectable, Stringable
{
    private array $entities;

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly Job $job)
    {
    }

    /**
     * Gets the PHP array representation of this collection.
     *
     * @return array<object> the PHP array representation of this collection
     */
    #[ReturnTypeWillChange]
    public function toArray()
    {
        $this->initialize();

        return $this->entities;
    }

    /**
     * Sets the internal iterator to the first element in the collection and
     * returns this element.
     *
     * @return object|false
     */
    #[ReturnTypeWillChange]
    public function first()
    {
        $this->initialize();

        return reset($this->entities);
    }

    #[ReturnTypeWillChange]
    public function findFirst(Closure $p)
    {
        foreach ($this->elements as $key => $element) {
            if ($p($key, $element)) {
                return $element;
            }
        }

        return null;
    }

    #[ReturnTypeWillChange]
    public function reduce(Closure $func, mixed $initial = null)
    {
        return array_reduce($this->elements, $func, $initial);
    }

    /**
     * Sets the internal iterator to the last element in the collection and
     * returns this element.
     *
     * @return object|false
     */
    #[ReturnTypeWillChange]
    public function last()
    {
        $this->initialize();

        return end($this->entities);
    }

    /**
     * Gets the current key/index at the current internal iterator position.
     *
     * @return string|int
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        $this->initialize();

        return key($this->entities);
    }

    /**
     * Moves the internal iterator position to the next element.
     *
     * @return object|false
     */
    #[ReturnTypeWillChange]
    public function next()
    {
        $this->initialize();

        return next($this->entities);
    }

    /**
     * Gets the element of the collection at the current internal iterator position.
     *
     * @return object|false
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        $this->initialize();

        return current($this->entities);
    }

    /**
     * Removes an element with a specific key/index from the collection.
     *
     * @param string|int $key
     *
     * @return object|null the removed element or NULL, if no element exists for the given key
     */
    public function remove($key): object|null
    {
        throw new LogicException('remove() is not supported.');
    }

    /**
     * Removes the specified element from the collection, if it is found.
     *
     * @param object $element the element to remove
     *
     * @return bool TRUE if this collection contained the specified element, FALSE otherwise
     */
    public function removeElement($element): bool
    {
        throw new LogicException('removeElement() is not supported.');
    }

    /**
     * ArrayAccess implementation of offsetExists().
     *
     * @see containsKey()
     */
    public function offsetExists(mixed $offset): bool
    {
        $this->initialize();

        return $this->containsKey($offset);
    }

    /**
     * ArrayAccess implementation of offsetGet().
     *
     * @see get()
     */
    public function offsetGet(mixed $offset): mixed
    {
        $this->initialize();

        return $this->get($offset);
    }

    /**
     * ArrayAccess implementation of offsetSet().
     *
     * @see add()
     * @see set()
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new LogicException('Adding new related entities is not supported after initial creation.');
    }

    /**
     * ArrayAccess implementation of offsetUnset().
     *
     * @see remove()
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new LogicException('unset() is not supported.');
    }

    /**
     * Checks whether the collection contains a specific key/index.
     *
     * @param mixed $key the key to check for
     *
     * @return bool TRUE if the given key/index exists, FALSE otherwise
     */
    public function containsKey($key): bool
    {
        $this->initialize();

        return isset($this->entities[$key]);
    }

    /**
     * Checks whether the given element is contained in the collection.
     * Only element values are compared, not keys. The comparison of two elements
     * is strict, that means not only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @return bool TRUE if the given element is contained in the collection,
     *              FALSE otherwise
     */
    public function contains($element): bool
    {
        $this->initialize();

        return in_array($element, $this->entities, true);
    }

    /**
     * Tests for the existence of an element that satisfies the given predicate.
     *
     * @param Closure $p the predicate
     *
     * @return bool TRUE if the predicate is TRUE for at least one element, FALSE otherwise
     */
    public function exists(Closure $p): bool
    {
        $this->initialize();

        foreach ($this->entities as $key => $element) {
            if ($p($key, $element)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Searches for a given element and, if found, returns the corresponding key/index
     * of that element. The comparison of two elements is strict, that means not
     * only the value but also the type must match.
     * For objects this means reference equality.
     *
     * @param mixed $element the element to search for
     *
     * @return mixed the key/index of the element or FALSE if the element was not found
     */
    public function indexOf($element): mixed
    {
        $this->initialize();

        return array_search($element, $this->entities, true);
    }

    /**
     * Gets the element with the given key/index.
     *
     * @param mixed $key the key
     *
     * @return mixed the element or NULL, if no element exists for the given key
     */
    public function get($key): mixed
    {
        $this->initialize();

        return $this->entities[$key] ?? null;
    }

    /**
     * Gets all keys/indexes of the collection elements.
     */
    public function getKeys(): array
    {
        $this->initialize();

        return array_keys($this->entities);
    }

    /**
     * Gets all elements.
     */
    public function getValues(): array
    {
        $this->initialize();

        return array_values($this->entities);
    }

    /**
     * Returns the number of elements in the collection.
     *
     * Implementation of the Countable interface.
     *
     * @return int the number of elements in the collection
     */
    public function count(): int
    {
        $this->initialize();

        return count($this->entities);
    }

    /**
     * Adds/sets an element in the collection at the index / with the specified key.
     *
     * When the collection is a Map this is like put(key,value)/add(key,value).
     * When the collection is a List this is like add(position,value).
     */
    public function set($key, $value)
    {
        throw new LogicException('set() is not supported.');
    }

    /**
     * Adds an element to the collection.
     *
     * @return bool always TRUE
     */
    public function add(mixed $value): bool
    {
        throw new LogicException('Adding new entities is not supported after creation.');
    }

    /**
     * Checks whether the collection is empty.
     *
     * Note: This is preferable over count() == 0.
     *
     * @return bool TRUE if the collection is empty, FALSE otherwise
     */
    public function isEmpty(): bool
    {
        $this->initialize();

        return [] === $this->entities;
    }

    /**
     * Gets an iterator for iterating over the elements in the collection.
     */
    public function getIterator(): Traversable
    {
        $this->initialize();

        return new ArrayIterator($this->entities);
    }

    /**
     * Applies the given function to each element in the collection and returns
     * a new collection with the elements returned by the function.
     */
    public function map(Closure $func): Collection
    {
        $this->initialize();

        return new ArrayCollection(array_map($func, $this->entities));
    }

    /**
     * Returns all the elements of this collection that satisfy the predicate p.
     * The order of the elements is preserved.
     *
     * @param Closure $p the predicate used for filtering
     *
     * @return Collection a collection with the results of the filter operation
     */
    public function filter(Closure $p): Collection
    {
        $this->initialize();

        return new ArrayCollection(array_filter($this->entities, $p));
    }

    /**
     * Applies the given predicate p to all elements of this collection,
     * returning true, if the predicate yields true for all elements.
     *
     * @param Closure $p the predicate
     *
     * @return bool TRUE, if the predicate yields TRUE for all elements, FALSE otherwise
     */
    public function forAll(Closure $p): bool
    {
        $this->initialize();

        foreach ($this->entities as $key => $element) {
            if (!$p($key, $element)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Partitions this collection in two collections according to a predicate.
     * Keys are preserved in the resulting collections.
     *
     * @param Closure $p the predicate on which to partition
     *
     * @return array An array with two elements. The first element contains the collection
     *               of elements where the predicate returned TRUE, the second element
     *               contains the collection of elements where the predicate returned FALSE.
     */
    public function partition(Closure $p): array
    {
        $this->initialize();

        $coll1 = $coll2 = [];
        foreach ($this->entities as $key => $element) {
            if ($p($key, $element)) {
                $coll1[$key] = $element;
            } else {
                $coll2[$key] = $element;
            }
        }

        return [new ArrayCollection($coll1), new ArrayCollection($coll2)];
    }

    /**
     * Returns a string representation of this object.
     */
    public function __toString(): string
    {
        return self::class.'@'.spl_object_hash($this);
    }

    /**
     * Clears the collection.
     */
    public function clear(): void
    {
        throw new LogicException('clear() is not supported.');
    }

    /**
     * Extract a slice of $length elements starting at position $offset from the Collection.
     *
     * If $length is null it returns all elements from $offset to the end of the Collection.
     * Keys have to be preserved by this method. Calling this method will only return the
     * selected slice and NOT change the elements contained in the collection slice is called on.
     *
     * @param int $offset
     * @param int $length
     */
    public function slice($offset, $length = null): array
    {
        $this->initialize();

        return array_slice($this->entities, $offset, $length, true);
    }

    /**
     * Select all elements from a selectable that match the criteria and
     * return a new collection containing these elements.
     */
    public function matching(Criteria $criteria): Collection
    {
        $this->initialize();

        $expr = $criteria->getWhereExpression();
        $filtered = $this->entities;

        if ($expr) {
            $visitor = new ClosureExpressionVisitor();
            $filter = $visitor->dispatch($expr);
            $filtered = array_filter($filtered, $filter);
        }

        $orderings = array_map(static fn (Order $order): string => $order->value, $criteria->orderings());
        if (0 !== count($orderings)) {
            $next = null;
            foreach (array_reverse($orderings) as $field => $ordering) {
                $next = ClosureExpressionVisitor::sortByField($field, 'DESC' == $ordering ? -1 : 1, $next);
            }

            usort($filtered, $next);
        }

        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();

        if ($offset || $length) {
            $filtered = array_slice($filtered, (int) $offset, $length);
        }

        return new ArrayCollection($filtered);
    }

    private function initialize(): void
    {
        if (null !== $this->entities) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        $entitiesPerClass = [];
        $count = 0;
        foreach ($connection->query('SELECT related_class, related_id FROM jms_job_related_entities WHERE job_id = '.$this->job->getId()) as $data) {
            ++$count;
            $entitiesPerClass[$data['related_class']][] = json_decode((string) $data['related_id'], true);
        }

        if (0 === $count) {
            $this->entities = [];

            return;
        }

        $entities = [];
        foreach ($entitiesPerClass as $className => $ids) {
            $qb = $this->entityManager->createQueryBuilder()
                        ->select('e')->from($className, 'e');

            $i = 0;
            foreach ($ids as $id) {
                $expr = null;
                foreach ($id as $k => $v) {
                    if (null === $expr) {
                        $expr = $qb->expr()->eq('e.'.$k, '?'.(++$i));
                    } else {
                        $expr = $qb->expr()->andX($expr, $qb->expr()->eq('e.'.$k, '?'.(++$i)));
                    }

                    $qb->setParameter($i, $v);
                }

                $qb->orWhere($expr);
            }

            $entities = array_merge($entities, $qb->getQuery()->getResult());
        }

        $this->entities = $entities;
    }
}
