<?php defined('SYSPATH') or die('No direct access allowed.');

class Synapse_Pdftk {

	/**
	 * Splits a multi-page pdf into a directory of single pages
	 *
	 * @param string $pdf path to the pdf file
	 */
	public static function burst($pdf) {}

	/**
	 * Concatenates two PDFs or Kostache objects together
	 *
	 * @param mixed $file_a A PDF, Kostache object, or array of Kostache
	 * @param mixed $file_b A PDF, Kostache object, array of Kostache
	 * @param string $order An optional ordering string
	 */
	public static function cat($file_a, $file_b)
	{
		try
		{
			$first  = Pdftk::parse_input($file_a);
			$second = Pdftk::parse_input($file_b);

			$output_filename = Pdftk::unique_filename('pdf');

			$pdftk = Kohana::config('pdf.pdftk.path');
			if ($pdftk == FALSE)
			{
				throw new RuntimeException('Unconfigured or incorrect Pdftk path. Set config \'pdf.pdftk.path\''.' for this environment.');
			}

			$command = escapeshellarg($pdftk).' 2>&1 A='.escapeshellarg($first).' B='.  escapeshellarg($second).' cat A B output '.  escapeshellarg($output_filename);

			ob_start();
			$last_line = system($command, $return);
			$results = ob_get_contents();
			ob_end_clean();

			if ($return != 0)
			{
				throw new PDF_Exception('Pdftk returned status '.$return.'. Command was: `'.$command.'`. Output: '.$results, $return);
			}

			// Do garbage collection
			if ($file_a instanceof Kostache OR $file_a instanceof View)
			{
				unlink($first);
			}

			if ($file_b instanceof Kostache or $file_b instanceof View)
			{
				unlink($second);
			}
		}
		catch (Exception $e)
		{
			Kohana::$log->add(Kohana::ERROR, 'Problem generating PDF. '.$e);

			// Throw a PDF_Exception rather than a generic Exception
			if (get_class($e) !== 'pdf_exception')
			{
				throw new PDF_Exception('PDF generation error', 0, $e);
			}
			else
			{
				throw $e;
			}
		}

		return $output_filename;
	}

	/**
	 *
	 * @param array $input An array of PDF files or Kostache objects
	 * @param string $order Optional ordering pattern
	 * @return string Path the concatenated PDF
	 */
	public static function array_cat($input, $order = NULL)
	{
		try
		{
			$pages = array();

			foreach ($input as $key => $i)
			{
				if (is_int($key))
				{
					$pages[chr($key+65)] = Pdftk::parse_input($i);
				}
				else
				{
					$pages[$key] = Pdftk::parse_input($i);
				}
			}

			$output_filename = Pdftk::unique_filename('pdf');

			$pdftk = Kohana::config('pdf.pdftk.path');
			if ($pdftk == FALSE)
			{
				throw new RuntimeException('Unconfigured or incorrect Pdftk path. Set config \'pdf.pdftk.path\''.' for this environment.');
			}

			$command = escapeshellarg($pdftk).' 2>&1 ';

			foreach ($pages as $key => $page)
			{
				$command .= escapeshellarg($key).'='.escapeshellarg($page).' ';
			}

			$command .= 'cat ';

			// If the ordering was not set by
			if ( ! $order)
			{
				foreach ($pages as $key => $page)
				{
					$command .= escapeshellarg($key).' ';
				}
			}
			else
			{
				$command .= escapeshellarg($order).' ';
			}

			$command  .= 'output '.escapeshellarg($output_filename);

			ob_start();
			$last_line = system($command, $return);
			$results = ob_get_contents();
			ob_end_clean();

			if ($return != 0)
			{
				throw new PDF_Exception('Pdftk returned status '.$return.'. Command was: `'.$command.'`. Output: '.$results, $return);
			}

			// Do garbage collection
			foreach ($input as $key => $i)
			{
				if ($i instanceof Kostache OR $i instanceof View)
				{
					if (is_int($key))
					{
						unlink($pages[chr($key+65)]);
					}
					else
					{
						unlink($pages[$key]);
					}
				}
			}
		}
		catch (Exception $e)
		{
			Kohana::$log->add(Kohana::ERROR, 'Problem generating PDF. '.$e);

			// Throw a PDF_Exception rather than a generic Exception
			if (get_class($e) !== 'pdf_exception')
			{
				throw new PDF_Exception('PDF generation error', 0, $e);
			}
			else
			{
				throw $e;
			}
		}

		return $output_filename;
	}

