<?php

namespace Supra\Search;

use \Solarium_Client;
use \Solarium_Exception;
use \Solarium_Document_ReadWrite;
use Supra\Search\Entity\Abstraction\IndexerQueueItem;

class IndexerService
{

	/**
	 * @var \Solarium_Client;
	 */
	private $solariumClient;

	/**
	 * System ID to be used for this project.
	 * @var string
	 */
	private $systemId;

	function __construct()
	{
		$this->systemId = 'someSystemId';

		$config = array(
				'adapteroptions' => array(
						'host' => '127.0.0.1',
						'port' => 8080,
						'path' => '/solrdev',
				)
		);

		$this->solariumClient = new Solarium_Client($config);

		//$pingQuery = $this->solariumClient->createPing();
		//$this->solariumClient->ping($pingQuery);
	}

	public function getSystemId()
	{
		return $this->systemId;
	}

	/**
	 * Adds $queueItem to Solr.
	 * @param IndexerQueueItem $queueItem 
	 */
	public function processItem(IndexerQueueItem $queueItem)
	{
		try {

			$documents = $queueItem->getIndexedDocuments();

			$updateQuery = $this->solariumClient->createUpdate();

			foreach ($documents as $document) {
				/* @var $document IndexedDocument */

				$document->systemId = $this->getSystemId();
				$document->uniqueId = $document->systemId . '-' . $document->uniqueId;
				
				$document->validate();

				$updateQuery->addDocument($document);
			}

			$updateQuery->addCommit();

			$result = $this->solariumClient->update($updateQuery);

			if ($result->getStatus() !== 0) {
				throw new Exception\RuntimeException('Got bad status in update result: ' . $result->getStatus());
			}

			$queueItem->setStatus(IndexerQueueItemStatus::INDEXED);
		}
		catch (Exception\RuntimeException $e) {
			$queueItem->setStatus(IndexerQueueItemStatus::FAILED);
		}
	}

	/**
	 * Takes all FRESH items from $queue and adds them to Solr.
	 * @param IndexerQueue $queue 
	 */
	public function processQueue(IndexerQueue $queue)
	{
		$indexedQueueItems = array();
		
		while ($queue->getItemCountForStatus(IndexerQueueItemStatus::FRESH) !== 0) {

			$queueItem = $queue->getNextItemForIndexing();

			$this->processItem($queueItem);
			$indexedQueueItems[] = $queueItem;

			$queue->store($queueItem);
		}
		
		foreach($indexedQueueItems as $indexedQueueItem) {
			
			$indexedQueueItem->setStatus(IndexerQueueItemStatus::FRESH);
			$queue->store($indexedQueueItem);
		}
	}

	public function getSolariumClient()
	{
		return $this->solariumClient;
	}

	/**
	 * Returns count of documents indexed for this system
	 * @return integer
	 */
	public function getDocumentCount()
	{
		$query = $this->solariumClient->createSelect();
		$query->setQuery('systemId:' . $this->getSystemId());
		$query->setRows(0);

		$result = $this->solariumClient->select($query);

		return $result->getNumFound();
	}

}
