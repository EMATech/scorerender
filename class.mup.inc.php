<?php
/*
 ScoreRender - Renders inline music score fragments in WordPress
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

/**
 * Implements rendering of Mup notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Mup notation.
 * @package ScoreRender
*/
class mupRender extends ScoreRender
{
	/**
	 * @var string
	 */
	var $_uniqueID = "mup";

	/**
	 * Class constructor
	 * @param array $options Options to be passed into class
	 * @access private
	 */
	function mupRender ($options = array())
	{
		$this->init_options ($options);

		$this->_options['IMAGE_MAX_WIDTH'] /= DPI;
	}

	/**
	 * Checks if given content is invalid or dangerous content
	 *
	 * @param string $input
	 * @return boolean True if content is deemed safe
	 */
	function isValidInput ($input)
	{
		$blacklist = array
		(
			'/^\s*\binclude\b/', '/^\s*\bfontfile\b/'
		);

		foreach ($blacklist as $pattern)
			if (preg_match ($pattern, $input))
				return false;

		return true;
	}

	/**
	 * Outputs complete music input file for rendering.
	 *
	 * Most usually user supplied content does not contain correct
	 * rendering options like page margin, staff width etc, and
	 * each notation has its own requirements. This method adds
	 * such necessary content to original content for processing.
	 *
	 * @return string The full music content to be rendered
	 */
	function getInputFileContents ()
	{
		$header = <<<EOD
//!Mup-Arkkra-5.0
score
leftmargin = 0
rightmargin = 0
topmargin = 0
bottommargin = 0
pagewidth = {$this->_options['IMAGE_MAX_WIDTH']}
label = ""
EOD;
		return $header . "\n" . $this->_input;
	}

	/**
	 * Execute the real command for first time rendering
	 *
	 * The command reads input content (after prepending and appending
	 * necessary stuff to user supplied content), and converts it to
	 * a PostScript file.
	 *
	 * @uses ScoreRender::_exec
	 * @param string $input_file File name of raw input file containing music content
	 * @param string $rendered_image File name of rendered PostScript file
	 * @return boolean Whether rendering is successful or not
	 */
	function execute ($input_file, $rendered_image)
	{
		/* Mup requires a file ".mup" present in $HOME or
		   current working directory. It must be present even if
		   not registered, otherwise mup refuse to render anything.
		   Even worse, the exist status in this case is 0, so
		   _exec succeeds yet no postscript is rendered. */

		$temp_magic_file = $this->_options['TEMP_DIR'] . DIRECTORY_SEPARATOR . '.mup';
		if (!file_exists($temp_magic_file))
		{
			if (is_readable($this->_options['MUP_MAGIC_FILE']))
				copy($this->_options['MUP_MAGIC_FILE'], $temp_magic_file);
			else
				touch ($temp_magic_file);
		}

		/* mup forces this kind of crap */
		putenv ("HOME=" . $this->_options['TEMP_DIR']);

		$cmd = sprintf ('%s -f %s %s 2>&1',
		                $this->_options['MUP_BIN'],
		                $rendered_image, $input_file);
		$retval = $this->_exec($cmd);

		unlink ($temp_magic_file);

		return (filesize ($rendered_image) != 0);
		//return ($result['return_val'] == 0);
	}

	/**
	 * @uses ScoreRender::_exec
	 * @param string $rendered_image The rendered PostScript file name
	 * @param string $cache_filename The final PNG image file name
	 * @param boolean $invert True if image should be white on black instead of vice versa
	 * @param boolean $transparent True if image background should be transparent
	 * @return boolean Whether conversion from PostScript to PNG is successful
	 */
	function convertimg ($rendered_image, $cache_filename, $invert, $transparent)
	{
		/*
		 * Mup output is Grayscale by default. When attempting to add
		 * transparency, it can only have value 0 or 1; that means notes,
		 * slurs and letters won't have smooth outline. Converting to
		 * RGB colorspace seems to fix the problem, but can't have all
		 * options in one single pass.
		 */
		$cmd = $this->_options['CONVERT_BIN'] . ' -trim +repage ';

		if (!$transparent)
		{
			$cmd .= (($invert) ? '-negate ' : ' ')
			        . $rendered_image . ' ' . $cache_filename;
		}
		else
		{
			// Really need to execute convert twice this time
			$cmd .= $rendered_image . ' png:- | ' .
				$this->_options['CONVERT_BIN'] .
				' -channel ' . (($invert)? 'rgba' : 'alpha')
			        . ' -fx "1-intensity" png:- ' . $cache_filename;
		}

		$retval = $this->_exec($cmd);

		return ($retval == 0);
	}
}

?>
