<?php

namespace Supra\Cms\ContentManager;

use Supra\Controller\Pages\Entity;
use Supra\Controller\Exception\ResourceNotFoundException;
use Supra\Controller\Pages\PageController;
use Supra\Controller\Pages\Request\PageRequest;
use Supra\Controller\Pages\Request\PageRequestEdit;
use Doctrine\ORM\EntityManager;
use Supra\ObjectRepository\ObjectRepository;
use Supra\Controller\Pages\Repository\PageRepository;
use Supra\Http\Cookie;
use Supra\Cms\CmsAction;
use Supra\NestedSet\Node\DoctrineNode;
use Doctrine\ORM\Query;
use Supra\Database\Doctrine\Hydrator\ColumnHydrator;
use Supra\Controller\Pages\Entity\ReferencedElement;
use Supra\FileStorage\Entity\Image;
use Supra\FileStorage\Entity\File;
use Supra\Cms\Exception\ObjectLockedException;
use Supra\User\Entity\User;
use Supra\Cms\Exception\CmsException;
use Supra\Uri\Path;
use Supra\Controller\Pages\Application\PageApplicationCollection;
use Supra\Controller\Pages\Request\HistoryPageRequestEdit;
use Supra\Controller\Pages\Event\CmsPagePublishEventArgs;
use Supra\Cms\CmsController;
use Supra\Loader\Loader;
use Supra\Controller\Pages\Listener\EntityAuditListener;
use Supra\Controller\Pages\Entity\Abstraction\Localization;
use Supra\Controller\Pages\Entity\PageRevisionData;
use Supra\Controller\Pages\Entity\Abstraction\AbstractPage;
use Supra\Controller\Pages\Entity\Page;
use Supra\Controller\Pages\Entity\Template;
use Supra\AuditLog\AuditLogEvent;
use Supra\Controller\Pages\Event\CmsPageDeleteEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Supra\Controller\Pages\Listener\PagePathGenerator;
use Supra\Controller\Pages\Event\PageEventArgs;
use Supra\Controller\Pages\Event\AuditEvents;

/**
 * Controller containing common methods
 */
abstract class PageManagerAction extends CmsAction
{
	const INITIAL_PAGE_ID_COOKIE = 'cms_content_manager_initial_page_id';

	const PAGE_CONTROLLER_CLASS = 'Supra\Controller\Pages\PageController';
	
	/**
	 * @var EntityManager
	 */
	protected $entityManager;

	/**
	 * @var Entity\Abstraction\Localization
	 */
	protected $pageData;

	/**
	 * @var PageController
	 */
	private $pageController;

	/**
	 * Assign entity manager
	 */
	public function __construct()
	{
		parent::__construct();

		// Will fetch connection for drafts
		$this->entityManager = ObjectRepository::getEntityManager($this);
	}
	
	/**
	 * TODO: must return configurable controller instance (use repository?)
	 * @return string
	 */
	private function getPageControllerClass()
	{
		return 'Supra\Controller\Pages\PageController';
	}
	
	/**
	 * Get public entity manager
	 * @return EntityManager
	 */
	protected function getPublicEntityManager()
	{
		return ObjectRepository::getEntityManager($this->getPageControllerClass());
	}

	/**
	 * Get page controller instance
	 * @return PageController
	 */
	protected function getPageController()
	{
		if (is_null($this->pageController)) {
			$controllerClass = $this->getPageControllerClass();
			$this->pageController = Loader::getClassInstance($controllerClass, 
					self::PAGE_CONTROLLER_CLASS);
			
			// Override to use the draft repository objects
			ObjectRepository::setCallerParent($this->pageController, $this);
		}

		return $this->pageController;
	}

	/**
	 * @return PageRequestEdit
	 */
	protected function getPageRequest()
	{
		$controller = $this->getPageController();
		$media = $this->getMedia();
		$user = $this->getUser();
		$requestPageLocalization = $this->getPageLocalization();

		$request = PageRequestEdit::factory($requestPageLocalization, $media);
		$response = $controller->createResponse($request);

		$controller->prepare($request, $response);

		$request->setUser($user);

		return $request;
	}

