<?php

namespace Supra\FileStorage\Entity\Abstraction;

use Supra\NestedSet;
use Supra\Authorization\AuthorizedEntityInterface;
use Supra\Authorization\Permission\Permission;
use Supra\User\Entity\AbstractUser;
use Supra\Authorization\AuthorizationProvider;
use Supra\FileStorage\Entity\SlashFolder;
use Supra\Database\Doctrine\Listener\Timestampable;
use Supra\AuditLog\TitleTrackingItemInterface;
use Supra\FileStorage\Entity\FilePath;

/**
 * File abstraction
 * @Entity(repositoryClass="Supra\FileStorage\Repository\FileNestedSetRepository")
 * @InheritanceType("JOINED")
 * @DiscriminatorColumn(name="discr", type="string")
 * @DiscriminatorMap({"file" = "Supra\FileStorage\Entity\File", "folder" = "Supra\FileStorage\Entity\Folder", "image" = "Supra\FileStorage\Entity\Image"})
 * @Table(name="file_abstraction", indexes={
 * 		@index(name="file_abstraction_lft_idx", columns={"lft"}),
 * 		@index(name="file_abstraction_rgt_idx", columns={"rgt"}),
 * 		@index(name="file_abstraction_lvl_idx", columns={"lvl"})
 * })
 * @method int getNumberChildren()
 * @method NestedSet\Node\NodeAbstraction addChild(NestedSet\Node\NodeInterface $child)
 * @method void delete()
 * @method boolean hasNextSibling()
 * @method boolean hasPrevSibling()
 * @method int getNumberDescendants()
 * @method boolean hasParent()
 * @method NestedSet\Node\NodeAbstraction getParent()
 * @method array getAncestors(int $levelLimit, boolean $includeNode)
 * @method array getDescendants(int $levelLimit, boolean $includeNode)
 * @method NestedSet\Node\NodeAbstraction getFirstChild()
 * @method NestedSet\Node\NodeAbstraction getLastChild()
 * @method NestedSet\Node\NodeAbstraction getNextSibling()
 * @method NestedSet\Node\NodeAbstraction getPrevSibling()
 * @method array getChildren()
 * @method array getSiblings(boolean $includeNode)
 * @method boolean hasChildren()
 * @method NestedSet\Node\NodeAbstraction moveAsNextSiblingOf(NestedSet\Node\NodeInterface $afterNode)
 * @method NestedSet\Node\NodeAbstraction moveAsPrevSiblingOf(NestedSet\Node\NodeInterface $beforeNode)
 * @method NestedSet\Node\NodeAbstraction moveAsFirstChildOf(NestedSet\Node\NodeInterface $parentNode)
 * @method NestedSet\Node\NodeAbstraction moveAsLastChildOf(NestedSet\Node\NodeInterface $parentNode)
 * @method boolean isLeaf()
 * @method boolean isRoot()
 * @method boolean isAncestorOf(NestedSet\Node\NodeInterface $node)
 * @method boolean isDescendantOf(NestedSet\Node\NodeInterface $node)
 * @method boolean isEqualTo(NestedSet\Node\NodeInterface $node)
 */
abstract class File extends Entity implements NestedSet\Node\EntityNodeInterface, AuthorizedEntityInterface, Timestampable, TitleTrackingItemInterface
{
	const PERMISSION_UPLOAD_NAME = 'file_upload';
	const PERMISSION_UPLOAD_MASK = 256;
	const PERMISSION_DELETE_NAME = 'file_delete';
	const PERMISSION_DELETE_MASK = 512;

	/**
	 * Integer object type ID
	 */
	const TYPE_ID = 0;

	/**
	 * @var NestedSet\Node\DoctrineNode
	 */
	protected $nestedSetNode;

	/**
	 * @Column(type="integer", name="lft", nullable=true)
	 * @var integer
	 */
	protected $left;

	/**
	 * @Column(type="integer", name="rgt", nullable=true)
	 * @var integer
	 */
	protected $right;

	/**
	 * @Column(type="integer", name="lvl", nullable=true)
	 * @var integer
	 */
	protected $level;

	/**
	 * @Column(type="string", name="file_name", nullable=false)
	 * @var string
	 */
	protected $fileName;

	/**
	 * @Column(type="datetime", name="created_at")
	 * @var \DateTime
	 */
	protected $creationTime;

	/**
	 * @Column(type="datetime", name="modified_at")
	 * @var \DateTime
	 */
	protected $modificationTime;

	/**
	 * @Column(type="boolean", name="public")
	 * @var integer
	 */
	protected $public = true;

	/**
	 * @var string
	 */
	protected $originalFileName;

