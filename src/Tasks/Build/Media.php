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
 * Class Media
 *
 * @package  Joomla\Jorobo\Tasks\Build
 */
class Media extends Base implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;

	public $type = null;

	protected $source = null;

	protected $target = null;

	protected $fileMap = null;

	protected $extName = null;

	/**
	 * Initialize Build Task
	 *
	 * @param   String  $folder   The target directory
	 * @param   String  $extName  The extension name
	 */
	public function __construct($folder, $extName)
	{
		parent::__construct();

		$this->source = $this->getSourceFolder() . "/" . $folder;
		$this->extName = $extName;

		$this->target = $extName . "/" . $folder;
	}

	/**
	 * Runs the media build task
	 *
	 * @return  bool
	 */
	public function run()
	{
		$this->say("Building media folder " . $this->source . " for " . $this->extName);

		if (!file_exists($this->source))
		{
			$this->say("Folder " . $this->source . " does not exist!");

			return true;
		}

		$this->prepareDirectory();

		$target = $this->getBuildFolder() . "/" . $this->type . "/" . $this->target;

		$map = $this->copyTarget($this->source, $target);

		$this->setResultFiles($map);

		return true;
	}

	/**
	 * Prepare the directory structure
	 *
	 * @return  void
	 */
	private function prepareDirectory()
	{
		$target = $this->getBuildFolder() . "/" . $this->type . "/" . $this->target;
		$this->_mkdir($target);
	}
}
