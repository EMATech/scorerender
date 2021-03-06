<?php
/**
 * ScoreRender documentation
 * @package ScoreRender
 * @version 0.3.50
 * @author Abel Cheung
 * @copyright Copyright (C) 2006 Chris Lamb
 * @copyright Copyright (C) 2007, 2008, 2009, 2010 Abel Cheung
 * @license http://www.fsf.org/licensing/licenses/agpl-3.0.html GNU AGPL v3
 */

/**
 * Base class shared by all notations.
 * Most mandatory subclass methods are already listed in
 * {@link SrNotationInterface} interface below, plus one more:
 *
 * - {@link conversion_step1()}
 *
 * The methods below are optional for subclasses:
 *
 * - {@link conversion_step2()}
 * - {@link is_valid_input()}
 *
 * Please refer to class.*.inc.php for examples.
 * @package ScoreRender
 */
abstract class SrNotationBase
{

/**
 * Regular expression for cached images
 */
const REGEX_CACHE_IMAGE = '/^sr-(\w+)-([0-9A-Fa-f]{32})\.png$/';

/**
 * Regular expression for cached midi
 */
const REGEX_CACHE_MIDI  = '/^sr-(\w+)-([0-9A-Fa-f]{32})\.mid$/';

// How error is handled when rendering failed
const ON_ERR_SHOW_MESSAGE   = 1;
const ON_ERR_SHOW_FRAGMENT  = 2;
const ON_ERR_SHOW_NOTHING   = 3;

// What kind of data should be returned when retrieving default setting
const TYPES_AND_VALUES  = 1;
const TYPES_ONLY        = 2;
const VALUES_ONLY       = 3;

/**
 * ImageMagick requirement check related data
 */
public static $imagick_check = array (
	'test_arg'    => array ('-version'),
	'test_output' => '/^Version: ImageMagick ([\d.-]+)/',
	'min_version' => '6.3.5-7',
);

/**
 * Stores all current ScoreRender settings.
 */
public static $sr_opt = array ();

/**
 * Array of supported music notation syntax and relevant attributes.
 */
public static $notations = array();

/**
 * @var string $input The raw music fragment to be rendered.
 */
protected $input;

/**
 * @var string $cmd_output Stores output message of rendering command.
 */
protected $cmd_output;

/**
 * @var string $imagick Full path of ImageMagick convert
 */
protected $imagick;

/**
 * @var string $imagick_ver Version of ImageMagick installed on host
 */
protected $imagick_ver = '';

/**
 * @var string $temp_dir Temporary working directory
 */
protected $temp_dir;

/**
 * @var string $cache_dir Folder for storing cached images
 */
protected $cache_dir;

/**
 * @var integer $img_max_width Maximum width of generated images
 */
protected $img_max_width = 360;

/**
 * @var string $mainprog Main program for rendering music into images
 */
protected $mainprog;

/**
 * @var string $midiprog Program used for generating MIDI
 */
protected $midiprog;

/**
 * @var string $final_image Generated image file full path,
 * null if uninitialized, FALSE if not successfully generated
 */
public $final_image = null;

/**
 * @var string $final_midi Generated MIDI file full path,
 * null if uninitialized, FALSE if not successfully generated
 */
public $final_midi = null;

/**
 * @var string $input_file File name containing post-processed music
 * fragment content
 */
protected $input_file = null;

/**
 * Sets music fragment content
 *
 * @since 0.2
 * @uses $input Stores music fragment content into this variable
 * @param string $input The music fragment content
 */
public function set_music_fragment ($input)
{
	$this->input = $input;
}


/**
 * Output raw fragment content
 * @since 0.3.50
 * @return string The raw fragment content without any processing
 */
public function get_raw_input ()
{
	return $this->input;
}


/**
 * Set the main program used to render music
 *
 * @param mixed $progs A single program or array of programs to be used
 * @uses $mainprog Full path is stored in this variable
 * @todo Needs cleaner implementation; for some notations like PMX/MusiXTeX,
 * multiple programs must be set, currently this function can only handle one.
 * @since 0.3
 */
public function set_img_progs ($progs) /* {{{ */
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
			list ($k) = array_keys ($progs);
			$this->mainprog = $progs[$k];
			break;
		}
	}
} /* }}} */


