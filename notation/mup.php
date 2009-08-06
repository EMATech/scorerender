<?php
/**
 * Implements rendering of Mup notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Mup notation.
 * @package ScoreRender
*/
class mupRender extends ScoreRender
                implements ScoreRender_Notation
{

private $width;

/**
 * @var string $magic_file Location of magic file used by Mup
 * @access private
 */
private $magic_file;

function __construct ()
{
	add_action ('sr_set_class_variable', array (&$this, 'set_magic_file_hook'));
}

/**
 * Set maximum width of generated images
 *
 * @param integer $width Maximum width of images (in pixel)
 * @since 0.2.50
 */
public function set_img_width ($width)
{
	parent::set_img_width ($width);
	$this->width = $this->img_max_width / DPI;
}

/**
 * Set the location of magic file
 *
 * @param string $file Full path of magic file
 * @since 0.2.50
 */
public function set_magic_file ($file)
{
	$this->magic_file = $file;
}

/**
 * Checks if given content is invalid or dangerous content
 *
 * @param string $input
 * @return boolean True if content is deemed safe
 */
protected function is_valid_input ()
{
	$blacklist = array
	(
		'/^\s*\binclude\b/', '/^\s*\bfontfile\b/'
	);

	foreach ($blacklist as $pattern)
		if (preg_match ($pattern, $this->_input))
			return false;

	return true;
}

/**
 * Refer to {@link ScoreRender::get_music_fragment() parent method} for more detail.
 */
public function get_music_fragment ()
{
	$header = <<<EOD
//!Mup-Arkkra-5.0
score
leftmargin = 0
rightmargin = 0
topmargin = 0
bottommargin = 0
pagewidth = {$this->width}
label = ""
EOD;
	return $header . "\n" . $this->_input;
}

/**
 * Refer to {@link ScoreRender::conversion_step1() parent method} for more detail.
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	/* Mup requires a magic file before it is usable.
	   On Unix this file is named ".mup", and must reside in $HOME or current working directory.
	   On Windows / DOS, it is named "mup.ok" instead, and located in current working directory or same location as mup.exe do.
	   It must be present even if not registered, otherwise mup refuse to render anything.
	   Even worse, the exist status in this case is 0, so _exec() succeeds yet no postscript is rendered. */

	if (is_windows())
		$temp_magic_file = $this->temp_dir . '\mup.ok';
	else
		$temp_magic_file = $this->temp_dir . '/.mup';
	
	if (!file_exists($temp_magic_file))
	{
		if (is_readable($this->magic_file))
			copy($this->magic_file, $temp_magic_file);
		else
			touch ($temp_magic_file);
	}

	/* mup forces this kind of crap */
	putenv ("HOME=" . $this->temp_dir);
	chdir ($this->temp_dir);
	
	$cmd = sprintf ('"%s" -f "%s" "%s"',
			$this->mainprog,
			$intermediate_image, $input_file);
	$retval = $this->_exec($cmd);

	unlink ($temp_magic_file);
	
	return (filesize ($intermediate_image) != 0);
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method} for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// FIXME: mind boggling exercise: why ImageMagick identifies PostScript produced by Mup as having
	// transparency on Windows, yet otherwise on Linux?
	return parent::conversion_step2 ($intermediate_image, $final_image, is_windows());
}


/**
 * Check if given program locations are correct and usable
 *
 * @param array $errmsgs An array of messages to be added if program checking failed
 * @param array $opt Array of ScoreRender options, containing all program paths
 * @uses ScoreRender::is_prog_usable()
 */
public static function is_notation_usable (&$errmsgs, &$opt)
{
	global $notations;

	$ok = true;
	foreach ($notations['mup']['progs'] as $setting_name => $program)
		if ( ! empty ($opt[$setting_name]) && ! parent::is_prog_usable (
			$program['test_output'], $opt[$setting_name], $program['test_arg']) )
				$ok = false;
			
	if (!$ok) $errmsgs[] = 'mup_bin_problem';
}


/**
 * Set the location of magic file
 * This is not supposed to be called directly; it is used as a
 * WordPress action hook instead.
 *
 * {@internal OK, I cheated. Shouldn't have been leaking external
 * config option names into class, but this can help saving me
 * headache in the future}}
 *
 * @since 0.2.50
 */
public function set_magic_file_hook ($options)
{
	if (isset ($options['MUP_MAGIC_FILE']))
		$this->set_magic_file ($options['MUP_MAGIC_FILE']);
}

/**
 * Define any additional error or warning messages if settings for notation
 * has any problem.
 */
public static function define_admin_messages (&$adm_msgs)
{
	global $notations;

	$adm_msgs['mup_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['mup']['name'])
	);
}

/**
 * Output program setting HTML for notation
 */
public static function program_setting_entry ($output)
{
	global $notations;

	foreach ($notations['mup']['progs'] as $setting_name => $program)
		$output .= parent::program_setting_entry (
			$program['prog_name'], $setting_name);

	$output .= parent::program_setting_entry (
		'', 'MUP_MAGIC_FILE',
		sprintf (__('Location of %s magic file:', TEXTDOMAIN), '<code>mup</code>'),
		sprintf (__('Leave it empty if you have not <a href="%s">registered</a> Mup. This file must be readable by the user account running web server.', TEXTDOMAIN),
			'http://www.arkkra.com/doc/faq.html#payment')
	);
	return $output;
}

/**
 * Define types of variables used for notation
 */
public static function define_setting_type (&$settings)
{
	global $notations;

	$settings += $notations['mup']['progs'];
}

}  // end of class


$notations['mup'] = array (
	'name'        => 'Mup',
	'url'         => 'http://www.arkkra.com/',
	'regex'       => '~\[mup\](.*?)\[/mup\]~si',
	'starttag'    => '[mup]',
	'endtag'      => '[/mup]',
	'classname'   => 'mupRender',
	'progs'       => array (
		'MUP_BIN' => array (
			'prog_name' => 'mup',
			'type'      => 'prog',
			'value'     => '',
			'test_arg'  => '-v',
			'test_output' => 'Arkkra Enterprises',
		),
	),
);


add_action ('scorerender_define_adm_msgs',
	array( 'mupRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'mupRender', 'is_notation_usable' ), 10, 2 );

add_filter ('scorerender_prog_and_file_loc',
	array( 'mupRender', 'program_setting_entry' ) );

add_filter ('scorerender_define_setting_type',
	array( 'mupRender', 'define_setting_type' ) );
?>