	/**
	 * TODO: hardcoded now
	 * @return string
	 */
	protected function getMedia()
	{
		return 'screen';
	}

	/**
	 *
	 * @return Entity\Abstraction\AbstractPage
	 */
	protected function getPage()
	{
		$page = null;

		if (isset($this->pageData)) {
			$page = $this->pageData->getMaster();
		}
		else {
			$page = $this->getPageByRequestKey('page_id');
		}

		return $page;
	}

	/**
	 * @return Entity\Abstraction\Localization
	 * @throws ResourceNotFoundException
	 */
	protected function getPageLocalization()
	{
		// FIXME: using requested locale would make more common sense with global/local switch
		if (isset($this->pageData)) {
			return $this->pageData;
		}

		$this->pageData = $this->getPageLocalizationByRequestKey('page_id');

		if (empty($this->pageData)) {
			$pageId = $this->getRequestParameter('page_id');
			throw new CmsException('sitemap.error.page_not_found', "Page data for page {$pageId} not found");
		}
		
		$localizationId = $this->pageData->getId();
			
		$pageEventArgs = new \Supra\Controller\Pages\Event\PageEventArgs();
		$pageEventArgs->setProperty('localizationId', $localizationId);
		
		$this->entityManager->getEventManager()
				->dispatchEvent(\Supra\Controller\Pages\Event\AuditEvents::pagePreEditEvent, $pageEventArgs);
		
		// Handle issue when page requested with wrong locale
		$pageLocaleId = $this->pageData->getLocale();
		$expectedLocaleId = $this->getLocale()->getId();
		
		/*
		 * Set the system current locale if differs from 'locale' parameter received.
		 * This is done for BACK button to work after navigating to page with different language.
		 * NB! this change won't be saved in the currrent locale browser cookie storage.
		 */
		if ($expectedLocaleId != $pageLocaleId) {
			ObjectRepository::getLocaleManager($this)
				->setCurrent($pageLocaleId);
		}

		return $this->pageData;
	}

	/**
	 * Try selecting abstract page by request parameter
	 * @param string $key
	 * @return Entity\Abstraction\AbstractPage
	 */
	private function searchPageByRequestKey($key)
	{
		$pageId = $this->getRequestParameter($key);
		$page = $this->entityManager->find(
				Entity\Abstraction\AbstractPage::CN(), $pageId);

		return $page;
	}

	/**
	 * Try selecting page localization by request parameter
	 * @param string $key
	 * @return Entity\Abstraction\Localization
	 */
	private function searchLocalizationByRequestKey($key)
	{
		$pageId = $this->getRequestParameter($key);
		$localization = $this->entityManager->find(
				Entity\Abstraction\Localization::CN(), $pageId);

		return $localization;
	}

	/**
	 * Try loading page by searching for received page/localization ID
	 * @param string $key
	 * @return Entity\Abstraction\AbstractPage
	 */
	protected function getPageByRequestKey($key)
	{
		$page = $this->searchPageByRequestKey($key);

		if (is_null($page)) {
			$localization = $this->searchLocalizationByRequestKey($key);

			if ( ! is_null($localization)) {
				$page = $localization->getMaster();
			}
		}

		return $page;
	}

	/**
	 * Try loading localization by searching for received page/localization ID
	 * @param string $key
	 * @return Entity\Abstraction\Localization
	 */
	protected function getPageLocalizationByRequestKey($key)
	{
		$localization = $this->searchLocalizationByRequestKey($key);

		if (is_null($localization)) {
			$page = $this->searchPageByRequestKey($key);

			if ( ! is_null($page)) {
				$locale = $this->getLocale();
				$localization = $page->getLocalization($locale);
			}
		}

		return $localization;
	}

