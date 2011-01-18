<?php defined('SYSPATH') or die('No direct script access.');

class View_Test extends Kostache
{
	public $text;

	public function _initialize()
	{
		Assets::add_group('lsu');
		parent::_initialize();
	}

	public function test()
	{
		return $this->text ? 'poop lol' : 'butts lol';
	}

	public function image()
	{
		return 'data:image/png;base64,'.base64_encode(file_get_contents(Kohana::find_file('media/images','signed_with_stamp2', 'png')));
	}
}
