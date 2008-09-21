<?php
/**
 * Implements rendering of GUIDO notation in ScoreRender.
 * @package ScoreRender
*/

/**
 * Inherited from ScoreRender class, for supporting GUIDO notation.
 * @package ScoreRender
*/
class guidoRender extends ScoreRender
{

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
public function get_music_fragment ()
{
	return $this->_input;
}

/**
 * Render raw input file into PostScript file.
 *
 * @param string $input_file File name of raw input file containing music content
 * @param string $intermediate_image File name of rendered PostScript file
 * @return boolean Whether rendering is successful or not
 */
protected function conversion_step1 ($input_file, $intermediate_image)
{
	// 1.125 = 72/64; guido server use 64pixel per cm
	$url = sprintf ('%s?defpw=%fcm;defph=%fcm;zoom=%f;crop=yes;gmndata=%s',
			'http://clef.cs.ubc.ca/scripts/salieri/gifserv.pl',
			$this->img_max_width / DPI * 2.54, 100.0, 1.125,
			rawurlencode (file_get_contents ($input_file)));

	return (@copy ($url, $intermediate_image));
}

/**
 * @param string $intermediate_image The rendered PostScript file name
 * @param string $final_image The final PNG image file name
 * @return boolean Whether conversion from PostScript to PNG is successful
 * @access protected
 */
protected function conversion_step2 ($intermediate_image, $final_image)
{
	// Under Windows, percent sign must be escaped
	if (is_windows ())
		return parent::conversion_step2 ($intermediate_image, $final_image, FALSE,
			'-shave 1x1 -geometry 56%%');
	else
		return parent::conversion_step2 ($intermediate_image, $final_image, FALSE,
			'-shave 1x1 -geometry 56%');
}

/**
 * Check if fopen() supports remote file.
 *
 * @return boolean Return true if remote URL can be fopen'ed.
 */
public function is_notation_usable ($args = '')
{
	return ini_get('allow_url_fopen');
}

} // end of class

?>