	/**
	 * Get first page localization ID to show in the CMS
	 * @return Entity\Abstraction\Localization
	 */
	protected function getInitialPageLocalization()
	{
		$localeId = $this->getLocale()->getId();
		$localization = null;

		// Try cookie
		if (isset($_COOKIE[self::INITIAL_PAGE_ID_COOKIE])) {
			$pageLocalizationId = $_COOKIE[self::INITIAL_PAGE_ID_COOKIE];
			$localization = $this->entityManager->find(Entity\Abstraction\Localization::CN(), $pageLocalizationId);
		}

		// Root page otherwise
		if (empty($localization)) {
			$page = null;
			$pageDao = $this->entityManager->getRepository(Page::CN());
			/* @var $pageDao PageRepository */
			$pages = $pageDao->getRootNodes();

			if (isset($pages[0])) {
				$page = $pages[0];
			}

			if ($page instanceof Entity\Abstraction\AbstractPage) {
				$localization = $page->getLocalization($localeId);
			}
		}

		if (empty($localization)) {
			return null;
		}

		return $localization;
	}

	/**
	 * Sets initial page ID to show in the CMS
	 * @param string $pageId
	 */
	protected function setInitialPageId($pageId)
	{
		$cookie = new Cookie(self::INITIAL_PAGE_ID_COOKIE, $pageId);
		$cookie->setExpire('+1 month');
		$_COOKIE[self::INITIAL_PAGE_ID_COOKIE] = $pageId;

		$this->getResponse()->setCookie($cookie);
	}

	/**
	 * 
	 * @param Entity\Abstraction\Localization $pageData
	 */
	protected function outputPage(Entity\Abstraction\Localization $pageData)
	{
		$data = null;

		$data = $this->loadNodeMainData($pageData);

		// Add missing parent page data ID
		$parentData = $pageData->getParent();
		$parentDataId = null;

		if ( ! is_null($parentData)) {
			$parentDataId = $parentData->getId();
		}

		$data['parent'] = $parentDataId;

		$this->getResponse()->setResponseData($data);
	}

	/**
	 * Loads main node data array
	 * @param Entity\Abstraction\Localization $data
	 * @param boolean $localizationExists will pass page ID not localization ID if not exists
	 * @return array
	 */
	protected function loadNodeMainData(Entity\Abstraction\Localization $data, $localizationExists = true)
	{
		$page = $data->getMaster();
		$locale = $data->getLocale();

		// Main data
		$array = array(
			'id' => $localizationExists ? $data->getId() : $page->getId(),
			'master_id' => $page->getId(),
			'title' => $data->getTitle(),
			// TODO: hardcoded
			'icon' => $page instanceof Entity\TemporaryGroupPage ? 'folder' :
					($data instanceof Entity\GroupLocalization ? 'group' :
							($page->getLevel() === 0 ? 'home' : 'page')),
			'preview' => '/cms/lib/supra/img/sitemap/preview/' . ($data instanceof Entity\GroupLocalization ? 'group' : 'blank') . '.jpg',
		);
		
		// Template ID
		if ($data instanceof Entity\PageLocalization) {
			$template = $data->getTemplate();
			$templateId = null;
			
			if ( ! empty($template)) {
				$templateId = $template->getId();
			}
			
			$array['template'] = $templateId;
			
			$scheduleTime = $data->getScheduleTime();
			if ( ! is_null($scheduleTime)) {
				$array['scheduled'] = true;
			}
			
			$array['global_disabled'] = false;
			
			$localizations = $page->getLocalizations()->count();
			
			if($localizations > 1) {
				$array['global_disabled'] = true;
			}
			
			$array['full_path'] = $data->getPath()
					->getFullPath(Path::FORMAT_BOTH_DELIMITERS);
		}

		// Node type
		$type = Entity\Abstraction\Entity::PAGE_DISCR;
		if ($data instanceof Entity\GroupLocalization) {
			$type = Entity\Abstraction\Entity::GROUP_DISCR;
		}
		elseif ($page instanceof Entity\ApplicationPage) {
			$type = Entity\Abstraction\Entity::APPLICATION_DISCR;
			$array['application_id'] = $page->getApplicationId();
			$conf = PageApplicationCollection::getInstance()->getConfiguration($page->getApplicationId());
			$array['new_children_first'] = $conf->newChildrenFirst;
			$array['isDragable'] = $conf->isDragable;
			$array['isDropTarget'] = $conf->isDropTarget;
		}
		$array['type'] = $type;

		// Path data
		$pathPart = null;
		$applicationBasePath = new Path('');

		if ($data instanceof Entity\PageLocalization) {
			$pathPart = $data->getPathPart();

			if ( ! $page->isRoot()) {
				$parentPage = $page->getParent();
				$parentLocalization = $parentPage->getLocalization($locale);

				if ( ! is_null($parentLocalization) && $parentPage instanceof Entity\ApplicationPage) {
					$applicationId = $parentPage->getApplicationId();
					$application = PageApplicationCollection::getInstance()
							->createApplication($parentLocalization, $this->entityManager);

					$application->showInactivePages(true);

					if (empty($application)) {
						throw new CmsException(null, "Application '$applicationId' was not found");
					}

					$applicationBasePath = $application->generatePath($data);
				}
			}
		}
		
		$array['unpublished_draft'] = true;
		$array['published'] = false;
		
		$localizationId = $data->getId();
		$publicEm = ObjectRepository::getEntityManager('#public');
		$publicLocalization = $publicEm->find(Localization::CN(), $localizationId);
		if ($publicLocalization instanceof Localization) {
			$array['unpublished_draft'] = false;
			
			$publicRevision = $publicLocalization->getRevisionId();
			$draftRevision = $data->getRevisionId();
			if ($draftRevision == $publicRevision) {
				$array['published'] = true;
			}
		}
		
		// TODO: maybe should send "null" when path is not allowed? Must fix JS then
		$array['path'] = $pathPart;
		// Additional base path received from application
		$array['basePath'] = $applicationBasePath->getFullPath(Path::FORMAT_RIGHT_DELIMITER);
		
		return $array;
	}

