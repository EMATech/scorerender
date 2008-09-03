<?php
/*
 ScoreRender - Renders inline music score fragments in WordPress
 Copyright (C) 2006 Chris Lamb <chris at chris-lamb dot co dot uk>
 Copyright (C) 2007, 08 Abel Cheung <abelcheung at gmail dot com>

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
 * Methods implementable by subclasses:
 *
 * Mandatory:
 * - execute($input_file, $rendered_image)
 * - get_music_fragment($input)
 * - convertimg($rendered_image, $final_image, $invert, $transparent)
 *   --- this one most usually invokes parent converting()
 *
 * Optional:
 * - is_valid_input()
 *
 * Please refer to class.*.inc.php for examples.
*/

/**
 * ScoreRender documentation
 * @package ScoreRender
 */

/**
 * ScoreRender Class
 * @package ScoreRender
 * @abstract
 */
abstract class ScoreRender
{

	/**
	 * @var array $_options ScoreRender config options.
	 * @access protected
	 * @todo drop this
	 */
	protected $_options;

	/**
	 * @var string $_input The music fragment to be rendered.
	 * @access protected
	 */
	protected $_input;

	/**
	 * @var string $_commandOutput Stores output message of rendering command.
	 * @access protected
	 */
	protected $_commandOutput;

	/**
	 * @var boolean $is_inverted Whether image should be rendered in white on black.
	 * @access protected
	 */
	protected $is_inverted = false;

	/**
	 * @var boolean $is_transparent Whether rendered image should use transparent background.
	 * @access protected
	 */
	protected $is_transparent = true;

	/**
	 * @var string $imagemagick Full path of ImageMagick convert
	 * @access protected
	 */
	protected $imagemagick;

	/**
	 * @var string $temp_dir Temporary working directory
	 * @access protected
	 */
	protected $temp_dir;

	/**
	 * @var string $cache_dir Folder for storing cached images
	 * @access protected
	 */
	protected $cache_dir;

	/**
	 * @var integer $content_max_length Maximum length of score fragment source (in bytes)
	 * @access protected
	 */
	protected $content_max_length = 4096;

	/**
	 * @var integer $img_max_width Maximum width of generated images
	 * @access protected
	 */
	protected $img_max_width = 360;

	/**
	 * @var string $mainprog Main program for rendering music into images
	 * @access protected
	 */
	protected $mainprog;

	/**
	 * Initialize ScoreRender options
	 *
	 * @param array $options Instances are initialized with this option array
	 * @access protected
	 */
	protected function init_options ($options = array())
	{
		// TODO: set all options outside class
		$this->_options = $options;
	}

	/**
	 * Sets music fragment content
	 *
	 * @since 0.2
	 * @uses $_input Stores music fragment content into this variable
	 * @param string $input The music fragment content
	 */
	public function set_music_fragment ($input)
	{
		$this->_input = $input;
	}

	/**
	 * Set the main program used to render music
	 *
	 * @param mixed $progs A single program or array of programs to be used
	 * @since 0.2.50
	 */
	public function set_programs ($progs)
	{
		if (is_string ($progs))
		{
			$this->mainprog = $progs;
			return;
		}
		elseif (!is_array ($progs) || empty ($progs))
			return;
		else
		{
			switch (count ($progs))
		       	{
			  case 1:
				$v = array_values ($progs);
				$this->mainprog = $v[0];
				break;
			  default:
				// Only picks the first element
				// TODO: set all others, like TeX, dvips,...
				list ($k) = array_keys ($progs);
				$this->mainprog = $progs[$k];
				break;
			}
		}
	}

	/**
	 * Outputs music fragment content
	 *
	 * Most usually user supplied content does not contain correct
	 * rendering options like page margin, staff width etc, and
	 * each notation has its own requirements. This method adds
	 * such necessary content to original content for processing.
	 *
	 * @uses $_input Return input content, optionally prepended/appended with header or footer, and filtered in other ways; $_input would not be modified
	 * @return string The full music content to be rendered
	 * @abstract
	 */
	abstract public function get_music_fragment ();

