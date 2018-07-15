<?php
/**
 * @package     JoRobo
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Jorobo\Tasks;

use Robo\Contract\TaskInterface;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

/**
 * Class JTask - Base class for our tasks
 *
 * @package  Joomla\Jorobo\Tasks
 */
abstract class JTask extends \Robo\Tasks implements TaskInterface
{
	/**
	 * The config object
	 *
	 * @var    array|null
	 */
	protected static $config = null;

	/**
	 * Operating system
	 *
	 * @var    string
	 */
	protected $os = '';

	/**
	 * The file extension (OS Support)
	 *
	 * @var    string
	 */
	protected $fileExtension = '';

	/**
	 * The source folder
	 *
	 * @var    string
	 */
	protected $sourceFolder = '';

	protected $hasComponent = true;

	protected $hasModules = true;

	protected $hasPackage = true;

	protected $hasPlugins = true;

	protected $hasLibraries = true;

	protected $hasCBPlugins = true;

	protected $hasTemplates = true;

	/**
	 * Construct
	 *
	 * @param   array $params Opt params
	 */
	public function __construct($params = array())
	{
		$this->loadConfiguration($params);
		$this->determineOperatingSystem();
		$this->determineSourceFolder();
		$this->analyze();
	}

	/**
	 * Load config
	 *
	 * @param   array $params Optional Params
	 *
	 * @return  bool
	 */
	private function loadConfiguration($params)
	{
		if (!is_null(self::$config))
		{
			return true;
		}

		// Load config as object
		$config = json_decode(json_encode(parse_ini_file(JPATH_BASE . '/jorobo.ini', true)), false);

		if (!$config)
		{
			$this->say('Error: Config file jorobo.ini not available');

			throw new FileNotFoundException('Config file jorobo.ini not available');
		}

		// Are we building a git / dev release?
		if ($this->isDevelopmentVersion($params))
		{
			$res = $this->_exec('git rev-parse --short HEAD');

			$version = "git" . trim($res->getMessage());

			if ($version)
			{
				$this->say("Changing version to development version " . $version);
				$config->version = $version;
			}
		}

		$config->buildFolder = JPATH_BASE . $this->determineTarget($config);
		$config->params      = $params;

		self::$config = $config;

		// Date set
		date_default_timezone_set('UTC');
	}

	/**
	 * Check if we are building a dev release
	 *
	 * @param   array $params - Robo.li Params
	 *
	 * @return  mixed
	 */
	private function isDevelopmentVersion($params)
	{
		return isset($params['dev']) ? $params['dev'] : false;
	}

	/**
	 * Get target
	 *
	 * @param   object $config - The JoRobo config
	 *
	 * @return  string
	 */
	private function determineTarget($config)
	{
		$target = "/dist/";

		if (!isset($config->extension))
		{
			$target .= 'unnamed';
		}
		
		$target .= $config->extension;

		if (!empty($config->version))
		{
			$target .= "-" . $config->version;
		}

		return $target;
	}

	/**
	 * Sets the operating system
	 */
	private function determineOperatingSystem()
	{
		$this->os = strtoupper(substr(PHP_OS, 0, 3));

		if ($this->os === 'WIN')
		{
			$this->fileExtension = '.exe';
		}
	}

	/**
	 * Sets the source folder
	 */
	private function determineSourceFolder()
	{
		$this->sourceFolder = JPATH_BASE . "/" . $this->getConfig()->source;

		if (!is_dir($this->sourceFolder))
		{
			$this->say('Warning - Directory: ' . $this->sourceFolder . ' is not available');
		}
	}

	/**
	 * Get the build config
	 *
	 * @return  object
	 */
	public function getConfig()
	{
		return self::$config;
	}

	/**
	 * Function to check if folders are existing / writable (Code Base etc.)
	 *
	 * @return  bool
	 */
	public function checkFolders()
	{
		$return    = true;
		$dirHandle = opendir($this->getSourceFolder());

		if ($dirHandle === false)
		{
			$this->printTaskError('Can not open ' . $this->getSourceFolder() . ' for parsing');

			$return = false;
		}

		closedir($dirHandle);

		return $return;
	}

	/**
	 * Get the source folder path
	 *
	 * @return  string  absolute path
	 */
	public function getSourceFolder()
	{
		return $this->sourceFolder;
	}

	/**
	 * Get the operating system
	 *
	 * @return string
	 */
	public function getOs()
	{
		return $this->os;
	}

	/**
	 * Get the extension name
	 *
	 * @return   string
	 */
	public function getExtensionName()
	{
		return strtolower($this->getConfig()->extension);
	}

	/**
	 * Get the destination / build folder
	 *
	 * @return   string
	 */
	public function getBuildFolder()
	{
		return $this->getConfig()->buildFolder;
	}

	/**
	 * Analyze the extension structure
	 *
	 * @return  void
	 */
	private function analyze()
	{
		// Check if we have component, module, plugin etc.
		if (!file_exists($this->getSourceFolder() . "/administrator/components/com_" . $this->getExtensionName())
			&& !file_exists($this->getSourceFolder() . "/components/com_" . $this->getExtensionName())
		)
		{
			$this->say("Extension has no component");
			$this->hasComponent = false;
		}

		if (!file_exists($this->getSourceFolder() . "/modules"))
		{
			$this->hasModules = false;
		}

		if (!file_exists($this->getSourceFolder() . "/plugins"))
		{
			$this->hasPlugins = false;
		}

		if (!file_exists($this->getSourceFolder() . "/templates"))
		{
			$this->hasTemplates = false;
		}

		if (!file_exists($this->getSourceFolder() . "/libraries"))
		{
			$this->hasLibraries = false;
		}

		if (!file_exists($this->getSourceFolder() . "/administrator/manifests/packages"))
		{
			$this->hasPackage = false;
		}

		if (!file_exists($this->getSourceFolder() . "/components/com_comprofiler"))
		{
			$this->hasCBPlugins = false;
		}
	}

	/**
	 * @param array|null $config
	 */
	public static function setConfigZipFile($zipfile)
	{
		self::$config->zipfile = $zipfile;
	}

	/**
	 * Create Symlinks based on operating System
	 *
	 * @param  string  $from  Sourcepath
	 * @param  string  $to    Targetpath
	 *
	 * @return   void
	 */
	public function createSymlink($from, $to)
	{
		if ($this->isWindows())
		{
			$this->_exec('mklink /J ' . $to . $this->getWindowsPath($from));
		}
		else
		{
			$this->_symlink($from, $to);
		}
	}

	/**
	 * Check if local OS is Windows
	 *
	 * @return  bool
	 *
	 * @since   3.7.3
	 */
	private function isWindows()
	{
		return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
	}

	/**
	 * Return the correct path for Windows (needed by CMD)
	 *
	 * @param   string $path Linux path
	 *
	 * @return  string
	 *
	 * @since   3.7.3
	 */
	private function getWindowsPath($path)
	{
		return str_replace('/', DIRECTORY_SEPARATOR, $path);
	}
}