	/**
	 * Will publish page currently inside pageData property or found by page_id
	 * and locale query parameters
	 */
	protected function publish()
	{
		$publicEm = $this->getPublicEntityManager();

		$pageRequest = $this->getPageRequest();

		$copyContent = function() use ($pageRequest) {
					$pageRequest->publish();
				};

		$publicEm->transactional($copyContent);

		// If all went well, fire the post-publish event for published page localization.
		$eventArgs = new CmsPagePublishEventArgs();
		$eventArgs->user = $this->getUser();
		$eventArgs->localization = $this->getPageLocalization();

		$eventManager = ObjectRepository::getEventManager($this);
		$eventManager->fire(CmsController::EVENT_POST_PAGE_PUBLISH, $eventArgs);
	}

	/**
	 * Converts referenced element to JS array
	 * @param ReferencedElement\ReferencedElementAbstract $element
	 * @return array
	 */
	protected function convertReferencedElementToArray(ReferencedElement\ReferencedElementAbstract $element)
	{
		$data = $element->toArray();
		$localeId = $this->getLocale()->getId();
		$fs = ObjectRepository::getFileStorage($this);
		$em = $fs->getDoctrineEntityManager();

		if ($element instanceof ReferencedElement\LinkReferencedElement) {

			if ($element->getResource() == 'file') {

				$fileId = $element->getFileId();
				$file = $em->find(File::CN(), $fileId);

				if ($file instanceof File) {
					$fileInfo = $fs->getFileInfo($file, $localeId);
					$data['file_path'] = $fileInfo['path'];
				}
			}
		}
		elseif ($element instanceof ReferencedElement\ImageReferencedElement) {

			$imageId = $element->getImageId();
			$image = $em->find(Image::CN(), $imageId);

			if ($image instanceof Image) {
				$info = $fs->getFileInfo($image, $localeId);
				$data['image'] = $info;
			}
		}

		return $data;
	}

