<?php

namespace Supra\Controller;

/**
 * HTTP abstract controller
 */
abstract class HttpAbstraction extends ControllerAbstraction
{
	/**
	 * Generate response object
	 * @param Request\RequestInterface $request
	 * @return Response\ResponseInterface
	 */
	public function createResponse(Request\RequestInterface $request)
	{
		return new Response\Http();
	}
}