<?php
/**
 * @package     JoRobo
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Jorobo\Tasks\Deploy;

use Joomla\Jorobo\Tasks\Build;
use Joomla\Jorobo\Tasks\JTask;
use Robo\Contract\TaskInterface;

/**
 * Deploy project as Package file
 */
class Package extends Base implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;
	use Build\buildTasks;
	use deployTasks;

	protected $target = null;

	protected $current = null;

	protected $hasComponent = true;

	protected $hasModules = true;

	protected $hasPackage = true;

	protected $hasPlugins = true;

	protected $hasLibraries = true;

	protected $hasCBPlugins = true;

	protected $hasTemplates = true;

	/**
	 * Build the package
	 *
	 * @return  bool
	 */
	public function run()
	{
		if (!$this->hasPackage)
		{
			$this->deployZip()->run();
			return true;
		}

		$params        = $this->getConfig()->params;
		$extension     = $this->getConfig()->extension;
		$version       = $this->getConfig()->version;
		$this->target  = JPATH_BASE . "/dist/pkg_" . $extension . "-" . $version . ".zip";
		$this->current = JPATH_BASE . "/dist/current";

		$this->say('Creating package ' . $extension . " " . $version);

		// Start getting single archives
		if (file_exists(JPATH_BASE . '/dist/zips'))
		{
			$this->_deleteDir(JPATH_BASE . '/dist/zips');
		}

		$this->_mkdir(JPATH_BASE . '/dist/zips');

		if ($this->hasComponent)
		{
			$this->buildComponent($params)->run();
			$this->createExtensionZips("components");
		}

		if ($this->hasModules)
		{
			$modules = $this->getSubExtensionName('modules');

			if (!empty($modules))
			{
				foreach ($modules as $module)
				{
					$this->buildModule($module, $params)->run();
				}
			}

			$this->createExtensionZips("modules");
		}

		if ($this->hasPlugins)
		{
			$path = $this->getSourceFolder() . "/plugins";
			$types = $this->getSubExtensionName('plugins');

			if (!empty($types))
			{
				foreach ($types as $type)
				{
					$p = $path . "/" . $type;

					// Get every plugin
					$hdl = opendir($p);

					while ($plugin = readdir($hdl))
					{
						// Ignore hidden files
						if (substr($plugin, 0, 1) == '.')
						{
							continue;
						}

						// Only folders
						$p2 = $p . "/" . $plugin;

						if (!is_file($p2))
						{
							$this->buildPlugin($type, $plugin, $params)->run();
						}
					}

					closedir($hdl);
				}
			}

			$this->createExtensionZips("plugins");
		}

		if ($this->hasTemplates)
		{
			$templates = $this->getSubExtensionName('templates');

			if (!empty($templates))
			{
				foreach ($templates as $template)
				{
					$this->buildTemplate($template, $params)->run();
				}
			}

			$this->createExtensionZips("templates");
		}

		$this->buildPackage($params)->run();

		$this->createPackageZip();

		$this->createSymlink($this->target, JPATH_BASE . "/dist/pkg-" . $this->getExtensionName() . "-current.zip");

		return true;
	}

	/**
	 * Create zips for Extensions
	 *
	 * @param   string $type Extension Type
	 *
	 * @return  void
	 */
	private function createExtensionZips($type)
	{
		$path = $this->getBuildFolder() . "/" . $type;

		// Get every Extension
		$hdl = opendir($path);

		while ($entry = readdir($hdl))
		{
			if (substr($entry, 0, 1) == '.')
			{
				continue;
			}

			// Only folders
			$p = $path . "/" . $entry;

			if (!is_file($p))
			{
				// Extension name folder
				$ext = $entry;

				$this->createSymlink($p, $this->current);

				$this->say("Packaging " . ucfirst($type) . " " . $ext);

				// Package file
				$this->createZip(JPATH_BASE . '/dist/zips/' . $ext . '.zip');

				$a = explode("_", $ext);

				switch ($a[0])
				{
					case 'plg':
						$this->packageFiles[$ext] = array('type' => $type, 'id' => $a[2], 'group' => $a[1]);
						break;

					case 'mod' || 'tpl':
						$this->packageFiles[$ext] = array('type' => $type, 'id' => $a[1], 'client' => 'site');
						break;

					default:
						$this->packageFiles[$ext] = array('type' => $type, 'id' => $a[1]);
						break;
				}
			}
		}

		closedir($hdl);
	}

	/**
	 * Create package zip (called latest)
	 *
	 * @return  void
	 */
	private function createPackageZip()
	{
		$path    = $this->getBuildFolder() . "/pkg_" . $this->getExtensionName();
		$pSource = JPATH_BASE . '/dist/zips';
		$pTarget = $path . "/";

		$this->_copyDir($pSource, $pTarget);
		$this->_deleteDir($pSource);

		$fileList = array();

		foreach ($this->packageFiles as $file => $attr)
		{
			$attribute = array();

			foreach ($attr as $key => $value)
			{
				$attribute[] = $key . '="' . $value . '"';
			}

			$attributes = implode(' ', $attribute);

			$fileList[] = '<file ' . $attributes . '>' . $file . '.zip</file>';
		}

		$f       = implode("\n", $fileList);
		$xmlFile = $path . "/pkg_" . $this->getExtensionName() . ".xml";

		$this->taskReplaceInFile($xmlFile)
			->from('##FILES##')
			->to($f)
			->run();

		$this->createSymlink($path, $this->current);

		$this->say("Packaging Package " . $this->getExtensionName());

		// Package file
		$this->createZip($this->target);
	}

	/**
	 * Get Name of Extensions from type
	 *
	 * @param   string  $type  modules, plugins, templates...
	 *
	 * @return  array
	 */
	private function getSubExtensionName($type)
	{
		$return = array();
		$path   = $this->getSourceFolder() . "/" . $type;

		// Get every subextension from type
		$hdl = opendir($path);

		while ($entry = readdir($hdl))
		{
			// Ignore hidden files
			if (substr($entry, 0, 1) == '.')
			{
				continue;
			}

			// Only folders
			$p = $path . "/" . $entry;

			if (!is_file($p))
			{
				$return[] = $entry;
			}
		}

		closedir($hdl);

		return $return;
	}
}