	/**
	 * Move page at trash 
	 */
	protected function delete()
	{
		$page = $this->getPageLocalization()
				->getMaster();

		$pageId = $page->getId();

		if ($page instanceof Entity\Template) {
			$localizationEntity = Entity\PageLocalization::CN();
			$dql = "SELECT COUNT(p.id) FROM $localizationEntity p
	                WHERE p.template = ?0";
			$count = $this->entityManager->createQuery($dql)
					->setParameters(array($pageId))
					->getSingleScalarResult();

			if ((int) $count > 0) {
				throw new CmsException(null, 'Cannot remove template as there are pages using it.');
			}
		}

		$pageRequest = $this->getPageRequest();
		$pageRequest->delete();
		
		$eventManager = ObjectRepository::getEventManager($this);
		
		$eventArgs = new CmsPageDeleteEventArgs();
		$eventArgs->localization = $this->getPageLocalization();
		$eventArgs->user = $this->getUser();
		$eventManager->fire(CmsController::EVENT_POST_PAGE_DELETE, $eventArgs);

		$this->getResponse()
				->setResponseData(true);
	}

	protected function restorePageVersion()
	{
		$this->isPostRequest();
	
		$auditEm = ObjectRepository::getEntityManager(PageController::SCHEMA_AUDIT);
		
		$localizationId = $this->getRequestParameter('page_id');
		
		// TODO: we could simply pass revision ID to serverside and skip this step
		$qb = $auditEm->createQueryBuilder();
		$qb->select('ap.id')
				->from(Localization::CN(), 'l')
				->join('l.master', 'ap')
				->where('l.id = :id')
				->setMaxResults(1);
		$qb->setParameter('id', $localizationId);
		$query = $qb->getQuery();
		$result = $query->getResult(ColumnHydrator::HYDRATOR_ID);
		
		// throw an exception if we failed to get master for this page
		if (empty($result)) {
			throw new CmsException(null, 'Page not found in recycle bin');
		}
		
		$pageId = array_pop($result);
		// get revision by type and removed page id
		$pageRevisionData = $auditEm->getRepository(PageRevisionData::CN())
				->findOneBy(array('type' => PageRevisionData::TYPE_TRASH, 'reference' => $pageId));

		if ( ! ($pageRevisionData instanceof PageRevisionData)) {
			throw new CmsException(null, 'Page revision data not found');
		}
		
		$revisionId = $pageRevisionData->getId();
		
		$page = $auditEm->getRepository(AbstractPage::CN())
				->findOneBy(array('id' => $pageId, 'revision' => $revisionId));
		
		if ($page instanceof Entity\Page) {

			$localizations = $page->getLocalizations();
			foreach ($localizations as $pageLocalization) {

				$template = $pageLocalization->getTemplate();
				
				if ( ! ($template instanceof Entity\Template)) {
					$localeName = $this->getLocale()
							->getId();
					throw new CmsException(null, "It is impossible to restore page as its \"{$localeName}\" version template was deleted");
				}
			}
		}
		else if ($page instanceof Entity\Template) {

			$parentId = $this->getRequestParameter('parent_id');
			$referenceId = $this->getRequestParameter('reference_id');

			if ( ! $page->hasParent()
					&& ( ! empty($parentId) || ! empty($referenceId))) {

				throw new CmsException(null, "It is impossible to restore root template as a child");
			}
		}
		
		$localeId = $this->getLocale()->getId();
		$media = $this->getMedia();

		$request = new HistoryPageRequestEdit($localeId, $media);
		$request->setDoctrineEntityManager($auditEm);
		$request->setPageLocalization($pageLocalization);

		$request->setRevision($revisionId);

		$restorePage = function() use ($request) {
				$page = $request->restorePage();
			};

		$this->entityManager
				->transactional($restorePage);

		$page = $this->entityManager
				->find(Entity\Abstraction\AbstractPage::CN(), $page->getId());

		$parent = $this->getPageByRequestKey('parent_id');
		$reference = $this->getPageByRequestKey('reference_id');
		try {
			if (is_null($reference)) {
				if (is_null($parent)) {
					throw new CmsException('sitemap.error.parent_page_not_found');
				}
				$parent->addChild($page);
			}
			else {
				$page->moveAsPrevSiblingOf($reference);
			}
		}
		catch (DuplicatePagePathException $uniqueException) {
			throw new CmsException('sitemap.error.duplicate_path');
		}

		$this->getResponse()
				->setResponseData(true);

	}

