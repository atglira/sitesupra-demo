<?php

namespace Supra\User;

use Supra\User\Entity;
use Supra\Authentication\Adapter;
use Supra\Authentication\AuthenticationPassword;
use Supra\Authentication\Exception\AuthenticationFailure;


interface UserProviderInterface
{
	/**
	 * Passes user to authentication adapter
	 * @param string $login 
	 * @param AuthenticationPassword $password
	 * @return Entity\User
	 * @throws AuthenticationFailure
	 */
	public function authenticate($login, AuthenticationPassword $password);
	
	/**
	 * Find user by login
	 * @param string $login
	 * @return Entity\User 
	 */
	public function findUserByLogin($login);
	
	/**
	 * Find user by email
	 * @param string $email
	 * @return Entity\User
	 */
	public function findUserByEmail($email);

	/**
	 * Find user by id
	 * @param string $id
	 * @return Entity\User 
	 */
	public function findUserById($id);

	/**
	 * Find group by name
	 * @param string $name
	 * @return Entity\Group 
	 */
	public function findGroupByName($name);

	/**
	 * Find group by id
	 * @param string $id
	 * @return Entity\Group
	 */
	public function findGroupById($id);
	
	/**
	 * Find all users
	 * @return array
	 */
	public function findAllUsers();

	/**
	 * Find all groups
	 * @return array
	 */
	public function findAllGroups();

	/**
	 * Find all users in single group
	 * @param Entity\Group $group
	 * @return array
	 */
	public function getAllUsersInGroup(Entity\Group $group);
	
	/**
	 * Create and return new user
	 * @return Entity\User
	 */
	public function createUser();
	
	/**
	 * Remove user
	 * @param Entity\User $user
	 */
	public function doDeleteUser(Entity\User $user);
	
	/**
	 * Update/store user property changes
	 * @param Entity\User $user
	 */
	public function updateUser(Entity\User $user);
	
}
