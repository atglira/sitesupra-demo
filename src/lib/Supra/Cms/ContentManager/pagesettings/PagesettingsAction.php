<?php

namespace Supra\Cms\ContentManager\Pagesettings;

use Supra\Cms\ContentManager\PageManagerAction;
use Supra\Controller\Pages\Entity;
use Supra\Controller\Pages\Request\PageRequest;
use DateTime;
use Supra\Cms\Exception\CmsException;
use Supra\Validator\Type\AbstractType;
use Supra\Controller\Pages\Task\LayoutProcessorTask;
use Supra\Controller\Layout\Exception as LayoutException;
use Supra\Authorization\Exception\EntityAccessDeniedException;
use Supra\ObjectRepository\ObjectRepository;
use Supra\Controller\Pages\Search\PageLocalizationIndexerQueue;
use Supra\Controller\Pages\PageController;
use Supra\Search\IndexerService;
use Supra\Search\SearchService;
use Supra\Controller\Pages\Search\PageLocalizationKeywordsRequest;
use Supra\Controller\Pages\Search\PageLocalizationSearchResultItem;
use Supra\Controller\Pages\Search\PageLocalizationFindRequest;
use Supra\Controller\Pages\Exception;

/**
 * Page settings actions
 */
class PagesettingsAction extends PageManagerAction
{

