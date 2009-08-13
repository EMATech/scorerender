<?php
/**
 * Implements rendering of Lilypond notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting Lilypond notation.
 * @package ScoreRender
*/
class lilypondRender extends ScoreRender
                     implements ScoreRender_Notation
{

/**
 * Refer to {@link ScoreRender_Notation::get_music_fragment() interface method}
 * for more detail.
 */
public function get_music_fragment ()
{
	$header = <<<EOD
\\version "2.8.1"
\\header {
	tagline= ""
}
\\paper {
	ragged-right = ##t
	indent = 0.0\\mm
	line-width = {$this->img_max_width}\\pt
}
\\layout {
	\\context {
		\\Score
		\\remove "Bar_number_engraver"
	}
}
EOD;

	// When does lilypond start hating \r ? 
	return $header . str_replace (chr(13), '', $this->_input);
}

/**
 * Determine LilyPond version
 * @param string $lilypond The path of lilypond program
 * @return string|boolean The version number string if it can be determined, otherwise FALSE 
 */
public static function lilypond_version ($lilypond)
{
	if ( !function_exists ('exec') ) return FALSE;

	exec ("\"$lilypond\" -v 2>&1", $output, $retval);
	
	if ( empty ($output) ) return FALSE;
	if ( !preg_match('/^gnu lilypond (\d+\.\d+\.\d+)/i', $output[0], $matches) ) return FALSE;
	return $matches[1];
}

/**
 * Refer to {@link ScoreRender::conversion_step1() parent method}
 * for more detail.
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	$safemode = '';
	/* LilyPond SUCKS unquestionably. On or before 2.8 safe mode is triggered by --safe option,
	 * on 2.10.x it becomes --safe-mode, and on 2.12.x the option is completely gone!
	 */
	if ( false !== ( $lilypond_ver = self::lilypond_version ($this->mainprog) ) )
		if ( version_compare ($lilypond_ver, '2.11.0', '<') )
			$safemode = '-s';
	
	/* lilypond adds .ps extension by itself, sucks for temp file generation */
	$cmd = sprintf ('"%s" %s --ps --output "%s" "%s"',
		$this->mainprog,
		$safemode,
		dirname($intermediate_image) . DIRECTORY_SEPARATOR . basename($intermediate_image, ".ps"),
		$input_file);

	$retval = $this->_exec ($cmd);

	return ($retval == 0);
}

/**
 * Refer to {@link ScoreRender::conversion_step2() parent method}
 * for more detail.
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// default staff size for lilypond is 20px, expected 24px, a ratio of 1.2:1
	// and 72*1.2 = 86.4
	return parent::conversion_step2 ($intermediate_image, $final_image, TRUE,
		'-equalize -density 86');
}

/**
 * Refer to {@link ScoreRender_Notation::is_notation_usable() interface method}
 * for more detail.
 * @uses ScoreRender::is_prog_usable()
 */
public static function is_notation_usable ($errmsgs, $opt)
{
	global $notations;

	$ok = true;
	foreach ($notations['lilypond']['progs'] as $setting_name => $program)
		if ( ! empty ($opt[$setting_name]) && ! parent::is_prog_usable (
			$program['test_output'], $opt[$setting_name], $program['test_arg']) )
				$ok = false;
			
	if (!$ok) $errmsgs[] = 'lilypond_bin_problem';
}

/**
 * Refer to {@link ScoreRender_Notation::define_admin_messages() interface method}
 * for more detail.
 */
public static function define_admin_messages ($adm_msgs)
{
	global $notations;

	$adm_msgs['lilypond_bin_problem'] = array (
		'level' => MSG_WARNING,
		'content' => sprintf (__('%s notation support may not work, because dependent program failed checking.', TEXTDOMAIN), $notations['lilypond']['name'])
	);
}

/**
 * Refer to {@link ScoreRender_Notation::program_setting_entry() interface method}
 * for more detail.
 */
public static function program_setting_entry ($output)
{
	global $notations;

	foreach ($notations['lilypond']['progs'] as $setting_name => $program)
		$output .= parent::program_setting_entry (
			$program['prog_name'], $setting_name);
	return $output;
}

/**
 * Refer to {@link ScoreRender_Notation::define_setting_type() interface method}
 * for more detail.
 */
public static function define_setting_type ($settings)
{
	global $notations;

	$settings += $notations['lilypond']['progs'];
}

} // end of class


$notations['lilypond'] = array (
	'name'        => 'LilyPond',
	'url'         => 'http://www.lilypond.org/',
	'regex'       => '~\[lilypond\](.*?)\[/lilypond\]~si',
	'starttag'    => '[lilypond]',
	'endtag'      => '[/lilypond]',
	'classname'   => 'lilypondRender',
	'progs'       => array (
		'LILYPOND_BIN' => array (
			'prog_name' => 'lilypond',
			'type'      => 'prog',
			'value'     => '',
			'test_arg'  => '--version',
			'test_output' => 'GNU LilyPond',
		),
	),
);


add_action ('scorerender_define_adm_msgs',
	array( 'lilypondRender', 'define_admin_messages' ) );

add_action ('scorerender_check_notation_progs',
	array( 'lilypondRender', 'is_notation_usable' ), 10, 2 );

add_filter ('scorerender_prog_and_file_loc',
	array( 'lilypondRender', 'program_setting_entry' ) );

add_filter ('scorerender_define_setting_type',
	array( 'lilypondRender', 'define_setting_type' ) );
?>