	/**
	 * Restores history version of the page
	 */
	protected function restoreLocalizationVersion()
	{
		$revisionId = $this->getRequestParameter('version_id');
	
		$localization = $this->getPageLocalization();
		$localizationId = $localization->getId();

		$entityManager = ObjectRepository::getEntityManager(PageController::SCHEMA_AUDIT);
		
//		$pageLocalization = $auditEm->find(Entity\Abstraction\Localization::CN(),
//				array('id' => $localizationId, 'revision' => $revisionId));
//		
//		if ( ! ($pageLocalization instanceof Entity\Abstraction\Localization)) {
//			throw new CmsException(null, 'Page version not found');
//		}
		
		// find last published page revision
		$params = array(
			'id' => $revisionId,
			'localizationId' => $localizationId,
			'types' => array(
				PageRevisionData::TYPE_HISTORY,
				PageRevisionData::TYPE_CREATE,
				PageRevisionData::TYPE_DUPLICATE,
			),
		);
		
		$qb = $entityManager->createQueryBuilder();
		$qb->select('r')
				->from(PageRevisionData::CN(), 'r')
				->where('r.id <= :id AND r.type IN (:types) AND r.reference = :localizationId')
				->orderBy('r.creationTime', 'DESC')
				->setMaxResults(1)
				->setParameters($params)
				;
		try {	
			$lastPublishRevision = $qb->getQuery()
					->getSingleResult();
		} catch (\Doctrine\ORM\NoResultException $e) {
			throw new CmsException(null, 'Cannot find last published page revision');
		}
		
		$baseRevisionId = $lastPublishRevision->getId();
		
		// select all revisions from last published, till selected one
		$qb = $entityManager->createQueryBuilder();
		
		$params = array(
			'id' => $revisionId,
			'baseId' => $baseRevisionId,
			'localizationId' => $localizationId, 
		);
		
		$qb->select('r')
				->from(PageRevisionData::CN(), 'r')
				->where('r.id <= :id AND r.id > :baseId AND r.reference = :localizationId')
				->orderBy('r.id', 'DESC')
				->setParameters($params)
				;
		
		$revisionList = $qb->getQuery()
				->getResult();
		
		if ( ! empty($revisionList)) {
			//throw new CmsException(null, "Nothing found for revision #{$revisionId}");
		
		
			// loop through list of revisions, if there is localization present, we should use it as base localization
			// as there are located last localization settings (template, schedule etc.)
			$lastLocalizationRevision = null;
			foreach($revisionList as $revision) {
				/* @var $revision PageRevisionData */
				$className = $revision->getElementName();

				if ($className == Entity\PageLocalization::CN() || $className == Entity\TemplateLocalization::CN()) {
					$lastLocalizationRevision = $revision;

					break;
				}
			}
		}
		
		$localizationRevision = (isset($lastLocalizationRevision) ? $lastLocalizationRevision : $lastPublishRevision);

		$localization = $entityManager->getRepository(Entity\Abstraction\Localization::CN())
				->findOneBy(array('id' => $localizationRevision->getReferenceId(), 'revision' => $localizationRevision->getId()));
		
		if ( ! ($localization instanceof Entity\Abstraction\Localization)) {
			throw new CmsException(null, 'Page version not found');
		}
		
		$localeId = $this->getLocale()->getId();
		$media = $this->getMedia();

		$request = new HistoryPageRequestEdit($localeId, $media);
		$request->setDoctrineEntityManager($entityManager);
		$request->setPageLocalization($localization);
		$request->setRevisionArray($revisionList);

		$revisionId = $localization->getRevisionId();
		$request->setRevision($baseRevisionId);

		$restoreLocalization = function() use ($request) {
					$request->restoreLocalization();
				};

		$this->entityManager
				->transactional($restoreLocalization);
		
	}