	/**
	 * @OneToOne(targetEntity="\Supra\FileStorage\Entity\FilePath", cascade={"remove", "persist", "merge"}, fetch="EAGER")
	 * @var \Supra\FileStorage\Entity\FilePath
	 */
	protected $path;

	/**
	 * @return FilePath 
	 */
	public function getPathEntity()
	{
		if (is_null($this->path)) {
			$this->path = new \Supra\FileStorage\Entity\FilePath();
			$this->path->setId($this->getId());
		}

		return $this->path;
	}

	/**
	 * @param FilePath $pathEntity
	 */
	public function setPathEntity(FilePath $pathEntity)
	{
		$this->path = $pathEntity;
	}

	/**
	 * Returns path 
	 * @param string $separator
	 * @param boolean $includeNode
	 * @return string
	 */
	public function getPath($separator = '/', $includeNode = true)
	{
		$filePath = $this->getPathEntity();

		if ( ! $filePath instanceof FilePath) {
			\Log::warn("File: {$this->getFileName()} ({$this->getId()}) has empty path. Run regenerate command.");
			return null;
		}

		$path = $filePath->getSystemPath();
		$pathParts = explode(DIRECTORY_SEPARATOR, $path);

		if ( ! $includeNode) {
			array_pop($pathParts);
		}

		$path = implode($separator, $pathParts);

		return $path;
	}

	/**
	 * Get left value
	 * @return int
	 */
	public function getLeftValue()
	{
		return $this->left;
	}

	/**
	 * Get right value
	 * @return int
	 */
	public function getRightValue()
	{
		return $this->right;
	}

	/**
	 * Get depth level
	 * @return int
	 */
	public function getLevel()
	{
		return $this->level;
	}

	/**
	 * Returns creation time
	 * @return \DateTime
	 */
	public function getCreationTime()
	{
		return $this->creationTime;
	}

	/**
	 * Sets creation time
	 * @param \DateTime $time
	 */
	public function setCreationTime(\DateTime $time = null)
	{
		if (is_null($time)) {
			$time = new \DateTime('now');
		}
		$this->creationTime = $time;
	}

	/**
	 * Returns last modification time
	 * @return \DateTime
	 */
	public function getModificationTime()
	{
		return $this->modificationTime;
	}

	/**
	 * Sets modification time
	 * @param \DateTime $time
	 */
	public function setModificationTime(\DateTime $time = null)
	{
		if (is_null($time)) {
			$time = new \DateTime('now');
		}
		$this->modificationTime = $time;
	}

	/**
	 * Set left value
	 * @param int $left
	 * @return Product
	 */
	public function setLeftValue($left)
	{
		$this->left = $left;
		if (isset($this->nestedSetNode)) {
			$this->nestedSetNode->setLeftValue($left);
		}
		return $this;
	}

	/**
	 * Set right value
	 * @param int $right
	 * @return Product
	 */
	public function setRightValue($right)
	{
		$this->right = $right;
		if (isset($this->nestedSetNode)) {
			$this->nestedSetNode->setRightValue($right);
		}
		return $this;
	}

	/**
	 * Set depth level
	 * @param int $level
	 * @return Product
	 */
	public function setLevel($level)
	{
		$this->level = $level;

		if (isset($this->nestedSetNode)) {
			$this->nestedSetNode->setLevel($level);
		}
		return $this;
	}

	/**
	 * Move left value by the difference
	 * @param int $diff
	 * @return Product
	 */
	public function moveLeftValue($diff)
	{
		$this->left += $diff;
		if (isset($this->nestedSetNode)) {
			$this->nestedSetNode->moveLeftValue($diff);
		}
		return $this;
	}

	/**
	 * Move right value by the difference
	 * @param int $diff
	 * @return Product
	 */
	public function moveRightValue($diff)
	{
		$this->right += $diff;
		if (isset($this->nestedSetNode)) {
			$this->nestedSetNode->moveRightValue($diff);
		}
		return $this;
	}

	/**
	 * Move depth level by the difference
	 * @param int $diff
	 * @return Product
	 */
	public function moveLevel($diff)
	{
		$this->level += $diff;

		if (isset($this->nestedSetNode)) {
			$this->nestedSetNode->moveLevel($diff);
		}
		return $this;
	}

	/**
	 * Nested node title
	 * @return string
	 */
	public function getNodeTitle()
	{
		return $this->__toString();
	}

