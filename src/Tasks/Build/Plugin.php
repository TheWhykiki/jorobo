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
 * Class Plugin
 *
 * @package  Joomla\Jorobo\Tasks\Build
 */
class Plugin extends Base implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;
	use buildTasks;

	protected $plgName = null;

	protected $plgType = null;

	protected $source = null;

	protected $target = null;

	/**
	 * Initialize Build Task
	 *
	 * @param   String  $type    Type of the plugin
	 * @param   String  $name    Name of the plugin
	 * @param   String  $params  Optional params
	 */
	public function __construct($type, $name, $params)
	{
		parent::__construct();

		// Reset files - > new module
		$this->resetFiles();

		$this->plgName = $name;
		$this->plgType = $type;

		$this->source = $this->getSourceFolder() . "/plugins/" . $type . "/" . $name;
		$this->target = $this->getBuildFolder() . "/plugins/plg_" . $type . "_" . $name;
	}

	/**
	 * Build the package
	 *
	 * @return  bool
	 */
	public function run()
	{
		$plgName = "plg_" . $this->plgType . "_" . $this->plgName;
		$this->say('Building plugin: ' . $this->plgName . " (" . $this->plgType . ")");

		// Prepare directories
		$this->prepareDirectories();

		$files = $this->copyTarget($this->source, $this->target);

		// Build media (relative path)
		$media = $this->buildMedia("media/" . $plgName, $plgName, "plugin");
		$media->type = 'plugins';
		$media->run();

		$this->addFiles('media', $media->getResultFiles());

		// Build language files
		$language = $this->buildLanguage($plgName);
		$language->run();

		// Update XML and script.php
		$this->createInstaller($files);

		// Create symlink to current folder
		$this->_symlink($this->target, JPATH_BASE . "/dist/current");


		return true;
	}


	/**
	 * Prepare the directory structure
	 *
	 * @return  void
	 */
	private function prepareDirectories()
	{
		$this->_mkdir($this->target);
	}

	/**
	 * Generate the installer xml file for the plugin
	 *
	 * @param   array  $files  The module files
	 *
	 * @return  void
	 */
	private function createInstaller($files)
	{
		$this->say("Creating plugin installer");

		$xmlFile = $this->target . "/" . $this->plgName . ".xml";

		// Version & Date Replace
		$this->replaceInFile($xmlFile);

		// Files and folders
		$f = $this->generatePluginFileList($files, $this->plgName);

		$this->taskReplaceInFile($xmlFile)
			->from('##FILES##')
			->to($f)
			->run();

		// Language files
		$f = $this->generateLanguageFileList($this->getFiles('backendLanguage'));

		$this->taskReplaceInFile($xmlFile)
			->from('##LANGUAGE_FILES##')
			->to($f)
			->run();

		// Media files
		$f = $this->generateFileList($this->getFiles('media'));

		$this->taskReplaceInFile($xmlFile)
			->from('##MEDIA_FILES##')
			->to($f)
			->run();
	}
}
