<?php

namespace Supra\Proxy;

/**
 * THIS CLASS WAS GENERATED BY THE DOCTRINE ORM. DO NOT EDIT THIS FILE.
 */
class SupraControllerPagesEntityAbstractionPlaceHolderProxy extends \Supra\Controller\Pages\Entity\Abstraction\PlaceHolder implements \Doctrine\ORM\Proxy\Proxy
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
    
    
    public function getName()
    {
        $this->__load();
        return parent::getName();
    }

    public function getLocked()
    {
        $this->__load();
        return parent::getLocked();
    }

    public function getBlocks()
    {
        $this->__load();
        return parent::getBlocks();
    }

    public function addBlock(\Supra\Controller\Pages\Entity\Abstraction\Block $block)
    {
        $this->__load();
        return parent::addBlock($block);
    }

    public function setMaster(\Supra\Controller\Pages\Entity\Abstraction\Page $master)
    {
        $this->__load();
        return parent::setMaster($master);
    }

    public function getMaster()
    {
        $this->__load();
        return parent::getMaster();
    }

    public function getMaxBlockPosition()
    {
        $this->__load();
        return parent::getMaxBlockPosition();
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
        return array('__isInitialized__', 'type', 'name', 'blocks', 'master', 'id');
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