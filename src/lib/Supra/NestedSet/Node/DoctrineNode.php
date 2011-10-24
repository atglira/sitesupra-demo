<?php

namespace Supra\NestedSet\Node;

use Supra\NestedSet\DoctrineRepository;
use Supra\NestedSet\RepositoryInterface;
use Supra\NestedSet\Exception;
use Doctrine\ORM\EntityManager;

/**
 * Doctrine database nested set node object
 */
class DoctrineNode extends NodeAbstraction
{
	/**
	 * @var RepositoryInterface
	 */
	private $sourceRepository;
	
	/**
	 * @var DoctrineRepository
	 */
	protected $repository;

	/**
	 * @param RepositoryInterface $repository
	 */
	public function __construct(RepositoryInterface $repository)
	{
		$this->sourceRepository = $repository;
	}
	
	/**
	 * Pass the doctrine entity the nested set node belongs to
	 * @param NodeInterface $entity
	 */
	public function belongsTo(NodeInterface $node)
	{
		parent::belongsTo($node);

		$rep = $this->sourceRepository;
		if ( ! ($rep instanceof RepositoryInterface)) {
			throw new Exception\WrongInstance($rep, 'RepositoryInterface');
		}
		$nestedSetRepository = $rep->getNestedSetRepository();
		/* @var $nestedSetRepository DoctrineRepository */
		
		$this->setRepository($nestedSetRepository);
		
		if ($this->right === null) {
			$nestedSetRepository->add($node);
		}
		$nestedSetRepository->register($node);
	}

	/**
	 * @param DoctrineRepository $repository
	 * @return DoctrineNode
	 */
	public function setRepository(DoctrineRepository $repository)
	{
		return parent::setRepository($repository);
	}

	/**
	 * @return int
	 * @nestedSetMethod
	 */
	public function getNumberChildren()
	{
		/*
		 * It's cheaper to call descendant number count.
		 * If the count is less than 2 they are all children
		 */
		$descendantNumber = $this->getNumberDescendants();
		if ($descendantNumber <= 1) {
			return $descendantNumber;
		}

		$rep = $this->repository;

		$search = $rep->createSearchCondition()
				->leftMoreThan($this->getLeftValue())
				->rightLessThan($this->getRightValue())
				->levelEqualsTo($this->getLevel() + 1);

		$em = $rep->getEntityManager();
		$className = $rep->getClassName();
		$qb = $em->createQueryBuilder();
		$qb->select('COUNT(e.id)')
				->from($className, 'e');

		$search->applyToQueryBuilder($qb);

		$count = $qb->getQuery()->getSingleScalarResult();
		return $count;
	}

	/**
	 * Prepare object to be processed by garbage collector by removing it's
	 * instance from the Doctrine Repository Array Helper object
	 * @param EntityNodeInterface $entity
	 */
	public function free(EntityNodeInterface $entity)
	{
		$this->repository->free($entity);
		$this->repository = null;
	}

}