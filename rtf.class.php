<?php
class RTF
{
	static function Text($rtf)
	{
		$text = preg_replace('~(\\\[a-z0-9*]+({[^}]*}){0,1}([ a-z0-9]*;{1}){0,1})~si', '', $rtf);
		$find    = array("\r", "\n", '\line ', '{', '}');
		$replace = array('', '', "\r\n", '', '');

		$text = str_replace($find, $replace, $text);
		return preg_replace_callback("~\\\'([0-9a-f]{2})~", array('RTF', 'convert'), trim($text));
		//return preg_replace("~\\\\\\'([0-9a-f]{2})~", chr("0x$1"), $text);
	}

	static function convert($symbol)
	{
		return chr(hexdec($symbol[1]));
	}
}