	/**
	 * Takes a Kostache object or array of objects and creates a PDF using
	 * wkhtmltopdf
	 *
	 * The second param is an array that can have any of the options listed in
	 * `wkhtmltopdf --extended-help` and will be printed as `--<key> <value>`
	 * For params with no options, set value to TRUE, for params with one
	 * option, set it as the value, for params with two or more options, pass
	 * an array as the value, for repeatable params, pass an array of key value
	 * pairs.
	 * 
	 * For example
	 *
	 * array(
	 *  'toc' => TRUE, // Set value as true for args with no option, eg --toc
	 * 	'copies' => '2',
	 * );
	 *
	 * Note: some elements are treated special, such as replace, see
	 * the code for more info on them
	 * 
	 * @param Kostache $template or array of Kostache objects
	 * @param Array Array of options for the renderer
	 * @return string $pdf_filename path the the pdf file
	 */
	public static function render_pdf($pages = array(), $options = array())
	{
		// Get input in correct format
		if ( ! is_array($pages))
		{
			$pages = array($pages);
		}

		foreach ($pages as $template)
		{
			if ( ! $template instanceof Kostache)
			{
				throw new PDF_Exception('Invalid Kostache Object');
			}

			$html = $template->render();

			try
			{
				$html_filename = Pdftk::unique_filename('html');

				$written = file_put_contents($html_filename, $html);

				if ( ! $written)
				{
					throw new RuntimeException('Unable to write to file '.$html_filename);
				}

				$html_files[] = $html_filename;
				unset($html);
			}
			catch (Exception $e)
			{
				// Throw a PDF_Exception rather than a generic Exception
				if (get_class($e) !== 'pdf_exception')
				{
					throw new PDF_Exception('PDF generation error', 0, $e);
				}
				else
				{
					throw $e;
				}
			}
		}

		try
		{
			$pdf_filename = Pdftk::unique_filename('pdf');

			$wkhtmltopdf = Kohana::config('pdf.wkhtmltopdf.path');
			if ($wkhtmltopdf == FALSE)
			{
				throw new RuntimeException('Unconfigured or incorrect wkhtmltopdf path. Set config \'pdf.wkhtmltopdf.path\''.' for this environment.');
			}

			$command = escapeshellarg($wkhtmltopdf).' 2>&1 ';
			
			// Add options from the options array
			foreach ($options as $key => $value)
			{
				// Check for any keys that need special treatment
				if ($key == 'replace')
				{
					// For replace the value is an array of search => replace pairs
					foreach ($value as $search => $replace)
					{
						$command .= '--'.$key.' '.escapeshellarg($search).' '.escapeshellarg($replace).' ';
					}
				}
				// To other developers: If you need to add special treatment for any params, put them above this line
				// Standard params of 0, 1 and 2+ options below here
				elseif ($value === TRUE)
				{
					// Standard param with no options
					$command .= '--'.$key.' ';
				}
				elseif (is_array($value))
				{
					// Standard param with 2 or more options, eg --replace <name> <value>
					$command .= '--'.$key.' ';
					foreach($value as $param)
					{
						$command .= escapeshellarg($param).' ';
					}
				}
				else
				{
					// Standard param with one option eg --key <value>
					$command .= '--'.$key.' '.escapeshellarg($value).' ';
				}
			}

			// Add input files to the command
			foreach ($html_files as $filename)
			{
				$command .= escapeshellarg($filename).' ';
			}

			// Add output file to the command
			$command .= escapeshellarg($pdf_filename);

			ob_start();
			$last_line = system($command, $return);
			$results = ob_get_contents();
			ob_end_clean();

			// Do garbage collection
			foreach ($html_files as $filename)
			{
				unlink($filename);
			}

			if ($return != 0)
			{
				throw new PDF_Exception('wkhtmltopdf returned status '.$return.'. Command was: `'.$command.'`. Output: '.$results, $return);
			}
		}
		catch (Exception $e)
		{
			Kohana::$log->add(Log::ERROR, 'Problem generating PDF. '.$e);

			// Throw a PDF_Exception rather than a generic Exception
			if (get_class($e) !== 'pdf_exception')
			{
				throw new PDF_Exception('PDF generation error'.$e->getMessage() , 0, $e);
			}
			else
			{
				throw $e;
			}
		}

		return $pdf_filename;
	}

