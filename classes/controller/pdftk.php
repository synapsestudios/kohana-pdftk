<?php defined('SYSPATH') or die('No direct access allowed.');

class Controller_Pdftk extends Controller
{
	public function action_index()
	{
		$view = Kostache::factory('test');
		$view_also = Kostache::factory('test');
		$mlu = Kohana::find_file('media/pdf', 'mlu', 'pdf');

		$pdf = Pdftk::render_pdf(array($view, $view_also));
		$this->request->send_file($pdf);
		unset($pdf);
	}
}
