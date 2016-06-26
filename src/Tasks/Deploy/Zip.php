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
 * Deploy project as Zip
 */
class Zip extends Base implements TaskInterface
{
	use \Robo\Task\Development\loadTasks;
	use \Robo\Common\TaskIO;
	use Build\buildTasks;
	use deployTasks;

	protected $current = null;

	/**
	 * Build the package
	 *
	 * @return  bool
	 */
	public function run()
	{
		if ($this->hasPackage)
		{
			$this->deployPackage()->run();

			return true;
		}

		$type      = '';
		$params    = $this->getConfig()->params;
		$extension = $this->getExtensionName();
		$version   = $this->getConfig()->version;

		$this->say('Creating Extension ' . $extension . " " . $version);

		if ($this->hasComponent)
		{
			$ext = 'com';
			$this->buildExtension($params)->run();
		}
		else
		{
			if ($this->hasModules)
			{
				$ext = 'mod';
				$this->buildModule($extension, $params)->run();
			}

			if ($this->hasPlugins)
			{
				$ext  = 'plg';
				$path = $this->getSourceFolder() . "/plugins";

				// Get every plugin type
				$hdl = opendir($path);

				while ($entry = readdir($hdl))
				{
					// Ignore hidden files
					if (substr($entry, 0, 1) == '.')
					{
						continue;
					}

					if (!is_file($entry))
					{
						$type = '_' . $entry;
						$this->buildPlugin($entry, $extension, $params)->run();
					}
				}

				closedir($hdl);
			}

			if ($this->hasTemplates)
			{
				$this->buildTemplate($extension, $params)->run();
			}
		}

		$target = JPATH_BASE . "/dist/" . $ext . $type . "_" . $extension . "-" . $version . ".zip";

		// Package file
		if ($this->createZip($target))
		{
			$this->setConfigZipFile($ext . $type . "_" . $extension . "-" . $version . ".zip");
		}

		$this->_symlink($target, JPATH_BASE . "/dist/" . $ext . "-" . $extension . "-current.zip");

		return true;
	}
}