/**
 * Set the main program used to generate MIDI
 *
 * @param mixed $progs A single program or array of programs to be used
 * @uses $midiprog Full path is stored in this variable
 * @todo Needs cleaner implementation, generally only at most one program is used
 * @since 0.3.50
 */
public function set_midi_progs ($progs) /* {{{ */
{
	if (is_string ($progs))
	{
		$this->midiprog = $progs;
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
			$this->midiprog = $v[0];
			break;
		  default:
			// Only picks the first element
			list ($k) = array_keys ($progs);
			$this->midiprog = $progs[$k];
			break;
		}
	}
} /* }}} */


/**
 * Set the program used to generate MIDI
 *
 * @param string $prog The full path of program
 * @uses $midiprog Full path of MIDI program is stored here
 * @since 0.3.50
 */
public function set_midi_program ($prog = null) /* {{{ */
{
	if ( empty ($prog) ) return;
	$this->midiprog = $prog;
	return;
} /* }}} */


/**
 * Returns output message of rendering command.
 *
 * @uses $cmd_output Returns output of last executed command
 * @return string
 */
public function get_command_output ()
{
	return $this->cmd_output;
}

/**
 * Returns notation name, by comparing class name with notation name list.
 *
 * @return string|boolean Return notation name if found, FALSE otherwise.
 * @since 0.2
 */
public function get_notation_name () /* {{{ */
{
	$classname = get_class ($this);

	foreach ( self::$notations as $notationname => $notation )
		if ( $classname === $notation['classname'] )
			return $notationname;

	return false;
} /* }}} */

/**
 * Sets path of ImageMagick convert
 *
 * @param string $path Full path of ImageMagick convert binary
 * @since 0.3
 */
public function set_imagemagick_path ($path)
{
	$this->imagick = $path;
}

/**
 * Set temporary folder
 *
 * @param string $path The desired temporary folder to use.
 * @since 0.3
 */
public function set_temp_dir ($path)
{
	$this->temp_dir = $path;
}

/**
 * Get temporary folder path
 *
 * @return string|boolean The temporary folder if already set, or FALSE otherwise
 * @since 0.3
 */
public function get_temp_dir ()
{
	return (isset ($this->temp_dir)) ? $this->temp_dir : false;
}

/**
 * Set cache folder for storing images
 *
 * @param string $path The desired cache folder to use.
 * @since 0.3
 */
public function set_cache_dir ($path)
{
	$this->cache_dir = $path;
}

/**
 * Get cache folder path
 *
 * @return string|boolean The cache folder if already set, or FALSE otherwise
 * @since 0.3
 */
public function get_cache_dir ()
{
	return (isset ($this->cache_dir)) ? $this->cache_dir : false;
}

/**
 * Set maximum width of generated images
 *
 * For some notations the unit for width is not pixel; under those situations
 * the methods are overrided in inherited notations.
 *
 * @param integer $width Maximum width of images (in pixel)
 * @since 0.3
 */
public function set_img_width ($width)
{
	$this->img_max_width = $width;
}

/**
 * Utility function: format error message into standardized format
 *
 * @param string $mesg Original error message
 * @return string Formatted message
 * @since 0.3
 */
public static function format_error_msg ($mesg)
{
	return sprintf (__('[ScoreRender Error: %s]', SR_TEXTDOMAIN), $mesg);
}


/**
 * Executes command and stores output message
 *
 * It is basically {@link exec() exec()} with additional stuff. On Windows,
 * however, the command is dumped into a batch first before executing,
 * in order to circumvent an ancient unfixed bug.
 *
 * @param string $cmd Command to be executed
 * @uses $cmd_output Command output is stored after execution.
 * @uses is_windows() Command line is adjusted if under Windows
 * @return integer Exit status of the command
 * @final
 */
final protected function _exec ($cmd) /* {{{ */
{
	$cmd_output = array();

	$retval = 0;

	$cmd .= " 2>&1";
	// ": &" is used to bypass cmd.exe /c, which prevents commands
	// with more than 2 double quotes to run
	if (is_windows())
		$cmd = '\: \& ' . $cmd;

	if (SR_DEBUG) error_log ("ScoreRender: execute command: " . $cmd);

	exec ($cmd, $cmd_output, $retval);
	$this->cmd_output = implode ("\n", $cmd_output);

	return $retval;
} /* }}} */