	/**
	 * Returns output message of rendering command.
	 *
	 * @uses $_commandOutput Returns this variable
	 * @return string
	 */
	public function get_command_output ()
	{
		return $this->_commandOutput;
	}

	/**
	 * Returns notation name, by comparing class name with notation name list.
	 *
	 * @return string Return notation name if found, FALSE otherwise.
	 * @since 0.2
	 */
	public function get_notation_name ()
	{
		global $notations;
		$classname = strtolower (get_class ($this));

		foreach ($notations as $notationname => $notation)
			if ($classname === strtolower ($notation['classname']))
				return $notationname;

		return false;
	}

	/**
	 * Sets path of ImageMagick convert
	 *
	 * @param string $path Full path of ImageMagick convert binary
	 * @since 0.2.50
	 */
	public function set_imagemagick_path ($path)
	{
		$this->imagemagick = $path;
	}

	/**
	 * Sets whether inverted image shall be generated
	 *
	 * @param boolean $invert
	 * @since 0.2.50
	 */
	public function set_inverted ($invert)
	{
		$this->is_inverted = $invert;
	}

	/**
	 * Sets whether transparent image shall be used
	 *
	 * @param boolean $transparent
	 * @since 0.2.50
	 */
	public function set_transparency ($transparent)
	{
		$this->is_transparent = $transparent;
	}

	/**
	 * Set temporary folder
	 *
	 * @param string $path
	 * @since 0.2.50
	 */
	public function set_temp_dir ($path)
	{
		$this->temp_dir = $path;
	}

	/**
	 * Get temporary folder path
	 *
	 * @return string The temporary folder if already set, or FALSE otherwise
	 * @since 0.2.50
	 */
	public function get_temp_dir ()
	{
		return (isset ($this->temp_dir)) ? $this->temp_dir : false;
	}

	/**
	 * Set cache folder for storing images
	 *
	 * @param string $path
	 * @since 0.2.50
	 */
	public function set_cache_dir ($path)
	{
		$this->cache_dir = $path;
	}

	/**
	 * Get cache folder path
	 *
	 * @return string The cache folder if already set, or FALSE otherwise
	 * @since 0.2.50
	 */
	public function get_cache_dir ()
	{
		return (isset ($this->cache_dir)) ? $this->cache_dir : false;
	}

	/**
	 * Set maximum allowed length of score fragment source
	 *
	 * @param integer $length Maximum length of score fragment source (in byte)
	 * @since 0.2.50
	 */
	public function set_max_length ($length)
	{
		$this->content_max_length = $length;
	}

	/**
	 * Set maximum width of generated images
	 *
	 * @param integer $width Maximum width of images (in pixel)
	 * @since 0.2.50
	 */
	public function set_img_width ($width)
	{
		$this->img_max_width = $width;
	}

	/**
	 * Executes command and stores output message
	 *
	 * {@internal It is basically exec() with additional stuff}
	 *
	 * @param string $cmd Command to be executed
	 * @uses $_commandOutput Command output is stored in this variable.
	 * @access protected
	 */
	protected function _exec ($cmd)
	{
		$cmd_output = array();
		$retval = 0;

		exec ($cmd, $cmd_output, $retval);

		$this->_commandOutput = implode ("\n", $cmd_output);

		return $retval;
	}

	/**
	 * Render raw input file into PostScript file.
	 *
	 * @param string $input_file File name of raw input file containing music content
	 * @param string $rendered_image File name of rendered PostScript file
	 * @abstract
	 */
	abstract protected function execute ($input_file, $rendered_image);

