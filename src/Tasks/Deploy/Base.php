<?php
/**
 * @package     JoRobo
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Jorobo\Tasks\Deploy;

use Robo\Result;
use Robo\Task\BaseTask;
use Robo\Contract\TaskInterface;
use Robo\Exception\TaskException;

use Joomla\Jorobo\Tasks\JTask;

/**
 * Deployment base - contains methods / data used in multiple build tasks
 */
class Base extends JTask implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;

	/**
	 * Base constructor.
	 */
	public function __construct()
	{
		parent::__construct();
	}

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
	 * Build the package
	 *
	 * @param   string $target Target
	 *
	 * @return  bool
	 */
	public function createZip($target = '')
	{

		if (empty($target))
		{
			$target = JPATH_BASE . "/dist/" . $this->getExtensionName() . "-" . $this->getConfig()->version . ".zip";
		}

		$zip = new \ZipArchive($target, \ZipArchive::CREATE);
		$this->say('Zipping ' . $this->getConfig()->extension . " " . $this->getConfig()->version);

		// Instantiate the zip archive
		$zip->open($target, \ZipArchive::CREATE);

		//Current Extension Path
		$current = JPATH_BASE . "/dist/current";

		// Process the files to zip
		foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($current), \RecursiveIteratorIterator::SELF_FIRST) as $subfolder)
		{
			if ($subfolder->isFile())
			{
				// Set all separators to forward slashes for comparison
				$usefolder = str_replace('\\', '/', $subfolder->getPath());

				// Drop the folder part as we don't want them added to archive
				$addpath = str_ireplace($current, '', $usefolder);

				// Remove preceding slash
				$findfirst = strpos($addpath, '/');

				if ($findfirst == 0 && $findfirst !== false)
				{
					$addpath = substr($addpath, 1);
				}

				if (strlen($addpath) > 0 || empty($addpath))
				{
					$addpath .= '/';
				}

				$options = array('add_path' => $addpath, 'remove_all_path' => true);
				$zip->addGlob($usefolder . '/*.*', GLOB_BRACE, $options);
			}
		}

		// Close the zip archive
		$zip->close();

		return true;
	}
}
