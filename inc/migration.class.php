<?php
/**
 * -------------------------------------------------------------------------
 *  Camera Input
 *  Copyright (C) 2020-2021 by Curtis Conard
 *  https://github.com/cconard96/glpi-camerainput-plugin
 *  -------------------------------------------------------------------------
 *  LICENSE
 *  This file is part of Camera Input.
 *  Camera Input is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *  Camera Input is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *  You should have received a copy of the GNU General Public License
 *  along with Camera Input. If not, see <http://www.gnu.org/licenses/>.
 *  --------------------------------------------------------------------------
 */

/**
 * Handles migrating between plugin versions
 */
class PluginCamerainputMigration
{
	private const BASE_VERSION = '2.0.0';

	/** @var \Glpi\DB\DB */
	protected $db;


	public function __construct()
	{
		global $DB;
		$this->db = $DB;
	}


	public function applyMigrations(): void
	{
		$rc = new ReflectionClass($this);
		$migration_functions = array_map(static function ($rm) {
		   return $rm->getShortName();
		}, array_filter($rc->getMethods(), static function ($m) {
		   return preg_match('/^apply_.*_migration$/', $m->getShortName());
		}));

		if (count($migration_functions)) {
		   // Map versions to functions
		   $version_map = [];
		   foreach ($migration_functions as $function) {
		      $ver = str_replace(['apply_', '_migration', '_'], ['', '', '.'], $function);
		      $version_map[$ver] = $function;
		   }

		   // Sort semantically
		   uksort($version_map, 'version_compare');

		   $last_known_version = \Glpi\Core\Config::getConfigurationValues('plugin:camerainput')['version'] ?? self::BASE_VERSION;

		   // Call each migration in order starting from the last known version
		   foreach ($version_map as $version => $func) {
		      if (version_compare($last_known_version, $version, '<')) {
		         \Glpi\Toolbox\PluginMigration::execute($this, $func);
                 $this->setPluginVersionInDB($version);
                 $last_known_version = $version;
		      }
		   }
		}
	}


	private function setPluginVersionInDB(string $version): void
	{
		$this->db->updateOrInsert(\Glpi\Core\Config::getTable(), [
		   'value'     => $version,
		], [
		   'context'   => 'plugin:camerainput',
		   'name'      => 'version'
		]);
	}
}