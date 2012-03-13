<?php

namespace Supra\Cms\CrudManager\Form;

use Supra\Cms\CrudManager\CrudManagerAbstractAction;
use Supra\Editable;
use Supra\ObjectRepository\ObjectRepository;
use Supra\Validator\Exception\RuntimeException;
use Supra\Cms\Exception\CmsException;
use Supra\AuditLog\TitleTrackingItemInterface;
use Supra\Cms\CrudManager\CrudEntityInterface;

class FormAction extends CrudManagerAbstractAction
{

	public function saveAction()
	{
		$this->isPostRequest();

		$configuration = $this->getConfiguration();
		$em = ObjectRepository::getEntityManager($this);
		$repo = $em->getRepository($configuration->entity);

		$post = $this->getRequestInput();

		$record = null;
		$recordId = $post->get('id', null);
			
		$newRecord = false;

		if ( ! empty($recordId)) {
			$record = $repo->findOneById($recordId);
		} else {
			$newRecord = true;

			if ($repo->isCreatable()) {
				$record = new $configuration->entity;
			} else {
				return null;
			}
		}

		if ( ! $record instanceof $configuration->entity) {
			throw new CmsException(null, 'Could not find any record with id #' . $recordId);
		}

		ObjectRepository::setCallerParent($record, $this);

		$em->persist($record);

		//setting new values
		$output = $record->setEditValues($post);

		$em->flush();

		$recordId = $record->getId();
		$recordBefore = $post->get('record-before', null);

		if ($repo->isSortable() && $newRecord) {
			$this->move($record, $recordBefore, false);
		}

		if ( ! $record instanceof TitleTrackingItemInterface) {
			$record = $recordId;
		}

		$this->writeAuditLog("Record %item% saved", $record);

		$response = $this->getResponse();
		$response->setResponseData($output);
		
		$this->dropGroupCache();
	}

	public function deleteAction()
	{
		$this->isPostRequest();

		$configuration = $this->getConfiguration();
		$em = ObjectRepository::getEntityManager($this);
		$repo = $em->getRepository($configuration->entity);

		if ( ! $repo->isDeletable()) {
			throw new CmsException(null, 'It\'s not allowed to remove records. Change configuration');
		}

		$post = $this->getRequestInput();
		$recordId = $post->get('id');
		if (empty($recordId)) {
			throw new CmsException(null, 'Record id is empty');
		}

		$record = $repo->findOneById($recordId);
		if ( ! $record instanceof $configuration->entity) {
			throw new CmsException(null, 'Can\'t find record to delete');
		}

		$em->remove($record);
		$em->flush();

		if ( ! $record instanceof TitleTrackingItemInterface) {
			$record = $recordId;
		}

		$this->writeAuditLog("Record %item% deleted", $record);
		
		$this->dropGroupCache();
	}

	/**
	 * Performs sorting action
	 */
	public function sortAction()
	{
		$this->isPostRequest();

		$configuration = $this->getConfiguration();
		$em = ObjectRepository::getEntityManager($this);
		$repo = $em->getRepository($configuration->entity);

		if ( ! $repo->isSortable()) {
			throw new CmsException(null, 'Manager is not sortable. Change configuration');
		}

		if ( ! property_exists($configuration->entity, 'position')) {
			throw new \Exception('Looks like there is no $position property in ' . $configuration->entity . ' entity.');
		}

		$post = $this->getRequestInput();

		$recordId = $post->get('id');
		$recordBefore = $post->get('record-before', null);
		if (empty($recordId)) {
			throw new CmsException(null, 'Empty record id');
		}

		$record = $repo->findOneById($recordId);
		if ( ! $record instanceof $configuration->entity) {
			throw new CmsException(null, 'Can\'t find record to delete');
		}

		$this->move($record, $recordBefore);
		
		$this->dropGroupCache();
	}

	/**
	 * Moving current record
	 * @param \Supra\Cms\CrudManager\CrudEntityInterface $record
	 * @param string $recordBefore
	 */
	protected function move(CrudEntityInterface $record, $recordBefore, $writeLog = true)
	{
		$configuration = $this->getConfiguration();
		$em = ObjectRepository::getEntityManager($this);
		$beforePosition = 0;

		$em->beginTransaction();

		$recordId = $record->getId();

		try {
			$queryResult = array();
			if ( ! empty($recordBefore)) {

				// check if nothing has been changed
				$query = $em->createQuery("SELECT e.id as position FROM {$configuration->entity} e WHERE e.id = :recordId");
				$query->setParameter('recordId', $recordId);
				$beforePosition = $query->getSingleScalarResult();

				if ($beforePosition == $recordBefore) {
					return;
				}

				$query = $em->createQuery("SELECT e.position as position FROM {$configuration->entity} e WHERE e.id = :before");
				$query->setParameter('before', $recordBefore);
				$beforePosition = $query->getSingleScalarResult();
			}

			// This is because of MySQL unique contraint failure if position is unique
			$query = $em->createQuery("UPDATE {$configuration->entity} e SET e.position = - e.position WHERE e.position > :beforePosition");
			$query->setParameter('beforePosition', $beforePosition);
			$queryResult = $query->execute();

			$query = $em->createQuery("UPDATE {$configuration->entity} e SET e.position = - e.position + 1 WHERE e.position < - :beforePosition");
			$query->setParameter('beforePosition', $beforePosition);
			$queryResult = $query->execute();

			$query = $em->createQuery("UPDATE {$configuration->entity} e SET e.position = :newPosition WHERE e.id = :currentRecord");
			$query->setParameter('newPosition', $beforePosition + 1);
			$query->setParameter('currentRecord', $recordId);
			$queryResult = $query->execute();

			$em->flush();
		} catch (\Exception $e) {
			$em->rollback();

			throw $e;
		}

		$em->commit();

		if ($writeLog) {
			$this->writeAuditLog("Record %item% moved", $record);
		}
	}

	/**
	 * Drops group cache. Entity name is cache group name for now.
	 */
	private function dropGroupCache()
	{
		$configuration = $this->getConfiguration();
		$group = $configuration->entity;
		
		$cacheGroupManager = new \Supra\Cache\CacheGroupManager();
		$cacheGroupManager->resetRevision($group);
	}
}