/**
 * First step of rendering process: execute the real command
 *
 * The command reads input content (after necessary conversion),
 * and converts it to an intermediate file, most usually PostScript.
 *
 * @uses _exec()
 * @uses SrNotationBase::$input_file File containing post-processed music code
 * @return WP_Error|int|string File name of intermediate image upon success,
 * return value of command upon command failure, and WP_Error upon other failures
 * not related to rendering command execution
 */
abstract protected function conversion_step1 ();

/**
 * Second step of rendering process: Convert rendered PostScript page into PNG format
 *
 * (Almost) All rendering commands generate PostScript format as output.
 * In order to show the image, it must be converted to PNG format first,
 * using ImageMagick. Various effects are also applied, like white edge
 * trimming, color inversion and alpha blending.
 *
 * In inherited classes, only the first 2 arguments are supplied. All other arguments
 * are automatically determined within the inherited classes.
 *
 * @uses _exec()
 * @uses SrNotationBase::$imagick
 * @param string $intermediate_image The rendered PostScript file name
 * @param boolean $ps_has_alpha True if PostScript produced by music
 * rendering program has transparency capability
 * @param string $extra_arg Extra arguments supplied to ImageMagick convert
 * @return int|string File name of PNG image generated if successful,
 * otherwise return the exit status of command
 */
