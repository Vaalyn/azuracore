<?php
namespace Azura\Doctrine;

use Azura\Normalizer\DoctrineEntityNormalizer;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Symfony\Component\Serializer\Serializer;

class Repository extends EntityRepository
{
    /**
     * Combination of find() and toArray() helper functions.
     *
     * @param mixed $id
     * @param bool $deep
     * @param bool $form_mode
     * @return array|null
     */
    public function findAsArray($id, $deep = false, $form_mode = false)
    {
        $record = $this->_em->find($this->_entityName, $id);
        return ($record === null)
            ? null
            : $this->toArray($record, $deep, $form_mode);
    }

    /**
     * Generate an array result of all records.
     *
     * @param bool $cached
     * @param null $order_by
     * @param string $order_dir
     * @return array
     */
    public function fetchArray($cached = true, $order_by = null, $order_dir = 'ASC')
    {
        $qb = $this->_em->createQueryBuilder()
            ->select('e')
            ->from($this->_entityName, 'e');

        if ($order_by) {
            $qb->orderBy('e.' . str_replace('e.', '', $order_by), $order_dir);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * Generic dropdown builder function (can be overridden for specialized use cases).
     *
     * @param bool $add_blank
     * @param \Closure|NULL $display
     * @param string $pk
     * @param string $order_by
     * @return array
     */
    public function fetchSelect($add_blank = false, \Closure $display = null, $pk = 'id', $order_by = 'name')
    {
        $select = [];

        // Specify custom text in the $add_blank parameter to override.
        if ($add_blank !== false) {
            $select[''] = ($add_blank === true) ? 'Select...' : $add_blank;
        }

        // Build query for records.
        $qb = $this->_em->createQueryBuilder()->from($this->_entityName, 'e');

        if ($display === null) {
            $qb->select('e.' . $pk)->addSelect('e.name')->orderBy('e.' . $order_by, 'ASC');
        } else {
            $qb->select('e')->orderBy('e.' . $order_by, 'ASC');
        }

        $results = $qb->getQuery()->getArrayResult();

        // Assemble select values and, if necessary, call $display callback.
        foreach ((array)$results as $result) {
            $key = $result[$pk];
            $value = ($display === null) ? $result['name'] : $display($result);
            $select[$key] = $value;
        }

        return $select;
    }

    /**
     * FromArray (A Doctrine 1 Classic)
     *
     * @param object $entity
     * @param array $source
     */
    public function fromArray($entity, array $source)
    {
        return $this->_getSerializer()->denormalize($source, get_class($entity), null, [
            DoctrineEntityNormalizer::OBJECT_TO_POPULATE => $entity,
        ]);
    }

    /**
     * ToArray (A Doctrine 1 Classic)
     *
     * @param object $entity
     * @param bool $deep Iterate through collections associated with this item.
     * @param bool $form_mode Return values in a format suitable for ZendForm setDefault function.
     * @return array
     */
    public function toArray($entity, $deep = false, $form_mode = false)
    {
        return $this->_getSerializer()->normalize($entity, null, [
            DoctrineEntityNormalizer::NORMALIZE_TO_IDENTIFIERS  => $form_mode,
        ]);
    }

    /**
     * @return Serializer
     */
    protected function _getSerializer(): Serializer
    {
        return new Serializer([
            new DoctrineEntityNormalizer($this->_em),
        ]);
    }

    /**
     * Shortcut to persist an object and flush the entity manager.
     *
     * @param object $entity
     */
    public function save($entity)
    {
        $this->_em->persist($entity);
        $this->_em->flush();
    }
}