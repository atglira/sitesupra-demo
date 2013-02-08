<?php

namespace Supra\Controller\Layout\Processor;

use Supra\Response\ResponseInterface;
use Supra\Controller\Layout\Exception;
use Supra\Request\RequestInterface;
use Supra\Controller\Pages\Response\PlaceHolderGroup\PlaceHolderGroupResponse;

/**
 * Simple layout processor
 */
class HtmlProcessor implements ProcessorInterface
{
	/**
	 * Place holder function name
	 */
	const PLACE_HOLDER = 'placeHolder';
	
	/**
	 * Place holder container function name
	 */
	const PLACE_HOLDER_GROUP = 'placeHolderGroup';

	/**
	 * Maximum layout file size
	 */
	const FILE_SIZE_LIMIT = 1000000;

	/**
	 * Allowed macro functions
	 * @var array
	 */
	static protected $macroFunctions = array(
		self::PLACE_HOLDER,
		self::PLACE_HOLDER_GROUP,
	);

	/**Pa
	 * Layout root dir
	 * @var string
	 */
	protected $layoutDir;
	
	/**
	 * @var string
	 */
	protected $startDelimiter = '<!--';

	/**
	 * @var string
	 */
	protected $endDelimiter = '-->';

	/**
	 * @var RequestInterface
	 */
	protected $request;

	/**
	 * @var ResponseInterface
	 */
	protected $response;

	/**
	 * @param RequestInterface $request
	 */
	public function setRequest(RequestInterface $request)
	{
		$this->request = $request;
	}

	/**
	 * @param ResponseInterface $response 
	 */
	public function setResponse(ResponseInterface $response)
	{
		$this->response = $response;
	}

	/**
	 * Process the layout
	 * @param ResponseInterface $response
	 * @param array $placeResponses
	 * @param string $layoutSrc
	 */
	public function process(ResponseInterface $response, array $placeResponses, $layoutSrc)
	{

		// Output CDATA
		$cdataCallback = function($cdata) use ($response) {
					$response->output($cdata);
		};

		$self = $this;
		
		// Flush place holder responses into master response
		$macroCallback = function($func, array $args, $self) use (&$response, &$placeResponses, $self) {
					if ($func == HtmlProcessor::PLACE_HOLDER) {
						if ( ! array_key_exists(0, $args) || $args[0] == '') {
							throw new Exception\RuntimeException("No placeholder name defined in the placeHolder macro in template ");
						}

						$place = $args[0];

						if (isset($placeResponses[$place])) {
							/* @var $placeResponse ResponseInterface */
							$placeResponse = $placeResponses[$place];
							$placeResponse->flushToResponse($response);
						}
					}

					if ($func == HtmlProcessor::PLACE_HOLDER_GROUP) {
						
						$name = null;
						if (($pos = mb_strpos($args[0], '|')) !== false) {
							$name = mb_substr($args[0], 0, $pos);
						} else {
							$name = $args[0];
						}	
						
						foreach($placeResponses as $placeResponse) {
							if ($placeResponse instanceof PlaceHolderGroupResponse) {
								if ($placeResponse->getGroupName() == $name) {
									$currentGroupResponses = $placeResponse->getPlaceHolderResponses();
									$groupResponse = $placeResponse;
									break;
								}
							}
						}
						
						if ( ! empty($currentGroupResponses)) {
							$layout = $groupResponse->getGroupLayout();
//							if ( ! is_null($layout)) {
							$self->process($groupResponse, $currentGroupResponses, $layout->getFileName());
							$groupResponse->flushToResponse($response);
//							}
							
//							$groupLayouts = $self->layout->getTheme()->getPlaceholderGroupLayouts();
//							
//							if ( ! $groupLayouts->isEmpty()) {
//								if ($groupLayouts->offsetExists($layoutName)) {
//									$groupLayout = $groupLayouts->get($layoutName);
//									/* @var $group Supra\Controller\Pages\Entity\Theme\ThemeLayoutPlaceholderGroup */
//									
//								} else {
//									\Log::warn("No configuration found for {$layoutName}, output for this placeholders group is skipped");
//								}
//							} else {
//								\Log::warn('Layout group array is empty');
//							}
						}
					}
				};

		$this->walk($layoutSrc, $cdataCallback, $macroCallback);
	}

	/**
	 * Return list of place names inside the layout
	 * @param string $layoutSrc
	 * @return array
	 */
	public function getPlaces($layoutSrc)
	{
		$places = array();

		// Ignore CDATA
		$cdataCallback = function($cdata) {};

		// Collect place holders
		$macroCallback = function($func, array $args) use (&$places, $layoutSrc) {
					if ($func == HtmlProcessor::PLACE_HOLDER) {
						if ( ! array_key_exists(0, $args) || $args[0] == '') {
							throw new Exception\RuntimeException("No placeholder name defined in the placeHolder macro in file {$layoutSrc}");
						}

						// Normalize placeholder ID for case insensitive MySQL varchar field
						$places[] = mb_strtolower($args[0]);
					}
				};

		$this->walk($layoutSrc, $cdataCallback, $macroCallback);

		return $places;
	}
	