protected function conversion_step2 ($intermediate_image, $ps_has_alpha = false, $extra_arg = '') /* {{{ */
{
	if ( false === ( $tempimage = tempnam ( getcwd(), '' ) ) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', SR_TEXTDOMAIN) );

	$cmd = sprintf ('"%s" %s -trim +repage ', $this->imagick, $extra_arg);

	// Damn it, older ImageMagick can't handle transparency in PostScript,
	// but suddenly it can now, and renders all previous logic broken
	if ($ps_has_alpha)
		$cmd .= sprintf (' "%s" "png:%s"', $intermediate_image, $tempimage);
	else
		// Adding alpha channel and changing alpha value
		// need separate invocations, can't do in one pass
		$cmd .= sprintf ('-alpha activate "%s" png:- | "%s" -channel alpha -fx "1-intensity" -channel rgb -fx 0 png:- "png:%s"',
			$intermediate_image, $this->imagick, $tempimage);

	$retval = $this->_exec ($cmd);

	if ( ( 0 !== $retval ) || !filesize ($tempimage) )
		return $retval;
	else
		return $tempimage;
} /* }}} */

/**
 * Reads input content (after necessary conversion),
 * and converts it to a MIDI file.
 *
 * @uses _exec()
 * @uses $midiprog
 * @uses $input_file
 * @param array $attr Array of shortcode attributes
 * @return string|int MIDI file name if successfully generated, otherwise
 * return error status of the command executed
 */
abstract protected function get_midi ( $attr );

/**
 * Check if certain functions are disabled
 *
 * @since 0.3
 * @return boolean Return TRUE if {@link popen() popen()} or
 * {@link pclose() pclose()} are disabled, FALSE otherwise
 */
public static function is_web_hosting ()
{
	return ( !function_exists ('popen') || !function_exists ('pclose') );
}

/**
 * Check if given program is usable, by identifying existance of certain string
 * in command output, and optionally checking program version requirement as well
 *
 * @since 0.2
 * @uses is_web_hosting()
 * @param string|Array $regexes Regular expression (PCRE) to be matched in program output.
 * Can be an array of regexes, in this case ALL regexes must be matched.
 * @param string $prog The program to be checked
 * @param array $args Array of strings containing extra command line arguments (if any)
 * @param string $minver Program version to match against. The version of program
 * to be checked must not be smaller than the version supplied in argument.
 * Using this argument means only the first string in $regexes is searched.
 * @param int $verpos Regex position inside $regexes that should contain
 * version information, default is 1 (first parenthesis). Only used if $minver is
 * not null or empty.
 * @param string $realver If version check is performed, then the detected program
 * version will be written to this variable
 * @return WP_Error|bool TRUE if all regex strings are found in command output,
 * WP_Error otherwise (including unexpected error and unmatched regex)
 */
public function is_prog_usable ($regexes, $prog, $args = array(), $minver = "", $verpos = 1, &$realver = null) /* {{{ */
{
	if ( ! file_exists ($prog) ) return new WP_Error
		( 'sr-prog-notexist', __('Program does not exist.', SR_TEXTDOMAIN));

	if ( ! is_executable ($prog) ) return new WP_Error
		( 'sr-not-executable', __('Program is not executable.', SR_TEXTDOMAIN) );

	// safe guard
	$prog = realpath ($prog);
	if ( false === $prog ) return new WP_Error
		( 'sr-realpath-fail', __("Can't determine real path of program.", SR_TEXTDOMAIN) );

	// short circuit if some funcs are disabled by web host
	if (self::is_web_hosting()) return new WP_Error
		( 'sr-webhost-mode', __("Certain PHP functions are disabled by web host, most likely due to security reasons. Therefore program usability will not be checked.", SR_TEXTDOMAIN) );

	// TODO: check that elements inside $args are indeed strings
	$args = (array) $args;

	$cmd = sprintf ( '"%s" %s 2>&1', $prog, implode (' ', $args) );
	if (false === ($handle = @popen ($cmd, 'r'))) return new WP_Error
		( 'sr-popen-fail', __('Failed to execute popen() for running command.', SR_TEXTDOMAIN), $cmd );

	while ( ! feof ($handle) )
		$output .= fread ($handle, 1024);
	pclose ($handle);

	// Version check
	if ( ! empty ($minver) )
	{
		if ( !is_int ($verpos) || $verpos < 0 )
			return new WP_Error ( 'sr-verpos-invalid', __('Version position is invalid', SR_TEXTDOMAIN), $verpos );

		if ( is_array ($regexes) ) $regexes = $regexes[0];

		if ( !preg_match ( $regexes, $output, $matches ) )
			return new WP_Error ( 'sr-prog-regex-notmatch', __('Desired regular expression not found in program output', SR_TEXTDOMAIN), $regexes );

		// Storing detected version
		if ( ! is_null ( $realver ) )
			$realver = $matches[$verpos];

		if ( version_compare ( $matches[$verpos], $minver, '>=' ) )
			return true;

		// Fail if installed program doesn't fulfill version requirement
		return new WP_Error ( 'sr-prog-ver-req-unfulfilled',
				sprintf (__("Program does not meet minimum version requirement, detected version is &#8216;%s&#8217; but &#8216;%s&#8217; is required.", SR_TEXTDOMAIN),
					$matches[$verpos], $minver),
				array (
					'desired' => $minver,
					'actual'  => $matches[$verpos],
				)
		);
	}

	foreach ( (array)$regexes as $regex )
		if ( !preg_match ( $regex, $output ) )
			return new WP_Error ( 'sr-prog-regex-notmatch',
					__('Desired regular expression not found in program output', SR_TEXTDOMAIN),
					$regexes
			);

	// no version check performed, and all strings matched
	return true;
} /* }}} */


/**
 * Execute prerequisite checks before rendering.
 *
 * @since 0.3.50
 * @uses $imagick
 * @uses $imagick_ver
 * @uses $cache_dir
 * @uses $temp_dir
 * @uses SrNotationBase::is_prog_usable()
 * @return WP_Error|bool TRUE if all checks passed, WP_Error otherwise
 */
final protected function perform_checks() /* {{{ */
{
	// Check for code validity or security issues
	if ( method_exists ($this, 'is_valid_input') && !$this->is_valid_input() )
		return new WP_Error ( 'sr-content-invalid',
				__('Content is illegal or poses security concern', SR_TEXTDOMAIN) );

	// Check ImageMagick
	$result = $this->is_prog_usable (
		self::$imagick_check['test_output'],
		$this->imagick,
		self::$imagick_check['test_arg'],
		self::$imagick_check['min_version'],
		1, $this->imagick_ver);

	if ( is_wp_error ($result) || !$result )
		return new WP_Error ( 'sr-imagick-unusable',
				__('ImageMagick program is unusable', SR_TEXTDOMAIN), $result );

	// Check cache folder
	if ( (!is_dir      ($this->cache_dir)) ||
	     (!is_writable ($this->cache_dir)) )
		return new WP_Error ( 'sr-cache-dir-unwritable',
				__('Cache directory not writable', SR_TEXTDOMAIN) );

	// Use fallback tmp dir if original one not working
	if ( (!is_dir      ($this->temp_dir)) ||
	     (!is_writable ($this->temp_dir)) )
		$this->temp_dir = sys_get_temp_dir ();

	// Check temp folder
	if ( (!is_dir      ($this->temp_dir)) ||
	     (!is_writable ($this->temp_dir)) )
		return new WP_Error ( 'sr-temp-dir-unwritable',
				__('Temporary directory not writable', SR_TEXTDOMAIN) );

	// Check notation rendering apps
	$result = $this->is_notation_usable (null, self::$sr_opt);
	if ( is_wp_error ($result) || !$result )
		return new WP_Error ( 'sr-render-apps-unusable',
				__('Rendering application is unusable', SR_TEXTDOMAIN), $result );

	return true;
} /* }}} */


/**
 * Create temporary file for storing music code content.
 *
 * @since 0.3.50
 * @uses $temp_dir
 * @uses $input_file
 * @uses create_temp_dir()
 * @uses SrNotationBase::get_music_fragment()
 * @return WP_Error|bool TRUE if all checks passed, WP_Error otherwise
 */
final protected function create_input_file () /* {{{ */
{
	if ( false === ($temp_folder = create_temp_dir ($this->temp_dir, 'sr-')) )
		return new WP_Error ( 'sr-temp-dir-unwritable',
				__('Temporary directory not writable', SR_TEXTDOMAIN) );

	if ( false === ($this->input_file = tempnam ($temp_folder,
		'scorerender-' . $this->get_notation_name() . '-')) )
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', SR_TEXTDOMAIN) );

	do_action ('scorerender_before_create_input_file');

	if (false === file_put_contents ($this->input_file, $this->get_music_fragment()))
		return new WP_Error ( 'sr-temp-file-create-fail',
				__('Temporary file creation failure', SR_TEXTDOMAIN) );

	do_action ('scorerender_after_create_input_file');

	return true;
} /* }}} */


/**
 * Recursively remove directory, aka deltree on Windows and
 * rm -rf on Unix. This function performs no check of current
 * working folder so should be executed outside the folder
 * to be deleted.
 *
 * @param string $dir The folder to be removed
 * @return bool|WP_Error
 */
final static protected function cleanup_dir ($dir) /* {{{ */
{
	if ( false === ( $handle = @opendir ($dir) ) )
		return false;
//		return new WP_Error ( 'sr_opendir_fail', __('Fail to open directory', SR_TEXTDOMAIN) );

	$ok = true;

	while (false !== ($file = @readdir ($handle)))
	{
		if ( ( '.' == $file ) || ( '..' == $file ) ) continue;
		if ( is_dir ($dir.'/'.$file) )
			$ok = $ok & self::cleanup_dir($dir.'/'.$file);
		else
			unlink ($dir.'/'.$file);
	}

	closedir ($handle);
	$ok = $ok & @rmdir ($dir);
	return $ok;
} /* }}} */


/**
 * Perform final cleanup action after all rendering finished
 *
 * @uses SrNotationBase::cleanup_dir()
 */
final public function cleanup ()
{
	chdir ('/');
	$this->cleanup_dir ( dirname ( $this->input_file ) );
}


/**
 * Render music fragment into images, and optionally generate MIDI
 *
 * First it tries to check if image is already rendered, and return
 * existing image file name immediately. Otherwise the music fragment is
 * rendered in 2 passes (with {@link conversion_step1()} and
 * {@link conversion_step2()}. Resulting image is stored in cache folder,
 * while its name is stored in $final_image. Finally, generate MIDI
 * file and store its name in $final_midi.
 *
 * @uses SrNotationInterface::get_music_fragment()
 * @uses is_valid_input() Validate content before rendering
 * @uses is_prog_usable() Check if ImageMagick is functional
 * @uses conversion_step1() First pass rendering: Convert input file -> PS
 * @uses conversion_step2() Second pass rendering: Convert PS -> PNG
 * @uses $cache_dir
 *
 * @todo render image in background, especially for lilypond, which can take
 * minutes or even hours to finish rendering
 * @param array $attr Array of shortcode attributes
 * @return boolean|WP_Error WP_Error upon image rendering error, FALSE if
 * non-critical error occurred (such as midi generation error), TRUE if all ok
 * @final
 */
final public function render ( $attr ) /* {{{ */
{
	$hash = md5 (preg_replace ('/\s/', '', $this->input));
	$final_image = $this->cache_dir. DIRECTORY_SEPARATOR .
			"sr-" . $this->get_notation_name() . "-$hash.png";
	$final_midi = $this->cache_dir. DIRECTORY_SEPARATOR .
			"sr-" . $this->get_notation_name() . "-$hash.mid";

	// first presume generation failed, modify later if turn out ok
	$this->final_image = false;

	// If cache exists, short circuit
	if ( is_file ($final_midi) )
	{
		if ( is_readable ($final_midi) )
			$this->final_midi = basename ($final_midi);
		else
			$this->final_midi = false;
	}

	if (is_file ($final_image))
		if (is_readable ($final_image))
		{
			$this->final_image = basename ($final_image);
			// FIXME: During first time of rendering process, get_midi() is executed
			// so one knows if MIDI generation fails. However if cached image already
			// exists, function call is skipped so there's no way to know if it's a
			// failure or simply not done at all. Maybe should move get_midi() out
			// of this func and execute independently.
			return true;
		}
		else
			return new WP_Error ( 'sr-image-unreadable',
					__('Image exists but is not readable', SR_TEXTDOMAIN), $final_image );

	$result = $this->perform_checks();
	if ( is_wp_error ($result) ) return $result;

	$result = $this->create_input_file();
	if ( is_wp_error ($result) ) return $result;

	$temp_working_dir = dirname ($this->input_file);

	chdir ($temp_working_dir);

	// Start rendering image
	$result = $this->conversion_step1();
	if ( is_wp_error ($result) )
		return $result;
	elseif ( is_numeric ($result) )
		return new WP_Error ( 'sr-img-render-fail',
				__('Image rendering process failure', SR_TEXTDOMAIN) );

	$intermediate_image = $result;

	$result = $this->conversion_step2($intermediate_image);
	if ( is_wp_error ($result) )
		return $result;
	elseif ( is_numeric ($result) )
		return new WP_Error ( 'sr-img-convert-fail',
				__('Image conversion failure', SR_TEXTDOMAIN) );

	if ( SR_DEBUG ) error_log ("ScoreRender: move file: $result -> $final_image");

	// Shouldn't happen, as temp image is guaranteed to exist
	// at this point, and cache dir is writable in pre-check
	if ( ! @rename ( $result, $final_image ) )
		return new WP_Error ( 'sr-cache-file-internal-error',
				__('Cache image creation internal error', SR_TEXTDOMAIN) );

	$this->final_image = basename ($final_image);

	$ok = true;

	// Then generate midi
	if ( is_null ( $this->final_midi ) && method_exists ($this, 'get_midi') )
	{
		$result = $this->get_midi ( $attr );

		if ( is_string ($result) )
		{
			if ( @rename ( $result, $final_midi ) )
				$this->final_midi = basename ($final_midi);
			else
				$this->final_midi = false;
		}
		else
			$this->final_midi = $ok = false;
	}

	return $ok;
} /* }}} */


/**
 * Output program setting HTML for notation
 *
 * @param string $bin_name Name of binary program
 * @param string $setting_name Name of setting used by the binary
 * @param string $title Title for setting entry
 * @param string $desc Optional description, shown under setting entry
 *
 * @return string HTML for the program setting in admin page
 * @since 0.3.50
 */
protected static function program_setting_entry ($bin_name, $setting_name, $title = '', $desc = '') /* {{{ */
{
	$id = strtolower ($setting_name);

	$output = "<tr valign='top'>\n"
		. "<th scope='row'><label for='{$id}'>"
		. ( empty ($title) ? sprintf (__('Location of %s binary:', SR_TEXTDOMAIN), '<code>'.$bin_name.'</code>') : $title )
		. "</label></th>\n"
		. "<td><input name='ScoreRender[{$setting_name}]' type='text' id='{$id}' "
		. "value='" . self::$sr_opt[$setting_name] . "' class='regular-text code' /> "
		. ( empty ($desc) ? '' : ScoreRenderAdmin::print_description ($desc) )
		. "</td>\n</tr>\n";

	return $output;
} /* }}} */


/**
 * Define values for variables used for notation, usually program paths
 *
 * This func is only called by inherited notation classes.
 *
 * @param array $settings Reference of ScoreRender default settings to be modified
 * @param array $notation_prog_data Default values of notation programs
 * stored in each notation class
 *
 * @since 0.3.50
 * @access protected
 */
protected static function define_setting_value ($settings, $notation_prog_data) /* {{{ */
{
	foreach ( $notation_prog_data as $setting_name => $progdata )
	{
		$binary_name = $progdata['prog_name'];
		$fullpath = search_prog ($binary_name);
		$settings[$setting_name]['value'] = $fullpath ? $fullpath : '';
	}
} /* }}} */


/**
 * Check if given program locations are correct and usable
 *
 * It can be called both as static method AND class method.
 *
 * In the first case, it is used as a WP action hook inside admin page
 * as program checker. $errmsgs is used to remember error messages
 * that shall be printed in top portion of admin page. No value is
 * returned.
 *
 * In second case, it is executed during rendering phase. $errmsgs
 * shall be set to NULL, and the function returns either TRUE or
 * FALSE, depending on program checking result.
 *
 * @since 0.3.50
 * @uses SrNotationBase::is_prog_usable()
 *
 * @param array $errmsgs An array of messages to be added if program
 * checking failed. Only used if this is called as a static method.
 * Please refer to description of this function for more detail.
 * @param array $opt Reference of ScoreRender option array, containing
 * all program paths
 *
 * @return null|bool NULL if it is called as static method, TRUE/FALSE
 * if called as class method. Please refer to description
 * of this function for more detail.
 */
protected function is_notation_usable ($errmsgs = null, $opt, $notation_prog_data) /* {{{ */
{
	$ok = true;
	foreach ( $notation_prog_data as $setting_name => $progdata )
	{
		if ( 'prog' !== $progdata['type'] ) continue;

		// TODO: for notations using only one program, empty setting means notation
		// is disabled, thus shouldn't output error; while for notations in multiple
		// programs, incomplete setting means error
		if ( empty ($opt[$setting_name]) )
		{
			$ok = true;
			break;
		}

		$result = self::is_prog_usable ( $progdata['test_output'],
				$opt[$setting_name], $progdata['test_arg']);
		if ( is_wp_error ($result) || !$result )
		{
			$ok = false;
			break;
		}
	}

	if (!$ok)
		if ( !is_null ($errmsgs) ) $errmsgs[] = $progdata['error_code'];

	if ( isset ($this) ) return $ok;	// called from instantiated class object
} /* }}} */


} // end of class

