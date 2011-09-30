<?php

namespace Supra\Cms\InternalUserManager\Permissionproperties;

use Supra\Cms\InternalUserManager\InternalUserManagerAbstractAction;
use Supra\ObjectRepository\ObjectRepository;
use Supra\Controller\Pages\Request\PageRequest;
use Supra\Controller\Pages\Entity as PageEntity;
use Supra\FileStorage\Entity as FileEntity;
use Supra\Cms\CmsApplicationConfiguration;
use Supra\Authorization\AccessPolicy\AuthorizationThreewayAccessPolicy;

class PermissionpropertiesAction extends InternalUserManagerAbstractAction
{
	public function datalistAction() {
		
		switch($this->getRequestParameter('application_id')) {
			
			case 'media-library': {
				
				$response = $this->getMediaLibraryTreeData();
				
			} break;
			
			case 'content': {
				
				$response = $this->getContentTreeData();
				
			} break;
		}
		
		$this->getResponse()->setResponseData($response);
	}
	
	private function getContentTreeData() {
		
		$pages = array();
		$localeId = $this->getLocale()->getId();

		$em = ObjectRepository::getEntityManager($this);

		$response = array();

		$pageRepository = $em->getRepository(PageRequest::PAGE_ENTITY);

		/* @var $pageRepository \Supra\Controller\Pages\Repository\PageRepository */
		$rootNodes = $pageRepository->getRootNodes();

		foreach ($rootNodes as $rootNode) {
			$tree = $this->buildContentTreeArray($rootNode, $localeId);
			// TODO: hardcoded
			$tree['icon'] = 'home';

			$response[] = $tree;
		}
		
		return $response;
		
	}
	
	private function buildContentTreeArray($page, $locale)
	{
		$data = $page->getLocalization($locale)->getMaster();

		if (empty($data)) {
			return null;
		}

		$pathPart = null;
		$templateId = null;

		if ($data instanceof PageEntity\PageLocalization) {
			$pathPart = $data->getPathPart();
		}

		$array = array(
			'id' => $data->getId(),
			'title' => $page->getLocalization($locale)->getTitle(),
			'path' => $pathPart,
			// TODO: hardcoded
			'icon' => 'page',
			'preview' => '/cms/lib/supra/img/sitemap/preview/page-1.jpg'
		);

		$array['children'] = array();

		foreach ($page->getChildren() as $child) {
			
			$childArray = $this->buildContentTreeArray($child, $locale);

			if ( ! empty($childArray)) {
				$array['children'][] = $childArray;
			}
		}

		if (count($array['children']) == 0) {
			unset($array['children']);
		} else {
			// TODO: hardcoded
			$array['icon'] = 'folder';
		}

		return $array;
	}

	private function getMediaLibraryTreeData() 
	{
		$ml = ObjectRepository::getFileStorage($this);
		
		$rootNodes = array();
		
		// FIXME: store the classname as constant somewhere?
		/* @var $repo FileRepository */
		$repo = $this->entityManager->getRepository('Supra\FileStorage\Entity\Abstraction\File');

		$output = array();

		$rootNodes = $repo->getRootNodes();

		foreach ($rootNodes as $rootNode) {
			$response[] = $this->buildMediaLibraryTreeArray($rootNode);
		}

		return $response;
	}
	
	private function buildMediaLibraryTreeArray(FileEntity\Abstraction\File $file) 
	{ 
		if( ! ($file instanceof FileEntity\Folder)) {
			return array();
		}
		
		$array = array(
			'id' => $file->getId(),
			'title' => $file->getFileName(),
			'template' => null,
			'path' => $file->getPath(),
			'icon' => 'folder'
		);

		$array['children'] = array();

		foreach ($file->getChildren() as $child) {
			
			$childArray = $this->buildMediaLibraryTreeArray($child);

			if ( ! empty($childArray)) {
				$array['children'][] = $childArray;
			}
		}

		if (count($array['children']) == 0) {
			unset($array['children']);
		} 
		
		return $array;
	}
	
	public function saveAction() 
	{
		$cmsAppConfigs = CmsApplicationConfiguration::getInstance();
		$appConfig = $cmsAppConfigs->getConfiguration($this->getRequest()->getPostValue('application_id'));
		
		$up = ObjectRepository::getUserProvider($this);
		$user = $up->findUserById($this->getRequest()->getPostValue('user_id'));
		
		if($this->getRequest()->getPostValue('list')) {
			
			$itemUpdate = $this->getRequest()->getPostValue('list');
			
			$itemId = $itemUpdate['id'];
			
			if($appConfig->authorizationAccessPolicy instanceof AuthorizationThreewayAccessPolicy) {
				$appConfig->authorizationAccessPolicy->setItemPermissions($user, $itemId, $itemUpdate['value']);
			}
		}
		else if($this->getRequest()->getPostValue('property') == 'allow') {
		
			$appConfig->authorizationAccessPolicy->setAccessPermission(
					$user, 
					$this->getRequest()->getPostValue('value')
			);
		}
	}
}
