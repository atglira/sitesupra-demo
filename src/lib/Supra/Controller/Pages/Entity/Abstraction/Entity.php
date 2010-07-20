<?php

namespace Supra\Controller\Pages\Entity\Abstraction;

use Doctrine\ORM\EntityManager,
		Supra\Database\Doctrine,
		Doctrine\Common\Collections\Collection,
		Supra\Controller\Pages\Exception;

/**
 * Base entity class for Pages controller
 */
abstract class Entity
{
	/**
	 * Connection name
	 * @var string
	 */
	static private $connnection;

	/**
	 * Locks to pervent infinite loop calls
	 * @var array
	 */
	private $locks = array();

	/**
	 * Set connection name used by Pages controller
	 * @param string $connectionName
	 */
	public static function setConnectionName($connectionName = null)
	{
		self::$connnection = $connectionName;
	}

	/**
	 * Get configured doctrine entity manager
	 * @return EntityManager
	 */
	public static function getConnection()
	{
		return Doctrine::getInstance()->getEntityManager(self::$connnection);
	}

	/**
	 * Lock to prevent infinite loops
	 * @param string $name
	 * @return boolean
	 */
	protected function lock($name)
	{
		if ( ! \array_key_exists($name, $this->locks)) {
			$this->locks[$name] = true;
			return true;
		}
		return false;
	}
	/**
	 * Unlock locked parameter
	 * @param string $name
	 * @return boolean
	 */
	protected function unlock($name)
	{
		if ( ! \array_key_exists($name, $this->locks)) {
			return false;
		}
		unset($this->locks[$name]);
		return true;
	}

	/**
	 * Set the property value. Return true on success, false on equal parameter,
	 * exception when argument not valid or
	 * @param mixed $property
	 * @param mixed $value
	 * @return bool
	 * @throws Exception when trying to rewrite the property
	 *	or invalid argument is passed
	 */
	protected function writeOnce(&$property, $value)
	{
		$sourceEntity = get_class($this);
		if (empty($value)) {
			throw new Exception("Second argument sent to method 
					$sourceEntity::writeOnce() cannot be empty");
		}
		if ( ! is_object($value)) {
			throw new Exception("Second argument sent to method 
					$sourceEntity::writeOnce() must be an object");
		}
		if ($property == $value) {
			return false;
		}
		if ( ! empty($property)) {
			$targetEntity = get_class($value);
			throw new Exception("The property $targetEntity is write-once,
					cannot rewrite with different value for $sourceEntity");
		}
		$property = $value;
		return true;
	}

	/**
	 * Adds an element to collection preserving uniqueness of fields
	 * @param Collection $collection
	 * @param Entity $newItem
	 * @param mixed $uniqueFields
	 * @return boolean true if added, false if already the same instance has been added
	 * @throws Exception if element with the same unique field values exists
	 */
	protected function addUnique(Collection $collection, $newItem, $uniqueFields = null)
	{
		if (is_null($uniqueFields)) {
			$uniqueFields = array();
		}
		
		// One field can be passed as string
		if ( ! is_array($uniqueFields)) {
			$uniqueFields = array($uniqueFields);
		}

		if ($collection->count() > 0) {

			/* @var $field string */
			foreach ($uniqueFields as $field) {
				$uniqueFieldValues[$field] = $newItem->getProperty($field);
			}

			/* @var $item Entity */
			foreach ($collection as $item) {

				// The instance already added
				if ($item == $newItem) {
					return false;
				}

				if (empty($uniqueFields)) {
					continue;
				}

				/* @var $field string */
				foreach ($uniqueFields as $field) {
					$value = $item->getProperty($field);
					if ($value != $uniqueFieldValues[$field]) {
						continue 2;
					}
				}
				
				// If we are here it means all unique parameters were equal
				throw new Exception("Cannot add element to collection,
					the element with the same values for unique fields already exists");
			}
		}

		$collection->add($newItem);
		return true;
	}

	/**
	 * Get property of an object by name
	 * @param string $name
	 * @return mixed
	 * @throws Exception if property getter method is not found
	 */
	public function getProperty($name)
	{
		$method = 'get' . \ucfirst($name);
		if ( ! \method_exists($this, $method)) {
			$class = \get_class($this);
			throw new Exception("Could not found getter function for object
					$class property $name");
		}
		$value = $this->$method();
		return $value;
	}

	/**
	 * Asserts that the object is instance of class
	 * @param Entity $instance
	 * @param string $class
	 * @param string $method
	 * @throws Exception if the instance check fails
	 */
	protected function isInstanceOf(Entity $instance, $class, $method)
	{
		if ( ! ($instance instanceof $class)) {
			throw new Exception("Object can accept instance of $class in method $method");
		}
	}
}