	/**
	 * Try the unknown method against the nested set node
	 * @param string $method
	 * @param array $arguments
	 * @return mixed
	 */
	public function __call($method, $arguments)
	{
		$node = $this->nestedSetNode;
		if (is_null($this->nestedSetNode)) {
			throw new NestedSet\Exception\BadMethodCall("Method $method does not exist for class " . __CLASS__ . " and it's node object is not initialized. Try persisting object first.");
		}

		if ( ! method_exists($node, $method)) {
			throw new NestedSet\Exception\BadMethodCall("Method $method does not exist for class " . __CLASS__ . " and it's node object.");
		}
		$callable = array($node, $method);
		$result = call_user_func_array($callable, $arguments);

		// Compare the result with $node and return $this on match to keep method chaining
		if ($result === $node) {
			$result = $this;
		}

		return $result;
	}

	/**
	 * So the full path generation method of the file would use the cloned and changed entity
	 */
	public function __clone()
	{
		if ( ! empty($this->id)) {
			if ( ! empty($this->nestedSetNode)) {
				$this->nestedSetNode = clone($this->nestedSetNode);
				$this->nestedSetNode->belongsTo($this);
			}
			
			$this->path = clone($this->path);
		}
	}

	/**
	 * Free the node unsetting the pointers to other objects.
	 * MUST clear entity manager after doing this!
	 */
	public function free()
	{
		if ( ! is_null($this->nestedSetNode)) {
			$this->nestedSetNode->free($this);
			$this->nestedSetNode = null;
		}
	}

	/**
	 * {@inheritdoc}
	 * @return string
	 */
	public function getNestedSetRepositoryClassName()
	{
		// One nested set repository for folders, files, images
		$className = __CLASS__;

		return $className;
	}

	public function setFileName($fileName)
	{
		$result = preg_replace('/\s+/i', ' ', $fileName);

		if ( ! is_null($this->fileName)) {
			$this->originalFileName = $this->fileName;
		}

		$this->fileName = trim($result);
	}

	public function getFileName()
	{
		return $this->fileName;
	}

	public function __toString()
	{
		return $this->getFileName();
	}

	/**
	 * Get public state
	 *
	 * @return boolean
	 */
	public function isPublic()
	{
		return $this->public;
	}

	/**
	 * Set public state
	 *
	 * @param boolean $public 
	 */
	public function setPublic($public)
	{
		$this->public = $public;
	}

	/**
	 * {@inheritdoc}
	 * @param NestedSet\Node\DoctrineNode $nestedSetNode
	 */
	public function setNestedSetNode(NestedSet\Node\DoctrineNode $nestedSetNode)
	{
		$this->nestedSetNode = $nestedSetNode;
	}

	/**
	 * {@inheritdoc}
	 * @return NestedSet\Node\DoctrineNode
	 */
	public function getNestedSetNode()
	{
		return $this->nestedSetNode;
	}

	/**
	 * Loads item info array
	 * @param string $locale
	 * @return array
	 */
	public function getInfo($locale = null)
	{
		$info = array(
			'id' => $this->getId(),
			'filename' => $this->getFileName(),
			'type' => static::TYPE_ID,
			'created' => $this->getCreationTime()->format('c'),
		);

		return $info;
	}

	/**
	 * @param AbstractUser $user
	 * @param Permission $permission
	 * @param boolean $grant
	 * @return boolean
	 */
	public function authorize(AbstractUser $user, $permission, $grant)
	{
		return $grant;
	}

	/**
	 * @return string
	 */
	public function getAuthorizationId()
	{
		return $this->getId();
	}

	/**
	 * @return string
	 */
	public function getAuthorizationClass()
	{
		return __CLASS__;
	}

	/**
	 * @return array
	 */
	public function getAuthorizationAncestors()
	{
		$ancestors = $this->getAncestors(0, false);

		// Append synthetic "slash" folder to the beginng of ancestors list.
		$ancestors[] = new SlashFolder();

		return $ancestors;
	}

	/**
	 * @param AuthorizationProvider $ap 
	 */
	public static function registerPermissions(AuthorizationProvider $ap)
	{
		$ap->registerGenericEntityPermission(self::PERMISSION_DELETE_NAME, self::PERMISSION_DELETE_MASK, __CLASS__);
		$ap->registerGenericEntityPermission(self::PERMISSION_UPLOAD_NAME, self::PERMISSION_UPLOAD_MASK, __CLASS__);
	}

	/**
	 * @return string
	 */
	public static function getAlias()
	{
		return 'file';
	}

	/**
	 * Used to improve audit log readability
	 */
	public function getOriginalTitle()
	{
		return $this->originalFileName;
	}

	/**
	 * Wrapper
	 * @return string
	 */
	public function getTitle()
	{
		return $this->getFileName();
	}

}