	/**
	 * Converts rendered PostScript page into PNG format.
	 *
	 * All rendering command would generate PostScript format as output.
	 * In order to show the image, it must be converted to PNG format first,
	 * and the process is done here, using ImageMagick. Various effects are also
	 * applied here, like white edge trimming, color inversion and alpha blending.
	 *
	 * @uses _exec
	 * @param string $rendered_image The rendered PostScript file name
	 * @param string $final_image The final PNG image file name
	 * @param boolean $invert True if image should be white on black instead of vice versa
	 * @param boolean $transparent True if image background should be transparent
	 * @param boolean $ps_has_alpha True if PostScript produced by music rendering program has transparency capability
	 * @param string $extra_arg Extra arguments supplied to ImageMagick convert
	 * @return boolean Whether conversion from PostScript to PNG is successful
	 * @access protected
	 */
	protected function convertimg ($rendered_image, $final_image, $ps_has_alpha = false, $extra_arg = '')
	{
		$cmd = sprintf ('%s %s -trim +repage ', $this->imagemagick, $extra_arg);

		// Damn it, older ImageMagick can't handle transparency in PostScript,
		// but suddenly it can now, and renders all previous logic broken
		if ($ps_has_alpha)
		{
			if ($this->is_transparent)
				$cmd .= sprintf (' %s %s %s',
					(($this->is_inverted) ? '-negate' : ''),
					$rendered_image, $final_image);
			else
				$cmd .= sprintf (' -flatten %s png:- | %s -alpha deactivate %s png:- %s',
					$rendered_image,
					$this->imagemagick,
					(($this->is_inverted) ? '-negate' : ''),
					$final_image);
		}
		else
		{
			if (!$this->is_transparent)
				$cmd .= sprintf (' %s %s %s',
					(($this->is_inverted) ? '-negate' : ''),
					$rendered_image, $final_image);
			else
			{
				// Adding alpha channel and changing alpha value
				// need separate invocations, can't do in one pass
				$cmd .= sprintf ('-alpha activate %s png:- | %s -channel alpha -fx "1-intensity" -channel rgb -fx %d png:- %s',
					$rendered_image,
					$this->imagemagick,
					(($this->is_inverted)? 1 : 0),
					$final_image);
			}
		}

		return (0 === $this->_exec ($cmd));
	}


	/**
	 * Check if given program is usable.
	 *
	 * @param mixed $match The string to be searched in program output. Can be an array of strings, in this case all strings must be found. Any non-string element in the array is ignored.
	 * @param string $prog The program to be checked
	 * @param string ... Arguments supplied to the program (if any)
	 * @return boolean Return TRUE if the given program is usable
	 * @since 0.2
	 */
	public function is_prog_usable ($match, $prog)
	{
		if (empty ($prog)) return false;

		// safe guard
		if (substr ($prog, 0, 1) != '/') return false;

		$prog = realpath ($prog);

		if (! is_executable ($prog)) return false;

		$args = func_get_args ();
		array_shift ($args);
		array_shift ($args);

		$cmdline = $prog . ' ' . implode (' ', $args) . ' 2>&1';
		if (false === ($handle = popen ($cmdline, 'r'))) return false;

		$output = fread ($handle, 2048);
		$ok = true;

		$needles = (array) $match;
		foreach ($needles as $needle)
		{
			if (is_string ($needle) && (false === strpos ($output, $needle)))
			{
				$ok = false;
				break;
			}
		}

		pclose ($handle);
		return $ok;
	}