	/**
	 * Saves page properties
	 */
	public function saveAction()
	{
		$this->isPostRequest();
		$input = $this->getRequestInput();
		$this->checkLock();
		$page = $this->getPage();
		$localeId = $this->getLocale()->getId();
		$pageData = $page->getLocalization($localeId);

		if (empty($pageData)) {
			$this->checkActionPermission($page, Entity\Abstraction\Entity::PERMISSION_NAME_EDIT_PAGE);
			$pageData = Entity\Abstraction\Localization::factory($page, $localeId);
		} else {
			$this->checkActionPermission($pageData, Entity\Abstraction\Entity::PERMISSION_NAME_EDIT_PAGE);
		}

		if ($input->has('global')) {
			$global = $input->getValid('global', AbstractType::BOOLEAN);

			if ($page->isRoot() && ! $global) {
				throw new Exception\LogicException('It is not allowed to disable translation of root page');
			}

			$page->setGlobal($global);
		}

		//TODO: create some simple objects for save post data with future validation implementation?
		if ($input->has('title')) {
			$title = $input->get('title');
			$pageData->setTitle($title);
		}

		if ($input->has('is_visible_in_menu')) {
			$visibleInMenu = $input->getValid('is_visible_in_menu', AbstractType::BOOLEAN);
			$pageData->setVisibleInMenu($visibleInMenu);
		}

		if ($input->has('is_limited') && $pageData instanceof Entity\PageLocalization) {
			$limitedAccess = $input->getValid('is_limited', AbstractType::BOOLEAN);
			$pageData->setLimitedAccessPage($limitedAccess);
		}

		if ($input->has('is_visible_in_sitemap')) {
			$visibleInSitemap = $input->getValid('is_visible_in_sitemap', AbstractType::BOOLEAN);
			$pageData->setVisibleInSitemap($visibleInSitemap);
		}

		if ($input->has('include_in_search')) {
			$includedInSearch = $input->getValid('include_in_search', AbstractType::BOOLEAN);
			$pageData->setIncludedInSearch($includedInSearch);
		}

		$themeProvider = ObjectRepository::getThemeProvider($this);
		$theme = $themeProvider->getCurrentTheme();

		if ($pageData instanceof Entity\TemplateLocalization) {
			if ($input->has('layout')) {

				$media = $this->getMedia();
				$template = $pageData->getMaster();
				/* @var $template Entity\Template */
				$templateLayout = null;

				// use parent layout
				if ($input->isEmpty('layout')) {
					if ($template->isRoot()) {
						throw new CmsException(null, "Can not use parent layout because current page is root page");
					}

					$parentTemplate = $template->getParent();
					/* @var $parentTemplate Entity\Template */

					$parentTemplateLayout = $parentTemplate->getTemplateLayouts()->get($media);

					if ( ! $parentTemplateLayout instanceof Entity\TemplateLayout) {
						throw new Exception\RuntimeException('Parent layout should be instance of ' . Entity\TemplateLayout::CN() . ' class');
					}

					$parentThemeLayoutName = $parentTemplateLayout->getLayoutName();

					$parentThemeLayout = $theme->getLayout($parentThemeLayoutName);

					// Remove current layout if any
					$templateLayout = $template->getTemplateLayouts()
							->get($media);

					if ( ! empty($templateLayout)) {
						$this->entityManager->remove($templateLayout);
					}

					$templateLayout = $template->addLayout($media, $parentThemeLayout);
				}
				// Search for provided layout
				else {

					$themeLayoutName = $input->get('layout');

					$themeLayout = $theme->getLayout($themeLayoutName);

					if (empty($themeLayout)) {
						throw new Exception\RuntimeException('Can\'t find layout with name ' . $themeLayoutName);
					}

					// Remove current layout if any
					$templateLayout = $template->getTemplateLayouts()
							->get($media);

					if ( ! empty($templateLayout)) {
						$this->entityManager->remove($templateLayout);
					}

					$templateLayout = $template->addLayout($media, $themeLayout);
				}

				// Persist the new template layout object (cascade)
				$this->entityManager->persist($templateLayout);
			}
		}

		if ($pageData instanceof Entity\PageLocalization) {

			if ($input->has('path')) {
				//TODO: validation 
				$pathPart = $input->get('path');
				$pageData->setPathPart($pathPart);
			}

			if ($input->has('template')) {
				//TODO: validation
				$templateId = $input->get('template');

				/* @var $template Entity\Template */
				$template = $this->entityManager->find(Entity\Template::CN(), $templateId);
				
				$currentTemplate = $pageData->getTemplate();
				
				if ( ! $template->equals($currentTemplate)) {
				
					$pageData->setTemplate($template);

					$request = $this->getPageRequest();
					
					$request->createMissingPlaceHolders(true);
				}
			}

			if ($input->has('active')) {
				$active = $input->getValid('active', AbstractType::BOOLEAN);
				$pageData->setActive($active);
			}

			if ($input->has('description')) {
				$metaDescription = $input->get('description');
				$pageData->setMetaDescription($metaDescription);
			}

			if ($input->has('keywords')) {
				$metaKeywords = $input->get('keywords');
				$pageData->setMetaKeywords($metaKeywords);
			}

			if ($input->has('scheduled_date')) {

				try {
					$this->checkActionPermission($pageData, Entity\Abstraction\Entity::PERMISSION_NAME_SUPERVISE_PAGE);

					//TODO: validation
					$date = $input->get('scheduled_date');
					$time = $input->get('scheduled_time', '00:00');

					if (empty($date)) {
						$pageData->unsetScheduleTime();
					} else {
						if (empty($time)) {
							$time = '00:00';
						}

						$dateTime = "{$date}T{$time}";

						$scheduleTime = new DateTime($dateTime);

						if ($scheduleTime instanceof DateTime) {
							$pageData->setScheduleTime($scheduleTime);
						} else {
							throw new CmsException(null, "Schedule time provided in unrecognized format");
						}
					}
				} catch (EntityAccessDeniedException $e) {

					$this->getResponse()
							->addWarningMessage('Scheduled publish date is not saved. You must have Supervise permission to use scheduling functionality.');
				}
			}

			if ($input->has('created_date')) {

				$date = $input->get('created_date');
				$time = $input->get('created_time', '00:00');

				// Set manually only if both elements are received
				if ( ! empty($date) && ! empty($time)) {
					$dateTime = "{$date}T{$time}";

					$creationTime = new DateTime($dateTime);

					if ($creationTime instanceof DateTime) {
						$pageData->setCreationTime($creationTime);
					} else {
						throw new CmsException(null, "Creation time provided in unrecognized format");
					}
				}
			}

			//TODO: validation
			$redirect = null;

			// Setting redirect
			if ($input->hasChild('redirect')) {
				$redirect = $input->getChild('redirect')
						->getArrayCopy();
				// Removing redirect
			} elseif ($input->has('redirect')) {
				$redirect = false;
			}

			if ( ! is_null($redirect)) {

				// Delete current link object
				$currentRedirect = $pageData->getRedirect();

				if ( ! empty($currentRedirect)) {
					$this->entityManager->remove($currentRedirect);
				}

				// Set new link, JS should send empty value if link must be removed
				if (empty($redirect)) {
					$pageData->setRedirect(null);
				} else {
					$link = new Entity\ReferencedElement\LinkReferencedElement();
					$link->fillArray($redirect);
					$this->entityManager->persist($link);

					$pageData->setRedirect($link);
				}
			}

			if ($input->has('page_change_frequency')) {
				$changeFrequency = $input->get('page_change_frequency');
				$pageData->setChangeFrequency($changeFrequency);
			}

			if ($input->has('page_priority')) {
				$pagePriority = $input->get('page_priority');
				$pageData->setPagePriority($pagePriority);
			}
		}

		try {
			$this->entityManager->flush();
		} catch (\Supra\Controller\Pages\Exception\DuplicatePagePathException $e) {
			throw new CmsException(null, $e->getMessage());
		}

		$this->savePostTrigger();

		$this->writeAuditLog('Settings of %item% saved', $pageData);
	}

