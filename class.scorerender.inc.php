<?php
/*
 ScoreRender - Renders inline music score fragments in WordPress
 Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 Copyright (C) 2007 Abel Cheung <abelcheung at gmail dot com>

 This program is free software; you can redistribute it and/or
 modify it under the terms of the GNU General Public License
 as published by the Free Software Foundation; either version 2
 of the License, or (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/


/*
 Mostly based on class.figurerender.inc.php from FigureRender
 Chris Lamb <chris@chris-lamb.co.uk>
 10th April 2006

 Follows the template method pattern. Subclasses should implement:
 - getInputFileContents($input)
 - execute($input_file, $rendered_image)
 optionally they can also implement:
 - isValidInput($input)
 - convertimg($rendered_image, $final_image, $invert, $transparent)
*/


class ScoreRender
{
	var $_options;
	var $_input;
	var $_uniqueID;
	var $_commandOutput;

	function init_options ($input, $options = array())
	{
		// fallback values
		$this->_options['FILE_FORMAT'] = 'png';

		$this->_options = array_merge ($this->_options, $options);

		$this->_input = $input;
	}

	function getCommandOutput()
	{
		return $this->_commandOutput;
	}

	function _exec($cmd)
	{
		$cmd_output = array();
		$retval = 0;

		exec ($cmd, $cmd_output, $retval);

		$this->_commandOutput = implode ("\n", $cmd_output);

		return $retval;
	}

	function convertimg ($rendered_image, $final_image, $invert, $transparent)
	{
		// Convert to specified format
		$cmd = $this->_options['CONVERT_BIN'] . ' -trim ';

		if (!$transparent)
		{
			$cmd .= (($invert) ? '-negate ' : '')
			        . $rendered_image . ' ' . $final_image;
		}
		else
		{
			if (!$invert)
			{
				$cmd .= '-channel alpha ' . $rendered_image . ' ' . $final_image;
			}
			else
			{
				// Is it possible to execute convert only once?
				$cmd .=	'-channel alpha ' .
					$rendered_image . ' png:- | ' .
					$this->_options['CONVERT_BIN'] .
					' -channel rgb -negate png:- ' .
					$final_image;
			}
		}

		$retval = ScoreRender::_exec ($cmd);

		return ($retval === 0);
	}

	function render()
	{
		// Check for valid code
		if (empty ($this->_input) ||
		    (method_exists ($this, 'isValidInput') &&
		     !$this->isValidInput($this->_input)))
			return ERR_INVALID_INPUT;

		// Create unique hash
		$hash = md5 ($this->_input . $this->_options['INVERT_IMAGE']
			     . $this->_options['TRANSPARENT_IMAGE'] . $this->_uniqueID);
		$final_image = $this->_options['CACHE_DIR'] . DIRECTORY_SEPARATOR
		                  . $hash . '.' . $this->_options['FILE_FORMAT'];

		if (!is_file ($final_image))
		{
			// Check cache directory
			if ( (!isset ($this->_options['CACHE_DIR'])) ||
			     (!is_dir ($this->_options['CACHE_DIR'])) ||
			     (!is_writable ($this->_options['CACHE_DIR'])) )
			{
				return ERR_CACHE_DIRECTORY_NOT_WRITABLE;
			}

			// Check temp directory
			if ( (!isset($this->_options['TEMP_DIR'])) ||
			     (!is_dir($this->_options['TEMP_DIR'])) ||
			     (!is_writable($this->_options['TEMP_DIR'])) )
			{
				return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
			}

			if (($input_file = tempnam($this->_options['TEMP_DIR'],
				'fr-' . $this->_uniqueID . '-')) === false)
			{
				return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
			}
			$rendered_image = $input_file . '.ps';

			// Create empty output file first ASAP
			if (! file_exists ($rendered_image))
				touch ($rendered_image);

			if (! is_writable ($rendered_image))
				return ERR_TEMP_FILE_NOT_WRITABLE;

			// Write input file contents
			if (($handle = fopen ($input_file, 'w')) === false)
				return ERR_TEMP_FILE_NOT_WRITABLE;

			fwrite ($handle, $this->getInputFileContents($this->_input));
			fclose ($handle);


			// Render using external application
			$current_dir = getcwd();
			chdir ($this->_options['TEMP_DIR']);
			if (!$this->execute($input_file, $rendered_image) ||
			    !file_exists ($rendered_image))
			{
				//unlink($input_file);
				return ERR_RENDERING_ERROR;
			}
			chdir ($current_dir);

			if (!$this->convertimg ($rendered_image, $final_image,
			                        $this->_options['INVERT_IMAGE'],
			                        $this->_options['TRANSPARENT_IMAGE']))
				return ERR_IMAGE_CONVERT_FAILURE;

			// Cleanup
			unlink ($rendered_image);
			unlink ($input_file);

		}

		return basename ($final_image);
	}
}

?>
