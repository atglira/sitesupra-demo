<?php

namespace Supra\Controller\Layout\Processor;

use Supra\Template\Parser\Twig\Twig;
use Twig_Loader_Filesystem;
use Supra\ObjectRepository\ObjectRepository;
use Supra\Controller\Pages\Request\PageRequest;
use Supra\Controller\Pages\Twig\TwigSupraGlobal;
use Supra\Response\HttpResponse;
use Supra\Response\ResponseContext;
use Supra\Controller\Layout\Theme\ThemeInterface;

/**
 * Twig layout processor
 */
class TwigProcessor extends HtmlProcessor
{

	/**
	 *
	 * @var ThemeInterface
	 * 
	 */
	protected $theme;

	/**
	 * @param ThemeInterface $theme 
	 */
	public function setTheme(ThemeInterface $theme)
	{
		$this->theme = $theme;
	}

	/**
	 * @return ThemeInterface
	 */
	public function getTheme()
	{
		return $this->theme;
	}

	/**
	 * @param string $layoutSrc
	 * @return string
	 */
	protected function getContent($layoutSrc)
	{
		$theme = $this->getTheme();

		if ( ! empty($theme)) {
			$this->setLayoutDir($theme->getLayoutDir());
		}

		$twig = ObjectRepository::getTemplateParser($this);
		/* @var $twig Twig */

		if ( ! $twig instanceof Twig) {
			throw new \RuntimeException("Twig layout processor expects twig template parser");
		}

		$helper = new TwigSupraGlobal();
		ObjectRepository::setCallerParent($helper, $this);
		$helper->setRequest($this->request);

		if ($this->response instanceof HttpResponse) {
			$helper->setResponseContext($this->response->getContext());
		} else {
			$helper->setResponseContext(new ResponseContext());
		}

		if ( ! empty($theme)) {
			$helper->setTheme($theme);
		}

		$twig->addGlobal('supra', $helper);

		$loader = new Twig_Loader_Filesystem($this->layoutDir);
		$contents = $twig->parseTemplate($layoutSrc, array(), $loader);

		return $contents;
	}

}