	/**
	 * List of templates
	 */
	public function templatesAction()
	{
		$localeId = $this->getLocale()->getId();
		$templateArray = array();
		$templateTitles = array();

		$templateDataDao = $this->entityManager->getRepository(Entity\TemplateLocalization::CN());
		$templateDataList = $templateDataDao->findByLocale($localeId);

		$iniLoader = ObjectRepository::getIniConfigurationLoader($this);

		$doNotUseAsDefaultTemplateIds = explode(';', $iniLoader->getValue('system', 'dont_use_as_default_template_ids', ''));

//		\Log::error('$doNotUseAsDefaultTemplateIds: ', $doNotUseAsDefaultTemplateIds);

		/* @var $templateData Entity\TemplateLocalization */
		foreach ($templateDataList as $templateData) {
			
			$previewPath = $templateData->getPreviewFilename();

			if (file_exists($previewPath)) {
				$previewUrl = $templateData->getPreviewUrl();
			} else {
				$previewUrl = '/cms/lib/supra/img/sitemap/preview/blank.jpg';
			}

			if (file_exists($previewPath)) {
				$previewUrl = $templateData->getPreviewUrl();
			} else {
				$previewUrl = '/cms/lib/supra/img/sitemap/preview/blank.jpg';
			}
			
			$templateArray[] = array(
				'id' => $templateData->getMaster()->getId(),
				'title' => $templateData->getTitle(),
				'icon' => $previewUrl,
				'dont_use_as_default' => in_array($templateData->getMaster()->getId(), $doNotUseAsDefaultTemplateIds)
			);

			$templateTitles[] = $templateData->getTitle();
		}

		array_multisort($templateTitles, $templateArray);

		$this->getResponse()->setResponseData($templateArray);
	}

	public function suggestionsAction()
	{
		$suggestions = array();

		$request = $this->getRequest();

		$pageLocalizationId = $request->getParameter('page_id');

		$em = ObjectRepository::getEntityManager(PageController::SCHEMA_DRAFT);

		$pageLocalizationRepository = $em->getRepository(Entity\PageLocalization::CN());
		$pageLocalization = $pageLocalizationRepository->find($pageLocalizationId);
		/* @var $pageLocalization Entity\PageLocalization */

		$revisionId = $pageLocalization->getRevisionId();

		$searchService = new SearchService();
		$indexerService = new IndexerService();

		$findRequest = new PageLocalizationFindRequest();
		$findRequest->setPageLocalizationId($pageLocalizationId);
		$findRequest->setRevisionId($pageLocalization->getRevisionId());
		$findRequest->setSchemaName(PageController::SCHEMA_DRAFT);

		$findResults = $searchService->processRequest($findRequest);

		$doCleanup = false;

		if ($findResults->getTotalResultCount() == 0) {

			$doCleanup = true;

			$indexerQueueItem = new Entity\PageLocalizationIndexerQueueItem($pageLocalization);
			$indexerQueueItem->setIgnoreChildren(true);
			$indexerService->processItem($indexerQueueItem);
		}

		$getKeywordsRequest = new PageLocalizationKeywordsRequest();
		$getKeywordsRequest->setPageLocalizationId($pageLocalizationId);
		$getKeywordsRequest->setRevisionId($revisionId);
		$getKeywordsRequest->setLocaleId($pageLocalization->getLocale());
		$getKeywordsRequest->setSchemaName(PageController::SCHEMA_DRAFT);

		$result = $searchService->processRequest($getKeywordsRequest);

		$resultItems = $result->getItems();
		$keywords = array();

		foreach ($resultItems as $resultItem) {

			/* @var $resultItem PageLocalizationSearchResultItem */
			if ($resultItem->getPageLocalizationId() == $pageLocalizationId) {

				$allKeywords = $resultItem->getKeywordsFromText();

				if ( ! empty($allKeywords)) {

					foreach ($allKeywords as $keyword => $keywordData) {

						$keywords[$keyword] = $keywordData['tf'];
					}

					arsort($keywords);

					$suggestions = array_keys(array_slice($keywords, 0, 10));
				}

				if ($doCleanup) {

					$indexerService->removeFromIndex($resultItem->getUniqueId());
				}

				break;
			}
		}

		$this->getResponse()->setResponseData($suggestions);
	}

}