/**
 * Interface for every notations used by ScoreRender
 * @package ScoreRender
 */
interface SrNotationInterface /* {{{ */
{
	/**
	 * Outputs music fragment content
	 *
	 * Most usually user supplied content does not contain correct
	 * rendering options like page margin, staff width etc, and
	 * each notation has its own requirements. Some also require additional
	 * filtering before able to be used by rendering programs.
	 * Such conversions are applied on output as well, though original content
	 * ($input) is not modified in any way.
	 *
	 * @return string The full music content to be rendered, after necessary filtering
	 */
	function get_music_fragment ();

	/**
	 * Check if program locations are correct and usable. Please refer to
	 * {@link SrNotationBase::is_notation_usable} for more detail.
	 *
	 * @param array $errmsgs
	 * @param array $opt
	 *
	 * @since 0.3.50
	 */
	function is_notation_usable ($errmsgs = null, $opt);

	/**
	 * Define any additional error or warning messages if settings for notation
	 * has any problem.
	 *
	 * @param array $adm_msgs Array of messages potentially shown in
	 * admin page if any problem occurs
	 *
	 * @since 0.3.50
	 */
	static function define_admin_messages ($adm_msgs);

	/**
	 * Output program setting HTML for notation
	 *
	 * @param string $output string containing all HTML output, where extra settings shall be added
	 * @return string The new HTML output after adding input entries for settings
	 *
	 * @since 0.3.50
	 */
	static function program_setting_entry ($output);

	/**
	 * Define types of variables used for notation
	 *
	 * @param array $settings Reference of ScoreRender default settings to be modified
	 *
	 * @since 0.3.50
	 */
	static function define_setting_type (&$settings);

	/**
	 * Define values for variables used for notation, usually program paths
	 *
	 * @param array $settings Reference of ScoreRender default settings to be modified
	 *
	 * @since 0.3.50
	 */
	static function define_setting_value (&$settings);

	/**
	 * Register notation and its data necessary for ScoreRender processing
	 *
	 * @param array $notations Reference of ScoreRender notation data array
	 *
	 * @since 0.3.50
	 */
	static function register_notation_data ($notations);

} /* }}} */ // end of interface

/* vim: set cindent foldmethod=marker : */
?>
