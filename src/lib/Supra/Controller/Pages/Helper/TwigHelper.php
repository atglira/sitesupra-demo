<?php

namespace Supra\Controller\Pages\Helper;

use Supra\Controller\Pages\BlockController;
use Supra\Response\TwigResponse;
use Twig_Markup;

/**
 * Supra page controller twig helper
 */
class TwigHelper
{
	/**
	 * @var BlockController
	 */
	protected $blockController;
	
	/**
	 * @param BlockController $blockController
	 */
	public function __construct(BlockController $blockController)
	{
		$this->blockController = $blockController;
	}
	
	/**
	 * Outputs block property
	 * @param string $name
	 * @return string
	 */
	public function property($name, $default = null)
	{
		$value = $this->blockController->getPropertyValue($name, $default);
		
		// Marks content safe
		$valueObject = new Twig_Markup($value);

		return $valueObject;
	}
}
