<?php
/**
 * @package     JoRobo
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Jorobo\Tasks\Deploy;

use Joomla\Jorobo\Tasks\JTask;
use Robo\Contract\TaskInterface;

/**
 * Deploy project as Package file
 */
class Package extends Base implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;

	/**
	 * The target Zip file of the package
	 *
	 * @var    string
	 */
	protected $target = null;

	protected $packageFiles = array();

	private $hasComponent = true;

	private $hasModules = true;

	private $hasTemplates = true;

	private $hasPlugins = true;

	private $hasLibraries = true;

	private $hasCBPlugins = true;

	/**
	 * Initialize Build Task
	 */
	public function __construct()
	{
		parent::__construct();

		$this->target  = JPATH_BASE . "/dist/pkg_" . $this->getExtensionName() . "-" . $this->getConfig()->version . ".zip";
		$this->current = JPATH_BASE . "/dist/current";
	}

	/**
	 * Build the package
	 *
	 * @return  bool
	 */
	public function run()
	{
		// TODO improve DRY!
		$this->say('Creating package ' . $this->getConfig()->extension . " " . $this->getConfig()->version);

		// Start getting single archives
		if (file_exists(JPATH_BASE . '/dist/zips'))
		{
			$this->_deleteDir(JPATH_BASE . '/dist/zips');
		}

		$this->_mkdir(JPATH_BASE . '/dist/zips');

		$this->analyze();

		if ($this->hasComponent)
		{
			$this->createExtensionZips("components");
		}

		if ($this->hasModules)
		{
			$this->createExtensionZips("modules");
		}

		if ($this->hasPlugins)
		{
			$this->createExtensionZips("plugins");
		}

		if ($this->hasTemplates)
		{
			$this->createExtensionZips("templates");
		}

		$this->createPackageZip();

		$this->_symlink($this->target, JPATH_BASE . "/dist/pkg-" . $this->getExtensionName() . "-current.zip");

		return true;
	}

	/**
	 * Analyze the extension structure
	 *
	 * @return  void
	 */
	private function analyze()
	{
		// Check if we have component, module, plugin etc.
		if (!file_exists($this->getBuildFolder() . "/administrator/components/com_" . $this->getExtensionName())
			&& !file_exists($this->getBuildFolder() . "/components/com_" . $this->getExtensionName())
		)
		{
			$this->say("Extension has no component");
			$this->hasComponent = false;
		}

		if (!file_exists($this->getBuildFolder() . "/modules"))
		{
			$this->hasModules = false;
		}

		if (!file_exists($this->getBuildFolder() . "/plugins"))
		{
			$this->hasPlugins = false;
		}

		if (!file_exists($this->getBuildFolder() . "/templates"))
		{
			$this->hasTemplates = false;
		}

		if (!file_exists($this->getBuildFolder() . "/libraries"))
		{
			$this->hasLibraries = false;
		}

		if (!file_exists($this->getBuildFolder() . "/components/com_comprofiler"))
		{
			$this->hasCBPlugins = false;
		}
	}

	/**
	 * Create zips for Extensions
	 *
	 * @param   string  $type  Extension Type
	 * @return  void
	 */
	public function createExtensionZips($type)
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

				$this->_symlink($p, $this->current);

				$this->say("Packaging " . ucfirst($type) . " " . $ext);

				// Package file
				$zip = new Zip(JPATH_BASE . '/dist/zips/' . $ext . '.zip');
				$zip->run();

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
	public function createPackageZip()
	{
		$path = $this->getBuildFolder() . "/pkg_" . $this->getExtensionName();
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

		$f = implode("\n", $fileList);
		$xmlFile = $path . "/pkg_" . $this->getExtensionName() . ".xml";

		$this->taskReplaceInFile($xmlFile)
			->from('##FILES##')
			->to($f)
			->run();

		$this->_symlink($path, $this->current);

		$this->say("Packaging Package " . $this->getExtensionName());

		// Package file
		$zip = new Zip($this->target);
		$zip->run();
	}
}