	/**
	 * Checks, weither the page is locked by current user or not,
	 * will throw an exception if no, and update lock modified time if yes
	 * @throws ObjectLockedException if page is locked by another user
	 */
	protected function checkLock($createLockOnMiss = true)
	{
		$this->isPostRequest();

		$userId = $this->getUser()->getId();
		$pageData = $this->getPageLocalization();

		$pageLock = $pageData->getLock();

		if ($pageLock instanceof Entity\LockData) {
			if (($pageLock->getUserId() != $userId)) {
				throw new ObjectLockedException('page.error.page_locked', 'Page is locked by another user');
			}
			else {
				$pageLock->setModificationTime(new \DateTime('now'));
				$this->entityManager->flush();
			}
		}
		elseif ($createLockOnMiss) {
			// Creates lock if doesn't exist
			$this->createLock($pageData, $userId);
		}
	}

	/**
	 * Removes page lock if exists
	 */
	protected function unlockPage()
	{
		$this->isPostRequest();

		$pageData = $this->getPageLocalization();

		$pageLock = $pageData->getLock();

		if ($pageLock instanceof Entity\LockData) {
			$this->entityManager->remove($pageLock);
			$pageData->setLock(null);

			$this->entityManager->flush();

			$previousRevision = $pageLock->getPageRevision();
			$currentRevision = $pageData->getRevisionId();

			if ($previousRevision != $currentRevision) {
				$this->writeAuditLog('unlock', "Draft for %item% saved", $pageData);
			}
		}
	}

	/**
	 * Sets page lock, if no lock is found, or if "force"-locking is used;
	 * will output current lock data if page locked by another user and 
	 * force action is not allowed or not provided
	 */
	protected function lockPage()
	{
		$this->isPostRequest();

		$userId = $this->getUser()->getId();
		$pageData = $this->getPageLocalization();

		$allowForced = true; // TODO: hardcoded, should be based on current User rights/auth
		$force = (bool) $this->getRequestParameter('force');

		try {
			$this->checkLock(false);
		}
		catch (ObjectLockedException $e) {
			if ( ! $force || ! $allowForced) {

				$pageLock = $pageData->getLock();
				$lockedBy = $pageLock->getUserId();

				$userProvider = ObjectRepository::getUserProvider($this);
				$lockOwner = $userProvider->findUserById($lockedBy);

				// If not found will show use ID
				$userName = '#' . $lockedBy;

				if ($lockOwner instanceof User) {
					$userName = $lockOwner->getName();
				}

				$response = array(
						'username' => $userName,
						'datetime' => $pageLock->getCreationTime()->format('c'),
						'allow_unlock' => $allowForced,
				);

				$this->getResponse()
						->setResponseData($response);
				return;
			}
		}

		$this->createLock($pageData, $userId);

		$this->getResponse()->setResponseData(true);
	}

	/**
	 * Creates the lock inside the database
	 * @param Entity\Abstraction\Localization $pageData
	 * @param string $userId
	 */
	protected function createLock(Entity\Abstraction\Localization $pageData, $userId)
	{
		$pageLock = new Entity\LockData();
		$this->entityManager->persist($pageLock);

		$pageLock->setUserId($userId);
		$revisionId = $pageData->getRevisionId();
		$pageLock->setPageRevision($revisionId);
		$pageData->setLock($pageLock);
		$this->entityManager->flush();
	}
	
	/**
	 * 
	 */
	protected function duplicate()
	{
		$pageLocalization = $this->getPageLocalizationByRequestKey('page_id');
		$request = $this->getPageRequest();
		$em = $this->entityManager;

		$page = $pageLocalization->getMaster();

		$clonePage = function() use ($request, $em, $page) {
			/* @var $request PageRequestEdit */
			/* @var $em EntityManager */
			/* @var $page AbstractPage */
			/* @var $pageLocalization Localization */

			$em->getEventManager()
				->dispatchEvent(AuditEvents::pagePreDuplicateEvent);
					
			$newPage = $request->recursiveClone($page, null, true);
			
			// page indexes in sitemap tree
			$newPage->setLeftValue(0);
			$newPage->setRightValue(0);
			$newPage->setLevel(1);
			
			$em->getRepository(AbstractPage::CN())
				->getNestedSetRepository()
				->add($newPage);			
			
			if ($page->hasParent()) {
				$newPage->moveAsNextSiblingOf($page);
			} else {
				$newPage->moveAsFirstChildOf($page);
			}

			$eventArgs = new LifecycleEventArgs($newPage, $em);
			$em->getEventManager()
					->dispatchEvent(PagePathGenerator::postPageClone, $eventArgs);
			
			$eventArgs = new PageEventArgs();
			$eventArgs->setEntityManager($em);
			
			$localizations = $newPage->getLocalizations();
			
			foreach($localizations as $newLocalization) {
				$eventArgs->setProperty('localizationId', $newLocalization->getId());

				$em->getEventManager()
					->dispatchEvent(AuditEvents::pagePostDuplicateEvent, $eventArgs);
			}
			
			
		};
		
		$em->transactional($clonePage);
		
		$this->getResponse()
				->setResponseData(true);
	}
	
