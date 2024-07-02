<?php

	/*
	*
	* PHP version 5
	*
	* LICENSE: This source file is subject to version 3.01 of the PHP license
	* that is available through the world-wide-web at the following URI:
	* http://www.php.net/license/3_01.txt.  If you did not receive a copy of
	* the PHP License and are unable to obtain it through the web, please
	* send a note to license@php.net so we can mail you a copy immediately.
	*
	* @package   EllipseArcMegatest
	* @author    Austin Ekwebelam <aekwebelam@gmail.com>
	* @copyright 1997-2011 The PHP Group
	* @license   http://www.php.net/license/3_01.txt  PHP License 3.01
	* @version   12
	*
	*/

	//
	// This program draws various shapes on a canvas and outputs them to a file
	//
	// Read the "megatest_samples.htm" file for details on how to use this program
	//


	date_default_timezone_set( 'Australia/Melbourne' );


	$script_args = $argv;
	array_shift( $script_args );	// $argv[0] is always the script's own name.  I don't need it



	$data_file_name = "data_draw_polygon.txt";

	$delimiter = "::::";

	//
	// Get all the directory files
	//

	$dir = ".";

	$dir_files = array();
	$files_to_process = array();


	// Open the current directory, and proceed to read its contents
	if ( is_dir( $dir ) )
	{
		if ( $dh = opendir( $dir ) )
		{
			while ( ( $file = readdir( $dh ) ) !== false )
			{

				$filename_search_results_pos = stripos( $file , "megatest_input_file" );

				if (
					is_file( $file ) &&								// We are looking at a file
					( $filename_search_results_pos !== false ) &&	// It contains "megatest_input_file"
					( $filename_search_results_pos == 0 ) &&		// "megatest_input_file" is in the zeroth position
					preg_match( "/\.txt$/i" , $file )				// The file ends with ".txt"
				)
				{
					// The file is good for processing

					$dir_files[] = $file;
				}
			}
			closedir($dh);
		}
	}




	if ( sizeof( $script_args ) )
	{
		//
		// We only want to generate images for some of the megatest entries, not all of them
		//

		foreach ( $script_args as $idxs => $input_num )
		{
			$padded_num = preg_replace( "/^0/" , "" , $input_num );
			$padded_num = ( $padded_num < 10 ) ? "0" . $padded_num : $padded_num;







			$target_entry_prefix = "megatest_input_file" . $padded_num;


			foreach ( $dir_files as $idxd => $curr_file )
			{
				//
				// I use the following steps to find the desired file
				//
				// (1) Store $curr_file in temporary variable $temp_string
				// (2) Remove the $target_entry_prefix from the beginning of $temp_string.
				//     This way the remaining string of desired files will either be ".txt"
				//     or will be "a.txt", "b.txt" etc.  Note that I do NOT want "0.txt",
				//     because if I am working with megatest_input_file10.txt", I will not
				//     want "megatest_inputfile100.txt" to show up but I want
				//     to get "megatest_inputfile10a.txt", "megatest_inputfile10b.txt" etc.
				// (3) if the remaining contents of $temp_string contains "/[a-zA-Z]?\.txt/", then
				//     this is a desired file
				//


				$temp_string = str_replace( $target_entry_prefix , "" , $curr_file );


				if ( preg_match( "/^[a-zA-Z]?\.txt$/" , $temp_string ) )
				{
					// The file is good for processing

					$files_to_process[] = $curr_file;


				}
			}
		}
	}
	else
	{
		$files_to_process = $dir_files;
	}




	foreach ( $files_to_process as $idxf => $input_file_name )
	{

		if ( file_exists( $input_file_name ) )
		{
			$input_file_contents = file_get_contents( $input_file_name );

			$dummy_output_image_file_name = preg_replace( "/^megatest_input_file/i" , "" , $input_file_name );
			$dummy_output_image_file_name = preg_replace( "/\.txt$/i" , ".png" , $dummy_output_image_file_name );



			//
			// Find the "canvas line", if present
			//

			$temp_contents = "|" . $input_file_contents;
			$temp_contents = str_ireplace( "bgimgdims" , "canvas" , $input_file_contents );

			$temp_array = explode( "|" , $temp_contents );

			$image_file_line = "imagefile=" . $dummy_output_image_file_name;	// default

			$canvas_line = "canvas=png=710=710=#ffff00=0=0=100=100";	// default

			foreach ( $temp_array as $curr_obj_line )
			{


				$curr_obj_line = trim( $curr_obj_line );

				$canvas_search_results_pos = stripos( $curr_obj_line , "canvas=" );

				$image_file_search_results_pos = stripos( $curr_obj_line , "imagefile=" );

				if ( $canvas_search_results_pos !== false )
				{
					// The pattern is somewhere in this line.  Verify that it is in the 0th position
					if ( $canvas_search_results_pos == 0 )
					{
						$canvas_line = $curr_obj_line;
					}
				}

				if ( $image_file_search_results_pos !== false )
				{
					// The pattern is somewhere in this line.  Verify that it is in the 0th position
					if ( $image_file_search_results_pos == 0 )
					{
						$image_file_line = $curr_obj_line;
					}
				}
			}

			list(
				$canvas_label ,
				$image_type ,
				$image_width ,
				$image_height ,
				$image_bg_colour ,
				$transparency ,
				$rotation ,
				$shrink_width ,
				$shrink_height ,
			)= explode( "=" , $canvas_line );

			$input_file_appendage = implode( $delimiter , array(
															"|" ,
															$image_type ,
															$image_width ,
															$image_height ,
															$image_bg_colour ,
															$transparency ,
															$rotation ,
															$shrink_width ,
															$shrink_height ,
														)
											);



			list ( $image_file_label , $output_image_file_name ) = explode( "=" , $image_file_line );



			$output_image_file_name = preg_replace( "/png$/i" , $image_type , $output_image_file_name );
			$output_image_file_name = preg_replace( "/\"/" , "" , $output_image_file_name );
			$output_image_file_name = preg_replace( "/\//" , "__" , $output_image_file_name );

			echo "Processing [" . $input_file_name . "] to generate [" . $output_image_file_name . "] ...";





			$fp = fopen( $data_file_name , 'w' );
			fwrite( $fp , "$input_file_contents" );
			fwrite( $fp , "$input_file_appendage" );
			fclose( $fp );


			$start_time = microtime();


			`php draw_polygonnocalcs.php > "$output_image_file_name"`;


			$finish_time = microtime();

			$duration = $finish_time - $start_time;

			echo "duration = [" . $duration . "] seconds\n";
		}
		else
		{
			echo "ERROR! Could not find input file [" . $input_file_name . "]\n";
		}
	}

?>