	/**
	 * Stamps file_a on top of file_b. Supports single- and multi-page PDFs
	 * and arrays of Mostache objects
	 *
	 * @param mixed $file_a The foreground file
	 * @param mixed $file_b The background file
	 * @return string pdf The stamped PDF
	 */
	public static function stamp($file_a, $file_b, $options = array())
	{
		try
		{
			$options_a = array();
			$options_b = array();

			if (array_key_exists('0', $options))
			{
				$options_a = $options[0];
			}
			if (array_key_exists('1', $options))
			{
				$options_b = $options[1];
			}

			$foreground = Pdftk::parse_input($file_a, $options_a);
			$background = Pdftk::parse_input($file_b, $options_b);

			$output_filename = Pdftk::unique_filename('pdf');

			$pdftk = Kohana::config('pdf.pdftk.path');
			if ($pdftk == FALSE)
			{
				throw new RuntimeException('Unconfigured or incorrect Pdftk path. Set config \'pdf.pdftk.path\''.' for this environment.');
			}

			$command = escapeshellarg($pdftk).' 2>&1 '.escapeshellarg($background).' stamp '.escapeshellarg($foreground).' output '.escapeshellarg($output_filename);

			ob_start();
			$last_line = system($command, $return);
			$results = ob_get_contents();
			ob_end_clean();

			if ($return != 0)
			{
				throw new PDF_Exception('Pdftk returned status '.$return.'. Command was: `'.$command.'`. Output: '.$results, $return);
			}

			// Do garbage collection
			if ($file_a instanceof Kostache OR $file_a instanceof View)
			{
				unlink($foreground);
			}

			if ($file_b instanceof Kostache or $file_b instanceof View)
			{
				unlink($background);
			}
		}
		catch (Exception $e)
		{
			Kohana::$log->add(Kohana::ERROR, 'Problem generating PDF. '.$e);

			// Throw a PDF_Exception rather than a generic Exception
			if (get_class($e) !== 'pdf_exception')
			{
				throw new PDF_Exception('PDF generation error', 0, $e);
			}
			else
			{
				throw $e;
			}
		}

		return $output_filename;
	}

	/**
	 * Parses the various input types into a useable format;
	 *
	 * @param mixed $input Path to PDF, Kostache object, or array of either;
	 * @return string Path to the PDF file or array of paths
	 */
	public static function parse_input($input, $options = array())
	{
		if (is_string($input))
		{
			if (file_exists($input) AND (File::mime($input) === File::mime_by_ext('pdf')))
			{
				return $input;
			}
		}
		elseif ($input instanceof Kostache OR $input instanceof View)
		{
			return Pdftk::render_pdf($input, $options);
		}

		throw new PDF_Exception('Invalid input: '.$input);
	}

	/**
	 * Creates and returns a unique filename with the specified extension
	 *
	 * @param <type> $ext
	 * @return string
	 */
	public static function unique_filename($ext = 'tmp')
	{
		do
		{
			$unique_filename = realpath(sys_get_temp_dir()).'/'.Text::random().'.'.$ext;
		}
		while (file_exists($unique_filename));

		return $unique_filename;
	}
}