	public function getPlaceGroups($layoutSrc)
	{
		$groups = array();
		
		// Ignore CDATA
		$cdataCallback = function($cdata) {};

		// Collect place holders
		$macroCallback = function($func, array $args) use (&$groups, $layoutSrc) {
					if ($func == HtmlProcessor::PLACE_HOLDER_GROUP) {
						if ( ! array_key_exists(0, $args) || $args[0] == '') {
							throw new Exception\RuntimeException("No placeholder group name defined in the placeHolderGroup macro in file {$layoutSrc}");
						}
						$groups[] = $args[0];
					}
				};

		$this->walk($layoutSrc, $cdataCallback, $macroCallback);
		
		return $groups;
	}

	/**
	 * Generates absolute filename
	 * @param string $layoutSrc
	 * @return string
	 * @throws Exception\RuntimeException when file or security issue is raised
	 */
	protected function getFileName($layoutSrc)
	{
		$filename = $this->getLayoutDir() . \DIRECTORY_SEPARATOR . $layoutSrc;
		if ( ! is_file($filename)) {
			throw new Exception\LayoutNotFoundException("File '$layoutSrc' was not found");
		}
		if ( ! is_readable($filename)) {
			throw new Exception\RuntimeException("File '$layoutSrc' is not readable");
		}

		// security stuff
		$this->securityCheck($filename);

		return $filename;
	}

	/**
	 * @param string $layoutSrc
	 * @return string
	 * @throws Exception\RuntimeException when file or security issue is raised
	 */
	protected function getContent($layoutSrc)
	{
		$filename = $this->getFileName($layoutSrc);

		return file_get_contents($filename);
	}

	/**
	 * @param string $filename
	 * @throws Exception\RuntimeException if security issue is found
	 */
	protected function securityCheck($filename)
	{
		if (preg_match('!(^|/|\\\\)\.\.($|/|\\\\)!', $filename)) {
			throw new Exception\RuntimeException("Security error for '$filename': Layout filename cannot contain '..' part");
		}
		if (\filesize($filename) > self::FILE_SIZE_LIMIT) {
			throw new Exception\RuntimeException("Security error for '$filename': Layout file size cannot exceed " . self::FILE_SIZE_LIMIT . ' bytes');
		}
	}

	protected function macroExists($name)
	{
		$exists = in_array($name, static::$macroFunctions);
		return $exists;
	}

	protected function walk($layoutSrc, \Closure $cdataCallback, \Closure $macroCallback)
	{
		$layoutContent = $this->getContent($layoutSrc);

		$startDelimiter = $this->getStartDelimiter();
		$startLength = strlen($startDelimiter);
		$endDelimiter = $this->getEndDelimiter();
		$endLength = strlen($endDelimiter);
		$pos = null;

		do {
			$pos = strpos($layoutContent, $startDelimiter);
			if ($pos !== false) {
				$cdataCallback(substr($layoutContent, 0, $pos));
				$layoutContent = substr($layoutContent, $pos);
				$pos = strpos($layoutContent, $endDelimiter);
				if ($pos === false) {
					break;
				}

				$macroString = substr($layoutContent, $startLength, $pos - $startLength);
				$macro = trim($macroString);
				if ( ! preg_match('!^(.*)\((.*)\)$!', $macro, $macroInfo)) {
					$cdataCallback(substr($layoutContent, 0, $startLength));
					$layoutContent = substr($layoutContent, $startLength);
					continue;
				}

				$macroFunction = trim($macroInfo[1]);
				$macroArguments = explode(',', $macroInfo[2]);
				$macroArguments = array_map('trim', $macroArguments);

				if ( ! $this->macroExists($macroFunction)) {
					$cdataCallback(substr($layoutContent, 0, $startLength));
					$layoutContent = substr($layoutContent, $startLength);
					continue;
				}

				$macroCallback($macroFunction, $macroArguments, null);

				// remove the used data
				$layoutContent = substr($layoutContent, $pos + $endLength);
			}
		} while ($pos !== false);

		$cdataCallback($layoutContent);
	}

	/**
	 * Set layout root dir
	 * @param string $layoutDir
	 */
	public function setLayoutDir($layoutDir)
	{
		$this->layoutDir = $layoutDir;
	}

	/**
	 * Get layout root dir
	 * @return string
	 */
	public function getLayoutDir()
	{
		return $this->layoutDir;
	}

	/**
	 * @return string
	 */
	public function getStartDelimiter()
	{
		return $this->startDelimiter;
	}

	/**
	 * @param string $startDelimiter
	 */
	public function setStartDelimiter($startDelimiter)
	{
		$this->startDelimiter = $startDelimiter;
	}

	/**
	 * @return string
	 */
	public function getEndDelimiter()
	{
		return $this->endDelimiter;
	}

	/**
	 * @param string $endDelimiter
	 */
	public function setEndDelimiter($endDelimiter)
	{
		$this->endDelimiter = $endDelimiter;
	}

}
