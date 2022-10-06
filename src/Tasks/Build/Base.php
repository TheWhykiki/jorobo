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
 * Build base - contains methods / data used in multiple build tasks
 *
 * @package  Joomla\Jorobo\Tasks\Build
 */
class Base extends JTask implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;

	/**
	 * Media files
	 *
	 * They need to be static in order to support multiple files and LANGUAGE
	 *
	 * @var  array
	 */
	protected static $mediaFiles = array();

	protected static $frontendFiles = array();

	protected static $backendFiles = array();

	protected static $frontendLanguageFiles = array();

	protected static $backendLanguageFiles = array();

	protected $resultFiles = array();

	/**
	 * Returns true
	 *
	 * @return  bool
	 */
	public function run()
	{
		return true;
	}

	/**
	 * Add files to array
	 *
	 * @param   string  $type       - Type (media, component etc.)
	 * @param   array   $fileArray  - File array
	 *
	 * @return bool
	 */
	public function addFiles($type, $fileArray)
	{
		$method = 'add' . ucfirst($type) . "Files";

		if (method_exists($this, $method))
		{
			$this->$method($fileArray);
		}
		else
		{
			$this->say('Missing method: ' . $method);
		}

		return true;
	}

	/**
	 * Retrieve the files
	 *
	 * @param   string  $type  Type (media, component etc.)
	 *
	 * @return mixed
	 */
	public function  getFiles($type)
	{
		$f = $type . 'Files';

		if (property_exists($this, $f))
		{
			return self::${$f};
		}

		$this->say('Missing Files: ' . $type);

		return "";
	}


	/**
	 * Adds Files / Folders to media array
	 *
	 * @param   array  $fileArray  Array of files / folders
	 *
	 * @return  void
	 */
	public function addMediaFiles($fileArray)
	{
		self::$mediaFiles = array_merge(self::$mediaFiles, $fileArray);
	}

	/**
	 * Adds Files / Folders to media array
	 *
	 * @param   array  $fileArray  Array of files / folders
	 *
	 * @return  void
	 */
	public function addFrontendFiles($fileArray)
	{
		self::$frontendFiles = array_merge(self::$frontendFiles, $fileArray);
	}

	/**
	 * Adds Files / Folders to media array
	 *
	 * @param   array  $fileArray  Array of files / folders
	 *
	 * @return  void
	 */
	public function addBackendFiles($fileArray)
	{
		self::$backendFiles = array_merge(self::$backendFiles, $fileArray);
	}

	/**
	 * Adds Files / Folders to language array
	 *
	 * @param   array  $fileArray  Array of files / folders
	 *
	 * @return  void
	 */
	public function addFrontendLanguageFiles($fileArray)
	{
		self::$frontendLanguageFiles = array_merge(self::$frontendLanguageFiles, $fileArray);
	}

	/**
	 * Adds Files / Folders to language array
	 *
	 * @param   array  $fileArray  Array of files / folders
	 *
	 * @return  void
	 */
	public function addBackendLanguageFiles($fileArray)
	{
		self::$backendLanguageFiles = array_merge(self::$backendLanguageFiles, $fileArray);
	}

	/**
	 * Copies the files and maps them into an array
	 *
	 * @param   string  $path  - Folder path
	 * @param   string  $tar   - Target path
	 *
	 * @return array
	 */
	protected function copyTarget($path, $tar)
	{
		$map = array();
		$hdl = opendir($path);

		while ($entry = readdir($hdl))
		{
			// Ignore hidden files
			if (substr($entry, 0, 1) == '.'
				|| (isset($this->getConfig()->exclude)
					&& in_array($entry, explode(',', $this->getConfig()->exclude))))
			{
				continue;
			}

			$p = $path . "/" . $entry;

			if (is_file($p))
			{
                $map[]  = array("file" => $entry);
                $target = $tar . "/" . $entry;
                $this->_copy($p, $target);

                if (pathinfo($entry, PATHINFO_EXTENSION) != 'ini')
                {
                    // Version & Date Replace
                    $this->replaceInFile($target);
                }
            }
			else
			{
				$map[] = array("folder" => $entry);
				$this->_copyDir($p, $tar . "/" . $entry);
			}
		}

		closedir($hdl);

		return $map;
	}

	/**
	 * Get the result files
	 *
	 * @return  array
	 */
	public function getResultFiles()
	{
		return $this->resultFiles;
	}

	/**
	 * Set the result files
	 *
	 * @param   array  $resultFiles  - The result of the copying
	 *
	 * @return  void
	 */
	public function setResultFiles($resultFiles)
	{
		$this->resultFiles = $resultFiles;
	}

	/**
	 * Get the current date (formated for building)
	 *
	 * @return  string
	 */
	public function getDate()
	{
		return date('Y-m-d');
	}

	/**
	 * Generate a list of files
	 *
	 * @param   array   $files    Files and Folders array
	 * @param   string  $ext      Extension Name
	 * @param   string  $extType  Extension Type
	 *
	 * @return  string
	 */
	public function generateFileList($files, $ext = "", $extType = "")
	{
		if (!count($files))
		{
			return "";
		}

		$text = array();

		foreach ($files as $f)
		{
			foreach ($f as $type => $value)
			{
				$p = "";

				if ($value == $ext . ".php")
				{
					$p = ' ' . $extType . '="' . $ext . '"';
				}

				$text[] = "<" . $type . $p . ">" . $value . "</" . $type . ">";
			}
		}

		return implode("\n", $text);
	}


	/**
	 * Generate a list of files
	 *
	 * @param   array  $files  Files and Folders array
	 *
	 * @return  string
	 */
	public function generateLanguageFileList($files)
	{
		if (!count($files))
		{
			return "";
		}

		$text = array();

		foreach ($files as $f)
		{
			foreach ($f as $tag => $value)
			{
				$text[] = '<language tag="' . $tag . '">' . $tag . "/" . $value . "</language>";
			}
		}

		return implode("\n", $text);
	}

	/**
	 * Generate a list of files for plugins
	 *
	 * @param   array   $files   Files and Folders array
	 * @param   string  $plugin  The plugin file
	 *
	 * @return  string
	 */
	public function generatePluginFileList($files, $plugin)
	{
		if (!count($files))
		{
			return "";
		}

		return $this->generateFileList($files, $plugin, "plugin");
	}

	/**
	 * Generate a list of files for modules
	 *
	 * @param   array   $files   Files and Folders array
	 * @param   string  $module  The module
	 *
	 * @return  string
	 */
	public function generateModuleFileList($files, $module)
	{
		if (!count($files))
		{
			return "";
		}

		return $this->generateFileList($files, $module, "module");
	}

	/**
	 * Reset the files list, before build another part
	 *
	 * @return  void
	 */
	public function resetFiles()
	{
		self::$backendFiles = array();
		self::$backendLanguageFiles = array();
		self::$frontendFiles = array();
		self::$frontendLanguageFiles = array();
		self::$mediaFiles = array();
	}


	/**
	 * Replace Basic placeholders in file (Date, year, version)
	 *
	 * @param   string  $file  - Path to file
	 *
	 * @return  void
	 */
	protected function replaceInFile($file)
	{
		if (!file_exists($file))
		{
			return;
		}

		$this->taskReplaceInFile($file)
			->from(array('##DATE##', '##YEAR##', '##VERSION##'))
			->to(array($this->getDate(), date('Y'), $this->getConfig()->version))
			->run();
	}
}
