<?php

namespace Supra\Package\Cms\Pages\Block\Config;

use Supra\Package\Cms\Entity\BlockProperty;

class PropertyListConfig extends AbstractPropertyConfig implements PropertyCollectionConfig
{
	/**
	 * @var AbstractPropertyConfig
	 */
	protected $item;

	/**
	 * @var string
	 */
	protected $label;

	/**
	 * @param string $label
	 */
	public function setLabel($label)
	{
		$this->label = $label;
	}

	/**
	 * @return string
	 */
	public function getLabel()
	{
		return $this->label;
	}

	/**
	 * @param AbstractPropertyConfig $item
	 */
	public function setListItem(AbstractPropertyConfig $item)
	{
		$item->setParent($this);
		$this->item = $item;
	}

	/**
	 * @return AbstractPropertyConfig
	 */
	public function getListItem()
	{
		return $this->item;
	}

	/**
	 * {@inheritDoc}
	 */
	public function isMatchingProperty(BlockProperty $property)
	{
		return $property->getHierarchicalName() == $this->name;
	}

	/**
	 * {@inheritDoc}
	 */
	public function createProperty($name)
	{
		return new BlockProperty($name);
	}

	/**
	 * {@inheritDoc}
	 * @throws \LogicException
	 */
	public function getEditable()
	{
		throw new \LogicException('Collections have no editables.');
	}
}