	/**
	 * Render music fragment into images
	 *
	 * First it tries to check if image is already rendered, and return
	 * existing image file name immediately. Otherwise the music fragment is
	 * rendered in 2 passes (with {@link convertimg} and {@link execute},
	 * and resulting image is stored in cache folder.
	 *
	 * @uses ERR_INVALID_INPUT Return this error code if is_valid_input method returned false
	 * @uses ERR_LENGTH_EXCEEDED Return this error code if content length limit is exceeded
	 * @uses ERR_CACHE_DIRECTORY_NOT_WRITABLE Return this error code if cache directory is not writable
	 * @uses ERR_TEMP_DIRECTORY_NOT_WRITABLE Return this error code if temporary directory is not writable
	 * @uses ERR_INTERNAL_CLASS Return this error code if essential methods are missing from subclass
	 * @uses ERR_TEMP_FILE_NOT_WRITABLE Return this error if input file or postscript file is not writable
	 * @uses ERR_RENDERING_ERROR Return this error code if rendering command fails
	 * @uses ERR_IMAGE_CONVERT_FAILURE Return this error code if PS -> PNG conversion failed
	 *
	 * @uses execute First pass rendering: Convert input file -> PS
	 * @uses convertimg Second pass rendering: Convert PS -> PNG
	 *
	 * @return mixed Resulting image file name, or an error code in case any error occurred
	 * @final
	 */
	public function render()
	{
		$hash = md5 ($this->_input . $this->is_inverted
			     . $this->is_transparent . $this->get_notation_name());
		$final_image = $this->cache_dir. DIRECTORY_SEPARATOR .
		               "sr-" . $this->get_notation_name() . "-$hash.png";

		// Need not check anything if cache exists
		if (is_file ($final_image)) return basename ($final_image);

		// missing class methods
		if (! method_exists ($this, 'execute'           ) ||
	       	    ! method_exists ($this, 'get_music_fragment'))
			return ERR_INTERNAL_CLASS;

		// Check for valid code
		if ( empty ($this->_input) ||
		     ( method_exists ($this, 'is_valid_input') &&
		       !$this->is_valid_input() ) )
			return ERR_INVALID_INPUT;

		// Check for content length
		if ( isset ($this->content_max_length) &&
		     ($this->content_max_length > 0) &&
		     (strlen ($this->_input) > $this->content_max_length) )
			return ERR_LENGTH_EXCEEDED;

		if ( ! $this->is_prog_usable ('ImageMagick', $this->imagemagick, '-version') )
			return ERR_CONVERT_UNUSABLE;

		if ( (!is_dir      ($this->cache_dir)) ||
		     (!is_writable ($this->cache_dir)) )
			return ERR_CACHE_DIRECTORY_NOT_WRITABLE;

		// Use fallback tmp dir if original one not working
		if ( (!is_dir      ($this->temp_dir)) ||
		     (!is_writable ($this->temp_dir)) )
			$this->temp_dir = sys_get_temp_dir ();

		if ( (!is_dir      ($this->temp_dir)) ||
		     (!is_writable ($this->temp_dir)) )
			return ERR_TEMP_DIRECTORY_NOT_WRITABLE;

		if ( false === ($input_file = tempnam ($this->temp_dir,
			'scorerender-' . $this->get_notation_name() . '-')) )
			return ERR_TEMP_DIRECTORY_NOT_WRITABLE;
		
		$rendered_image = $input_file . '.ps';

		if (! file_exists ($rendered_image) )
		{
			// Create empty output file first ASAP
			if (! touch ($rendered_image) )
				return ERR_TEMP_FILE_NOT_WRITABLE;
		}
		elseif (! is_writable ($rendered_image) )
			return ERR_TEMP_FILE_NOT_WRITABLE;

		if (false === file_put_contents ($input_file, $this->get_music_fragment()))
			return ERR_TEMP_FILE_NOT_WRITABLE;


		// Render using external application
		$current_dir = getcwd();
		chdir ($this->temp_dir);
		if (!$this->execute($input_file, $rendered_image) ||
		    (filesize ($rendered_image)) === 0)
		{
			//unlink($input_file);
			return ERR_RENDERING_ERROR;
		}
		chdir ($current_dir);

		if (!$this->convertimg ($rendered_image, $final_image))
			return ERR_IMAGE_CONVERT_FAILURE;

		// Cleanup
		unlink ($rendered_image);
		unlink ($input_file);

		return basename ($final_image);
	}

} // end of class

?>
