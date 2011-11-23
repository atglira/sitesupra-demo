<?php

namespace Supra\Tests\Doctrine;

use Doctrine\ORM\UnitOfWork;

class DDC1441Test extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        parent::setUp();
		
		$this->_em = \Supra\ObjectRepository\ObjectRepository::getEntityManager($this);
		$this->_schemaTool = new \Doctrine\ORM\Tools\SchemaTool($this->_em);
		
        try {
            $this->_schemaTool->createSchema(array(
                $this->_em->getClassMetadata(__NAMESPACE__ . '\DDC1441File'),
                $this->_em->getClassMetadata(__NAMESPACE__ . '\DDC1441Picture'),
            ));
        } catch(\Exception $ignored) {}
    }

    public function testFailingCase()
    {
		if (version_compare(\Doctrine\ORM\Version::VERSION, '2.1.4', 'lt')) {
			self::markTestSkipped("Is not working in Doctrine ORM 2.1.3");
		}
		
		$s = 'O:35:"Supra\Tests\Doctrine\DDC1441Picture":2:{s:46:" Supra\Tests\Doctrine\DDC1441Picture pictureId";i:2;s:41:" Supra\Tests\Doctrine\DDC1441Picture file";O:46:"Supra\Proxy\SupraTestsDoctrineDDC1441FileProxy":2:{s:17:"__isInitialized__";b:0;s:6:"fileId";N;}}';
		
		// Remove all data so we can count in the end
		$this->_em->createQuery("DELETE FROM " . __NAMESPACE__ . '\DDC1441Picture')->execute();
		$this->_em->createQuery("DELETE FROM " . __NAMESPACE__ . '\DDC1441File')->execute();
		
        $file = new DDC1441File;

        $picture = new DDC1441Picture;
        $picture->setFile($file);
		
		$id = $picture->getPictureId();

		/* @var $em \Doctrine\ORM\EntityManager */
        $em = $this->_em;
        $em->persist($picture);
        $em->flush();
        $em->clear();

        $pic = $em->find(__NAMESPACE__ . '\DDC1441Picture', $id);
		/* @var $pic DDC1441Picture */
		$s = serialize($pic);
		
		$file = $pic->getFile();
		$proxyClassName = get_class($file);
		
//		$pic = unserialize($s);
		
		// Unset the metadata for proxy object, partially simulates clean environment
		$em->getMetadataFactory()
				->setMetadataFor($proxyClassName, null);
		
		$em->merge($pic);
    }
}

/**
 * @Entity
 */
class DDC1441Picture
{
    /**
     * @Column(name="picture_id", type="integer")
     * @Id @GeneratedValue
     */
    private $pictureId;

    /**
     * @ManyToOne(targetEntity="DDC1441File", cascade={"persist", "remove", "merge"})
     * @JoinColumns({
     *   @JoinColumn(name="file_id", referencedColumnName="file_id")
     * })
     */
    private $file;

    /**
     * Get pictureId
     */
    public function getPictureId()
    {
        return $this->pictureId;
    }

    /**
     * Set file
     */
    public function setFile($value = null)
    {
        $this->file = $value;
    }

    /**
     * Get file
     */
    public function getFile()
    {
        return $this->file;
    }
}

/**
 * @Entity
 */
class DDC1441File
{
    /**
     * @Column(name="file_id", type="integer")
     * @Id
     * @GeneratedValue(strategy="AUTO")
     */
    public $fileId;

    /**
     * Get fileId
     */
    public function getFileId()
    {
        return $this->fileId;
    }
}