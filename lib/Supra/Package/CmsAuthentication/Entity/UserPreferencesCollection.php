<?php

/*
 * Copyright (C) SiteSupra SIA, Riga, Latvia, 2015
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 */

namespace Supra\Package\CmsAuthentication\Entity;

use Supra\Package\Cms\Entity\Abstraction\Entity;
use Doctrine\Common\Collections;

/**
 * Single user preferences collection
 * @Entity
 */
class UserPreferencesCollection extends Entity
{

	/**
	 * User settings collection
	 * @OneToMany(targetEntity="Supra\Package\CmsAuthentication\Entity\UserPreference", mappedBy="collection", cascade={"all"}, indexBy="name")
	 * @var Collections\Collection
	 */
	protected $preferences;

	/**
	 * 
	 */
	public function __construct()
	{
		parent::__construct();
		$this->preferences = new Collections\ArrayCollection();
	}

	/**
	 * @return Collections\Collection
	 */
	public function getPreferences()
	{
		return $this->preferences;
	}

	/**
	 * @param Collections\Collection $preferences
	 */
	public function setPreferences($preferences)
	{
		$this->preferences = $preferences;
	}

}
