<?php

namespace Supra\Cms\ContentManager\Template;

use Supra\Controller\SimpleController;
use Supra\Cms\ContentManager\PageManagerAction;
use Supra\Controller\Pages\Entity;
use Supra\Controller\Pages\Request\PageRequest;
use Supra\Controller\Pages\Exception\DuplicatePagePathException;
use Supra\Cms\Exception\CmsException;
use Supra\Controller\Layout\Exception as LayoutException;
use Supra\Controller\Layout\Processor\ProcessorInterface;
use Supra\Controller\Pages\Task\LayoutProcessorTask;
use Supra\Controller\Pages\Event\AuditEvents;
use Supra\Controller\Pages\Event\PageEventArgs;

/**
 * Sitemap
 */
class TemplateAction extends PageManagerAction
{

	/**
	 * Template creation
	 */
	public function createAction()
	{
		$this->checkApplicationAllAccessPermission();

		$this->entityManager->beginTransaction();
		$templateData = null;

		try {
			$templateData = $this->createActionTransactional();
		} catch (\Exception $e) {
			$this->entityManager->rollback();

			throw $e;
		}

		$this->entityManager->commit();

		// Decision in #2695 to publish the template right after creating it
		$this->pageData = $templateData;
		$this->publish();

		$this->outputPage($templateData);

		$this->writeAuditLog('create', '%item% created', $templateData);
	}

	/**
	 * Method called in transaction
	 * @return Entity\TemplateLocalization
	 */
	protected function createActionTransactional()
	{
		$this->checkApplicationAllAccessPermission();

		$this->isPostRequest();
		$input = $this->getRequestInput();

		$rootTemplate = $input->isEmpty('parent', false);
		$hasLayout = ( ! $input->isEmpty('layout'));

		if ($rootTemplate && ! $hasLayout) {
			throw new CmsException(null, "Root template must have layout specified");
		}

		$localeId = $this->getLocale()->getId();

		$eventManager = $this->entityManager->getEventManager();
		$eventManager->dispatchEvent(AuditEvents::pagePreCreateEvent);
		
		$template = new Entity\Template();
		$templateData = new Entity\TemplateLocalization($localeId);

		$this->entityManager->persist($template);
		$this->entityManager->persist($templateData);

		$templateData->setMaster($template);

		if ($input->has('title')) {
			$title = $input->get('title');
			$templateData->setTitle($title);
		}

		if ($hasLayout) {
			//TODO: validate
			$layoutId = $input->get('layout');
			$layoutProcessor = $this->getPageController()
					->getLayoutProcessor();

			$layoutTask = new LayoutProcessorTask();
			$layoutTask->setLayoutId($layoutId);
			$layoutTask->setEntityManager($this->entityManager);
			$layoutTask->setLayoutProcessor($layoutProcessor);

			try {
				$layoutTask->perform();
			} catch (LayoutException\LayoutNotFoundException $e) {
				throw new CmsException('template.error.layout_not_found', null, $e);
			} catch (LayoutException\RuntimeException $e) {
				throw new CmsException('template.error.layout_error', null, $e);
			}

			$layout = $layoutTask->getLayout();

			$templateLayout = $template->addLayout($this->getMedia(), $layout);
			$this->entityManager->persist($templateLayout);
		}

		$this->entityManager->flush();

		// Find parent page
		if ( ! $rootTemplate) {

			$parentLocalization = $this->getPageLocalizationByRequestKey('parent');

			if ( ! $parentLocalization instanceof Entity\TemplateLocalization) {
				$parentId = $input->get('parent', null);
				throw new CmsException(null, "Could not found template parent by ID $parentId");
			}

			$parent = $parentLocalization->getMaster();

			// Set parent
			$template->moveAsLastChildOf($parent);
			$this->entityManager->flush();
		}
		
		$pageEventArgs = new PageEventArgs();
		$pageEventArgs->setProperty('localizationId', $templateData->getId());
		$pageEventArgs->setEntityManager($this->entityManager);
		$eventManager->dispatchEvent(AuditEvents::pagePostCreateEvent, $pageEventArgs);

		return $templateData;
	}

	/**
	 * Settings save action
	 */
	public function saveAction()
	{
		$this->checkApplicationAllAccessPermission();

		$this->isPostRequest();
		$input = $this->getRequestInput();
		$this->checkLock();
		$pageData = $this->getPageLocalization();

		//TODO: create some simple objects for save post data with future validation implementation?
		if ($input->has('title')) {
			$title = $input->get('title');
			$pageData->setTitle($title);
		}

		$this->entityManager->flush();

		$this->writeAuditLog('save', '%item% saved', $pageData);
	}

	public function deleteAction()
	{
		$this->checkApplicationAllAccessPermission();

		$this->isPostRequest();

		$page = $this->getPageLocalization()
				->getMaster();

		if ($page->hasChildren()) {
			throw new CmsException(null, "Cannot remove template with children");
		}

		$this->delete();

		$this->writeAuditLog('delete', '%item% deleted', $page);
	}

	/**
	 * Called on template publish
	 */
	public function publishAction()
	{
		$this->checkApplicationAllAccessPermission();

		// Must be executed with POST method
		$this->isPostRequest();

		$this->checkLock();
		$this->publish();
		$this->unlockPage();

		$templateLocalization = $this->getPageLocalization();
		$this->writeAuditLog('publish', '%item% published', $templateLocalization);
	}

	/**
	 * Called on template lock action
	 */
	public function lockAction()
	{
		$this->checkApplicationAllAccessPermission();

		$this->lockPage();
	}

	/**
	 * Called on template unlock action
	 */
	public function unlockAction()
	{
		$this->checkApplicationAllAccessPermission();

		try {
			$this->checkLock();
		} catch (\Exception $e) {
			$this->getResponse()->setResponseData(true);
			return;
		}
		$this->unlockPage();
	}

	/**
	 * Template duplicate action
	 */
	public function duplicateAction()
	{
		$this->checkApplicationAllAccessPermission();

		$this->isPostRequest();
		$this->duplicate();
	}

	/**
	 * Duplicate global localization
	 */
	public function duplicateGlobalAction()
	{
		$this->checkApplicationAllAccessPermission();

		$this->isPostRequest();
		$this->duplicateGlobal();
	}

}
