<?php

namespace Supra\Proxy;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class SupraControllerPagesEntityPageDataProxy extends \Supra\Controller\Pages\Entity\PageData implements \Doctrine\ORM\Proxy\Proxy
{
    private $_entityPersister;
    private $_identifier;
    public $__isInitialized__ = false;
    public function __construct($entityPersister, $identifier)
    {
        $this->_entityPersister = $entityPersister;
        $this->_identifier = $identifier;
    }
    /** @private */
    public function __load()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;

            if (method_exists($this, "__wakeup")) {
                // call this after __isInitialized__to avoid infinite recursion
                // but before loading to emulate what ClassMetadata::newInstance()
                // provides.
                $this->__wakeup();
            }

            if ($this->_entityPersister->load($this->_identifier, $this) === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            unset($this->_entityPersister, $this->_identifier);
        }
    }
    
    
    public function getPage()
    {
        $this->__load();
        return parent::getPage();
    }

    public function setPage(\Supra\Controller\Pages\Entity\Page $page)
    {
        $this->__load();
        return parent::setPage($page);
    }

    public function setTemplate(\Supra\Controller\Pages\Entity\Template $template)
    {
        $this->__load();
        return parent::setTemplate($template);
    }

    public function getTemplate()
    {
        $this->__load();
        return parent::getTemplate();
    }

    public function getTemplateHierarchy()
    {
        $this->__load();
        return parent::getTemplateHierarchy();
    }

    public function setPath($path)
    {
        $this->__load();
        return parent::setPath($path);
    }

    public function getPath()
    {
        $this->__load();
        return parent::getPath();
    }

    public function setPathPart($pathPart)
    {
        $this->__load();
        return parent::setPathPart($pathPart);
    }

    public function getPathPart()
    {
        $this->__load();
        return parent::getPathPart();
    }

    public function getMetaDescription()
    {
        $this->__load();
        return parent::getMetaDescription();
    }

    public function setMetaDescription($metaDescription)
    {
        $this->__load();
        return parent::setMetaDescription($metaDescription);
    }

    public function getMetaKeywords()
    {
        $this->__load();
        return parent::getMetaKeywords();
    }

    public function setMetaKeywords($metaKeywords)
    {
        $this->__load();
        return parent::setMetaKeywords($metaKeywords);
    }

    public function isActive()
    {
        $this->__load();
        return parent::isActive();
    }

    public function setActive($active)
    {
        $this->__load();
        return parent::setActive($active);
    }

    public function getScheduleTime()
    {
        $this->__load();
        return parent::getScheduleTime();
    }

    public function setScheduleTime(\DateTime $scheduleTime)
    {
        $this->__load();
        return parent::setScheduleTime($scheduleTime);
    }

    public function unsetScheduleTime()
    {
        $this->__load();
        return parent::unsetScheduleTime();
    }

    public function getRedirect()
    {
        $this->__load();
        return parent::getRedirect();
    }

    public function setRedirect(\Supra\Controller\Pages\Entity\ReferencedElement\LinkReferencedElement $redirect = NULL)
    {
        $this->__load();
        return parent::setRedirect($redirect);
    }

    public function getLocale()
    {
        $this->__load();
        return parent::getLocale();
    }

    public function setTitle($title)
    {
        $this->__load();
        return parent::setTitle($title);
    }

    public function getTitle()
    {
        $this->__load();
        return parent::getTitle();
    }

    public function setMaster(\Supra\Controller\Pages\Entity\Abstraction\AbstractPage $master)
    {
        $this->__load();
        return parent::setMaster($master);
    }

    public function getMaster()
    {
        $this->__load();
        return parent::getMaster();
    }

    public function authorize(\Supra\User\Entity\Abstraction\User $user, $permissionType)
    {
        $this->__load();
        return parent::authorize($user, $permissionType);
    }

    public function getAuthorizationId()
    {
        $this->__load();
        return parent::getAuthorizationId();
    }

    public function getAuthorizationClass()
    {
        $this->__load();
        return parent::getAuthorizationClass();
    }

    public function getAuthorizationAncestors($includingSelf = true)
    {
        $this->__load();
        return parent::getAuthorizationAncestors($includingSelf);
    }

    public function getPermissionTypes()
    {
        $this->__load();
        return parent::getPermissionTypes();
    }

    public function getDiscriminator()
    {
        $this->__load();
        return parent::getDiscriminator();
    }

    public function matchDiscriminator(\Supra\Controller\Pages\Entity\Abstraction\Entity $object, $strict = true)
    {
        $this->__load();
        return parent::matchDiscriminator($object, $strict);
    }

    public function getId()
    {
        $this->__load();
        return parent::getId();
    }

    public function equals(\Supra\Database\Entity $entity)
    {
        $this->__load();
        return parent::equals($entity);
    }

    public function __toString()
    {
        $this->__load();
        return parent::__toString();
    }

    public function getProperty($name)
    {
        $this->__load();
        return parent::getProperty($name);
    }


    public function __sleep()
    {
        return array('__isInitialized__', 'locale', 'title', 'master', 'id', 'template', 'path', 'pathPart', 'metaDescription', 'metaKeywords', 'active', 'scheduleTime', 'redirect');
    }

    public function __clone()
    {
        if (!$this->__isInitialized__ && $this->_entityPersister) {
            $this->__isInitialized__ = true;
            $class = $this->_entityPersister->getClassMetadata();
            $original = $this->_entityPersister->load($this->_identifier);
            if ($original === null) {
                throw new \Doctrine\ORM\EntityNotFoundException();
            }
            foreach ($class->reflFields AS $field => $reflProperty) {
                $reflProperty->setValue($this, $reflProperty->getValue($original));
            }
            unset($this->_entityPersister, $this->_identifier);
        }
        
    }
}