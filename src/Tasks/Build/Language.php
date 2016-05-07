<?php
/**
 * @package     JoRobo
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\Jorobo\Tasks\Build;

use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Contract\TaskInterface;
use Robo\Exception\TaskException;

use Joomla\Jorobo\Tasks\JTask;

/**
 * Class Language
 *
 * @package  Joomla\Jorobo\Tasks\Build
 */
class Language extends Base implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;

	protected $ext = null;

	protected $type = "com";

	protected $target = null;

	protected $adminLangPath = null;

	protected $frontLangPath = null;

	protected $hasAdminLang = true;

	protected $hasFrontLang = true;


	/**
	 * Initialize Build Task
	 *
	 * @param   String  $extension  The extension (component, module etc.)
	 */
	public function __construct($extension)
	{
		parent::__construct();

		$this->adminLangPath = $this->getSourceFolder() . "/administrator/language";
		$this->frontLangPath = $this->getSourceFolder() . "/language";

		$this->ext = $extension;

		$this->type = substr($extension, 0, 3);
	}

	/**
	 * Returns true
	 *
	 * @return  bool
	 */
	public function run()
	{
		if ($this->type != "plu")
		{
			$this->analyze();
		}

		if (!$this->hasAdminLang && !$this->hasFrontLang)
		{
			// No Language files
			return true;
		}

		$this->say("Building language for " . $this->ext . " | Type " . $this->type);

		// Make sure we have the language folders in our target
		$this->prepareDirectories();

		$target = $this->getBuildFolder();

		if ($this->type == "mod")
		{
			$target .= "/modules/" . $this->ext;
		}
		elseif ($this->type == "plg")
		{
			//$a = explode("_", $this->ext);

			$target .= "/plugins/" . $this->ext;
		}
		elseif ($this->type == "pkg")
		{
			$target .= "/" . $this->ext;
		}
		elseif ($this->type == "lib")
		{
			$target .= "/libraries/" . $this->ext;
		}
		elseif ($this->type == "plu")
		{
			$a = explode("_", $this->ext);

			$this->say("plug: " . $this->ext);

			$this->say("/components/com_comprofiler/plugin/" . $a[1] . "/plug_" . $a[3]);

			$target .= "/components/com_comprofiler/plugin/" . $a[1] . "/plug_" . $a[3];

			$this->ext = "plg_plug_" . $a[3];

			$this->hasFrontLang = false;
		}
		elseif ($this->type == "tpl")
		{
			$target .= "/templates/" . $this->ext;
		}

		if ($this->hasAdminLang)
		{
			$comTarget = '';

			if ($this->type == "com")
			{
				$comTarget = "/components/" . $this->ext . "/administrator";
			}


			$map = $this->copyLanguage("administrator/language", $target . $comTarget);
			$this->addFiles('backendLanguage', $map);
		}
		if ($this->hasFrontLang)
		{
			$comTarget = '';

			if ($this->type == "com")
			{
				$comTarget = "/components/" . $this->ext;
			}

			$map = $this->copyLanguage("language", $target . $comTarget);
			$this->addFiles('frontendLanguage', $map);
		}

		return true;
	}

	/**
	 * Analyze the extension structure
	 *
	 * @return  void
	 */
	private function analyze()
	{
		// Just check for english here
		if (!file_exists($this->adminLangPath . "/en-GB/en-GB." . $this->ext . ".ini") && !file_exists($this->adminLangPath . "/en-GB/en-GB." . $this->ext . ".sys.ini"))
		{
			$this->hasAdminLang = false;
		}

		if (!file_exists($this->frontLangPath . "/en-GB/en-GB." . $this->ext . ".ini") && !file_exists($this->frontLangPath . "/en-GB/en-GB." . $this->ext . ".sys.ini"))
		{
			$this->hasFrontLang = false;
		}
	}

	/**
	 * Prepare the directory structure
	 *
	 * @return  boolean
	 */
	private function prepareDirectories()
	{
		if ($this->type == "com")
		{
			if ($this->hasAdminLang)
			{
				$this->_mkdir($this->getBuildFolder()
					. "/components/" . $this->ext . "/administrator/language");
			}

			if ($this->hasFrontLang)
			{
				$this->_mkdir($this->getBuildFolder() . "/components/" . $this->ext . "/language");
			}
		}

		if ($this->type == "mod")
		{
			$this->_mkdir($this->getBuildFolder() . "/modules/" . $this->ext . "/language");
		}

		if ($this->type == "plg")
		{
			//$a = explode("_", $this->ext);

			$this->_mkdir($this->getBuildFolder() . "/plugins/" . $this->ext . "/language");
		}

		if ($this->type == "plug")
		{
			$a = explode("_", $this->ext);

			$this->_mkdir($this->getBuildFolder() . "/components/com_comprofiler/plugin/" . $a[1] . "/" . $this->ext . "/administrator/language");
		}

		return true;
	}

	/**
	 * Copy language files
	 *
	 * @param   string  $source  The source directory (administrator/language or language or mod_xy/language etc)
	 * @param   String  $target  The target directory
	 *
	 * @return   array
	 */
	public function copyLanguage($source, $target)
	{
		// Equals administrator/language or language
		$path = $this->getSourceFolder() . "/" . $source;

		$files = array();

		$hdl = opendir($path);

		while ($entry = readdir($hdl))
		{
			// Ignore hidden files
			if (substr($entry, 0, 1) == '.')
			{
				continue;
			}
			
			$p = $path . "/" . $entry;

			// Which languages do we have
			// Language folders
			if (!is_file($p))
			{
				// Make folder at destination
				$this->_mkdir($target . "/language/" . $entry);

				$fileHdl = opendir($p);

				while ($file = readdir($fileHdl))
				{
					// Only copy language files for this extension (and sys files..)
					if (substr($file, 0, 1) != '.' && strpos($file, $this->ext . "."))
					{
						$files[] = array($entry => $file);

						// Copy file
						$this->_copy($p . "/" . $file, $target . "/language/" . $entry . "/" . $file);
					}
				}

				closedir($fileHdl);
			}
		}

		closedir($hdl);

		return $files;
	}
}
