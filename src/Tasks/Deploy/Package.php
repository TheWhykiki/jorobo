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
			$this->createComponentZip();
		}

		if ($this->hasModules)
		{
			$this->createExtensionZips("module");
		}

		if ($this->hasPlugins)
		{
			$this->createExtensionZips("plugin");
		}

		if ($this->hasTemplates)
		{
			$this->createExtensionZips("template");
		}

		$this->createPackageZip();

		//$this->_symlink($this->target, JPATH_BASE . "/dist/pkg-" . $this->getExtensionName() . "-current.zip");

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

		if (!file_exists($this->getBuildFolder() . "/module"))
		{
			$this->hasModules = false;
		}

		if (!file_exists($this->getBuildFolder() . "/plugin"))
		{
			$this->hasPlugins = false;
		}

		if (!file_exists($this->getBuildFolder() . "/template"))
		{
			$this->hasTemplates = false;
		}

		if (!file_exists($this->getBuildFolder() . "/library"))
		{
			$this->hasLibraries = false;
		}

		if (!file_exists($this->getBuildFolder() . "/components/com_comprofiler"))
		{
			$this->hasCBPlugins = false;
		}
	}

	/**
	 * Create a installable zip file for a component
	 *
	 * @TODO implement possibility for multiple components (without duplicate content)
	 *
	 * @return  void
	 */
	public function createComponentZip()
	{
		$comZip = new \ZipArchive(JPATH_BASE . "/dist", \ZipArchive::CREATE);

		if (file_exists(JPATH_BASE . '/dist/tmp/cbuild'))
		{
			$this->_deleteDir(JPATH_BASE . '/dist/tmp/cbuild');
		}

		// Improve, should been a whitelist instead of a hardcoded copy
		$this->_mkdir(JPATH_BASE . '/dist/tmp/cbuild');

		$this->_copyDir($this->current . '/administrator', JPATH_BASE . '/dist/tmp/cbuild/administrator');
		$this->_remove(JPATH_BASE . '/dist/tmp/cbuild/administrator/manifests');
		$this->_copyDir($this->current . '/language', JPATH_BASE . '/dist/tmp/cbuild/language');
		$this->_copyDir($this->current . '/components', JPATH_BASE . '/dist/tmp/cbuild/components');

		$comZip->open(JPATH_BASE . '/dist/zips/com_' . $this->getExtensionName() . '.zip', \ZipArchive::CREATE);

		// Process the files to zip
		$this->addFiles($comZip, JPATH_BASE . '/dist/tmp/cbuild');

		$comZip->addFile($this->current . "/" . $this->getExtensionName() . ".xml", $this->getExtensionName() . ".xml");
		$comZip->addFile($this->current . "/administrator/components/com_" . $this->getExtensionName() . "/script.php", "script.php");

		// Close the zip archive
		$comZip->close();
	}

	/**
	 * Add files
	 *
	 * @param    \ZipArchive $zip  The zip object
	 * @param    string      $path Optional path
	 *
	 * @return  void
	 */
	private function addFiles($zip, $path = null)
	{
		if (!$path)
		{
			$path = $this->current;
		}

		$source = str_replace('\\', '/', realpath($path));

		if (is_dir($source) === true)
		{
			$files = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($source), \RecursiveIteratorIterator::SELF_FIRST
			);

			foreach ($files as $file)
			{
				$file = str_replace('\\', '/', $file);

				if (substr($file, 0, 1) == ".")
				{
					continue;
				}

				// Ignore "." and ".." folders
				if (in_array(substr($file, strrpos($file, '/') + 1), array('.', '..')))
				{
					continue;
				}

				$file = str_replace('\\', '/', $file);

				if (is_dir($file) === true)
				{
					$zip->addEmptyDir(str_replace($source . '/', '', $file . '/'));
				}
				else if (is_file($file) === true)
				{
					$zip->addFromString(str_replace($source . '/', '', $file), file_get_contents($file));
				}
			}
		}
		else if (is_file($source) === true)
		{
			$zip->addFromString(basename($source), file_get_contents($source));
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
		$pTarget = $path . "/packages";

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
