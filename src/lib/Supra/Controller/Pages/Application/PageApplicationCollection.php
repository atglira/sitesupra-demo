<?php

namespace Supra\Controller\Pages\Application;

use Supra\Controller\Pages\Configuration\PageApplicationConfiguration;

/**
 * Collection of page applications
 */
class PageApplicationCollection
{
	/**
	 * @var PageApplicationCollection
	 */
	private static $instance;
	
	/**
	 * @var array
	 */
	protected $applicationConfigurationList = array();
	
	/**
	 * @var array
	 */
	protected $loadedApplications = array();
	
	/**
	 * @return PageApplicationCollection 
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}
		
		return self::$instance;
	}
	
	/**
	 * @param PageApplicationConfiguration $configuration
	 */
	public function addConfiguration(PageApplicationConfiguration $configuration)
	{
		$id = $configuration->id;
		$this->applicationConfigurationList[$id] = $configuration;
	}
	
	/**
	 * @param string $id
	 * @return PageApplicationConfiguration 
	 */
	public function getConfiguration($id)
	{
		if (isset($this->applicationConfigurationList[$id])) {
			return $this->applicationConfigurationList[$id];
		}
	}
	
	/**
	 * @param string $id
	 * @return PageApplicationInterface
	 */
	public function createApplication($id)
	{
		if ( ! isset($this->loadedApplications[$id])) {
			$configuration = $this->getConfiguration($id);
			
			if ($configuration instanceof PageApplicationConfiguration) {
				$this->loadedApplications[$id] = new $configuration->className;
			}
		}
		
		return $this->loadedApplications[$id];
	}
}
