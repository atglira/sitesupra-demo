<?php

namespace Supra\Package\Cms\Pages\Block\Mapper;

class AttributeMapper extends Mapper
{
	public function title($title)
	{
		$this->configuration->setTitle($title);
		return $this;
	}

	public function description($description)
	{
		$this->configuration->setDescription($description);
		return $this;
	}

	public function icon($icon)
	{
		$this->configuration->setIcon($icon);
		return $this;
	}

	public function tooltip($tooltip)
	{
		$this->configuration->setTooltip($tooltip);
		return $this;
	}

	public function group($group)
	{
		$this->configuration->setGroupName($group);
		return $this;
	}

	public function insertable($insertable = true)
	{
		$this->configuration->setInsertable($insertable);
		return $this;
	}

	/**
	 * Alias for insertable()
	 * 
	 * @param bool $hidden
	 */
	public function hidden($hidden = true)
	{
		return $this->insertable(! $hidden);
	}

	/**
	 * @param string $templateName
	 * @return \Supra\Package\Cms\Pages\Block\Mapper\BlockMapper
	 */
	public function template($templateName)
	{
		$this->configuration->setTemplateName($templateName);
		return $this;
	}

	/**
	 * @param string $controllerClass
	 * @return \Supra\Package\Cms\Pages\Block\Mapper\AttributeMapper
	 */
	public function controller($controllerClass)
	{
		$this->configuration->setControllerClass($controllerClass);
		return $this;
	}

	/**
	 * @TODO: refactor this.
	 *
	 * @internal
	 * @deprecated
	 *
	 * @param string $cmsClassName
	 * @return \Supra\Package\Cms\Pages\Block\Mapper\BlockMapper
	 */
	public function cmsClassName($cmsClassName)
	{
		$this->configuration->setCmsClassName($cmsClassName);
		return $this;
	}
}