	public function duplicateGlobal()
	{
		$localeId = $this->getRequestParameter('locale');
		$localeManager = ObjectRepository::getLocaleManager($this);
		$localeManager->exists($localeId);
		
		$master = $this->getPageByRequestKey('page_id');
		
		$sourceLocaleId = $this->getRequestParameter('source_locale');
		$localeManager->exists($sourceLocaleId);
		
		$existingLocalization = $master->getLocalization($sourceLocaleId);
		
		// Overwrite current localization with the source data
		$this->pageData = $existingLocalization;
		
		$this->checkActionPermission($master, Entity\Abstraction\Entity::PERMISSION_NAME_EDIT_PAGE);
		
		if (is_null($existingLocalization)) {
			throw new CmsException(null, "No source page [{$sourceLocaleId}] was found");
		}
		
		if ($localeId == $sourceLocaleId) {
			throw new CmsException(null, 'Page duplicate will do nothing as old locale and new locale are identical');
		}
		
		$em = $this->entityManager;
		$request = $this->getPageRequest();
		
		$em->getEventManager()
				->dispatchEvent(AuditEvents::pagePreDuplicateEvent);
		
		$cloneLocalization = function() use ($request, $em, $existingLocalization, $localeId) {
			
			// 1. duplicate localization
			$localization = $request->recursiveClone($existingLocalization, null, true, $localeId);
			// 2. set new locale for localization itself
			$localization->setLocale($localeId);
			
			if ($localization instanceof Entity\PageLocalization) {
				// 3. set new locale for path entity also
				$path = $localization->getPathEntity();
				$path->setLocale($localeId);
				// 4. flush, to store path locale
				$em->flush();

				// for now we have new(already duplicated) localization with correct locale id
				// with empty PageLocalizationPath(new entity was created, but 'path' column contains no data)
				// but new Localization::$pathPart contains same string as old one

				// 5. pass new localization (with new path entity) to PagePathGenerator
				// which will try to build new path for this locale using Localization::$pathPart string
				$eventArgs = new LifecycleEventArgs($localization, $em);
					$em->getEventManager()
						->dispatchEvent(PagePathGenerator::postPageClone, $eventArgs);
			}

			return $localization;
		};
		
		$newLocalization = $em->transactional($cloneLocalization);
		
		$eventArgs = new PageEventArgs();
		$eventArgs->setEntityManager($em);
		$eventArgs->setProperty('localizationId', $newLocalization->getId());
		
		$em->getEventManager()
			->dispatchEvent(AuditEvents::pagePostDuplicateEvent, $eventArgs);
		
		if ($newLocalization instanceof Entity\TemplateLocalization) {
			$this->pageData = $newLocalization;
			$this->publish();
		}
		
		$this->writeAuditLog('create', "%item%[{$localeId}] created from [{$sourceLocaleId}] locale", $newLocalization);
				
		$this->getResponse()
				->setResponseData(array('id' => $newLocalization->getId()));
		
	}

	/**
	 * Write to audit log
	 *
	 * @param string $action
	 * @param mixed $data
	 * @param object $item
	 * @param int $level 
	 */
	protected function writeAuditLog($action, $message, $item = null, $level = AuditLogEvent::INFO) 
	{
		if ($item instanceof AbstractPage) {
			$localeId = $this->getLocale()->getId();
			$item = $item->getLocalization($localeId);
		}
		
		parent::writeAuditLog($action, $message, $item, $level);
	}
	
}
