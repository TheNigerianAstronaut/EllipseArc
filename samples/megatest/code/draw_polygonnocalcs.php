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
	* @package   Megatest-Drawpolygon
	* @author    Austin Ekwebelam <aekwebelam@gmail.com>
	* @copyright 1997-2011 The PHP Group
	* @license   http://www.php.net/license/3_01.txt  PHP License 3.01
	* @version   12
	*
	*/

	//
	// This program draws various shapes on a canvas.
	//
	// It is called by the "megatest.php" program
	//
	//


	// Report all PHP errors (see changelog)
	error_reporting(E_ALL);


	require_once 'EllipseArc.php';

	set_time_limit(80);


$show_calculations = 0;

$calculations = "some mighty calcs here";



	$filename = "data_draw_polygon.txt";

	$delimiter = "::::";

	list(
		$final_string ,
		$img_type ,
		$img_width ,
		$img_height ,
		$img_color_text ,
		$img_pct_trans ,
		$img_rotate ,
		$obj_colors ,
	) = explode($delimiter , file_get_contents($filename));








$calculations .= "
=========
final_string = [\n" .
$final_string .
"],
===================
";



	$multi_comment_start = "/*";
	$multi_comment_end = "*/";
	$single_comment = "//";


	//
	// Do the multi-line ignore (ie "/*...*/") here
	//

	if ( strstr($final_string, $multi_comment_start) !== false)
	{
		//
		// There is a multi-line comment.
		// Find the ending
		//

		$final_string = "|" . $final_string;

		$comment_start = strpos($final_string, $multi_comment_start);
		$comment_end = strpos($final_string, $multi_comment_end, $comment_start);




		if ( $comment_end === false )
		{
			//
			// Everything to the end will be commented out
			//

			$final_string .= "*" . "/";	// To stop TextPad from thinking this is a comment

		}
		$final_string = $final_string . "|";




		$comment_start = strpos($final_string, $multi_comment_start);
		$comment_end = strpos($final_string, $multi_comment_end, $comment_start);





		$final_string = preg_replace("/\|.+\/\*/" , "|" . $multi_comment_start , $final_string, -1);
		$final_string = preg_replace("/\*\/.+\|/" , "*" . "/" . "|" , $final_string, -1);






		$comment_start = strpos($final_string, $multi_comment_start);





	}


$calculations .= "
=========
After removing multi-line to the very end, final_string = [\n" .
$final_string .
"],
===================
";



	//
	// Now get the good lines to process out of $final_string
	//

	$internal_objs = explode("|" , $final_string);

	$num_objs = sizeof($internal_objs);

	$entries_to_process = array();

	$multi_ignore = 0;

	for ($i = 0; $i < $num_objs; $i++)
	{
		if ( strstr($internal_objs[$i], $multi_comment_start ) !== false)
		{
			//
			// This line contains the beginning of a multi-line comment
			//

			$multi_ignore = 1;
		}

		if ( strstr($internal_objs[$i], $multi_comment_end ) !== false)
		{
			//
			// This line contains the end of a multi-line comment.
			// skip this line, but set the next line as valid to
			// process
			//

			$multi_ignore = 0;

			continue;
		}

		if ($multi_ignore)
		{
			continue;
		}

		if ( strstr($internal_objs[$i], $single_comment ) !== false)
		{
			//
			// This line contains a single-line comment.
			// skip this line
			//

			continue;
		}


		$entries_to_process[] = $internal_objs[$i];

	}

	$final_string = implode("|" , $entries_to_process);



$calculations .= "
=========
After completely cleaning up, final_string = [\n" .
$final_string .
"],
===================
";















	$temp_string_change = "|" . $final_string;


	$temp_string_change = preg_replace("/\s+/" , "" , $temp_string_change);


	$temp_string_change = preg_replace("/\|\/\/.?\|/" , "|" , $temp_string_change);


	$temp_string_change = preg_replace("/\|+/" , "|" , $temp_string_change);


	$temp_string_change = str_replace("bgimgdims" , "canvas" , $temp_string_change);


	$temp_string_change = preg_replace("/^.*\|canvas=/" , "canvas=" , $temp_string_change);


	$temp_string_array = explode("|" , $temp_string_change);

$calculations .= "
=========
007 temp_string_change = [" .$temp_string_change ."],
temp_string_array[0] = [" .$temp_string_array[0] ."],
sizeof(temp_string_array) = [" .sizeof($temp_string_array) ."],
sizeof(temp_string_array[0]) = [" .sizeof($temp_string_array[0]) ."],
is_null(temp_string_array[0]) = [" .is_null($temp_string_array[0]) ."],
===================
";


	if (strstr($temp_string_array[0], "canvas=") === false)
	{
		//
		// The user did not define any canvas details.
		// Add some in for him
		// Give it the green colour so it is clear
		// that it was added here and not by "polygons 88.php"
		//

$calculations .= "
=========
008 creating a dummy canvas for temp_string_array[0]
===================
";


		$temp_string_array[0] = implode('=',
			array(
				"canvas",
				"png",
				"200",
				"200",
				"#ff0000",
				"0",
				"0",
				"200",
				"200"
			)
		);

	}

	list(
		$canvas_label ,
		$img_type ,
		$img_width ,
		$img_height ,
		$img_color_text ,
		$img_pct_trans ,
		$img_rotate ,
		$img_shrink_width ,
		$img_shrink_height ,
	) = explode('=' , $temp_string_array[0]);




$running_raw_calculations_file_name = "running_raw_calcs_polygon.txt";
$fp = fopen($running_raw_calculations_file_name , 'w');
fwrite($fp , "$calculations");
fclose($fp);



	$image_width = $img_width;
	$image_height = $img_height;




	$font_dir = "D:/zzWebsites/example/fonts/system_fonts/";
	$font_array = array();

	$font_array[] = "courbd";	//Courier Bold



	//
	// If the user has not supplied any parameters, then produce no useful image and exit
	//

	if ((!$image_width) || (!$image_height))
	{
		$bad_width = 300;
		$bad_height = 80;

		$bad_text0 = "width = [$image_width], height = [$image_height]";
		$bad_text1 = "Sorry, invalid parameters!";

		$bad_image = imagecreatetruecolor($bad_width , $bad_height);

		$bad_image_bgcolor = imagecolorallocate($bad_image , 0xff , 0x00 , 0x00);


		$font_file = $font_dir . $font_array[0] . ".ttf";


		// Make the background red
		imagefilledrectangle($bad_image , 0 , 0 , ($bad_width - 1) , ($bad_height - 1) , $bad_image_bgcolor);



		$textcolor = imagecolorallocate($bad_image , 0x00 , 0x00 , 0x00);


		//
		// Draw the character
		//

		imagefttext($bad_image , 10 , 0 , 20 , 20 , $textcolor , $font_file , $bad_text0);
		imagefttext($bad_image , 10 , 0 , 20 , 40 , $textcolor , $font_file , $bad_text1);

		// Output image to the browser
		header('Content-Type: image/png');

		imagepng($bad_image);
		imagedestroy($bad_image);

		exit;
	}







	//
	// Now Draw the Image
	//


	$image = imagecreatetruecolor($image_width , $image_height);

	$img_color_rgb = GetColorComponents($img_color_text);


	$image_width_minus_1 = $image_width - 1;
	$image_height_minus_1 = $image_height - 1;


	$fully_transparent_color_text = "";
	$fully_transparent_color_defined = 0;

	if ($img_pct_trans == 100)
	{
		// The background image was set to be 100% transparent
		$fully_transparent_color_text = $img_color_text;
		$fully_transparent_color_defined = 1;
	}

	$final_img_color = imagecolorallocate($image , $img_color_rgb[0] , $img_color_rgb[1] , $img_color_rgb[2]);



$mytest = ($img_pct_trans == "") ? "empty" : "something";
$mytest2 = ($img_pct_trans == "#000000") ? "allzeros" : $img_pct_trans;

$calculations .= "
=========
img_color_text = [" . $img_color_text . "],
img_pct_trans = [" . $img_pct_trans . "],
mytest = [" . $mytest . "],
mytest2 = [" . $mytest2 . "],
===================
";



	//
	// Fill in the background canvas
	//

	if (
		!(
			(
				($img_color_text == "000000") ||
				($img_color_text == "#000000") ||
				($img_color_text == "#000")
			) &&
			(
				($img_pct_trans == null) ||
				($img_pct_trans == 0) ||
				($img_pct_trans == "")
			)
		)
	)
	{
		//
		// The user definitely wants a colour.  The system just uses black if no colour is supplied
		//
		// NOTE:
		// Adding background colours slows the system down and can cause it to run out of memory.
		//

		imagefilledrectangle($image , 0 , 0 , $image_width_minus_1 , $image_height_minus_1 , $final_img_color);
	}




	//
	// Now process the internal objects
	//

	$internal_objs = explode("|" , $final_string);


	$num_objs = sizeof($internal_objs);



	//
	// On 20110823 I added some weird bug that causes a newline to appear before each field
	// the array_walk fixes this issue
	//

	array_walk($internal_objs , 'trim_value');


	//
	// Find all the MOVE operations in this image.
	//

	$move_operations = array();
	$move_group = array();


	for ($i = 0; $i < $num_objs; $i++)
	{

$calculations .= "
=========
Movement and variable scan for internal_objs[$i] = [" . $internal_objs[$i] . "],
===================
";

		$temp_obj_details = explode("=" , $internal_objs[$i]);

		$temp_obj_name = array_shift($temp_obj_details);


$calculations .= "
=========
temp_obj_name = [" . $temp_obj_name . "],
===================
";



		if (strtolower($temp_obj_name) == "move")		// used to move a specific object
		{
			$temp_target_details = explode("," , $temp_obj_details[0]);

			$temp_target_obj = array_shift($temp_target_details);		// The name of the specific object we want to operate on


$calculations .= "
=========
move operation
temp_obj_name = [" . $temp_obj_name . "],
temp_obj_details = [" . $temp_obj_details . "],
temp_target_obj = [" . $temp_target_obj . "],
temp_target_details[0] = [" . $temp_target_details[0] . "],
temp_target_details[1] = [" . $temp_target_details[1] . "],
===================
";

			if (array_key_exists($temp_target_obj , $move_operations))
			{
				// Add the x-y coordinates to the existing entry

				$move_operations[$temp_target_obj][0] += $temp_target_details[0];		// The x-move
				$move_operations[$temp_target_obj][1] += $temp_target_details[1];		// The y-move
			}
			else
			{
				// It is a new entry

				$move_operations[$temp_target_obj][0] = $temp_target_details[0];		// The x-move
				$move_operations[$temp_target_obj][1] = $temp_target_details[1];		// The y-move
			}

		}
		elseif (strtolower($temp_obj_name) == "moveall")
		{
			// used to move all objects in the image

			$temp_target_details = explode("," , $temp_obj_details[0]);

			$temp_target_obj = "moveall";


$calculations .= "
=========
moveall
temp_obj_name = [" . $temp_obj_name . "],
temp_obj_details = [" . $temp_obj_details . "],
temp_target_obj = [" . $temp_target_obj . "],
temp_target_details[0] = [" . $temp_target_details[0] . "],
temp_target_details[1] = [" . $temp_target_details[1] . "],
===================
";


			if (array_key_exists($temp_target_obj , $move_operations))
			{
				// Add the x-y coordinates to the existing entry

				$move_operations[$temp_target_obj][0] += $temp_target_details[0];		// The x-move
				$move_operations[$temp_target_obj][1] += $temp_target_details[1];		// The y-move
			}
			else
			{
				// It is a new entry

				$move_operations[$temp_target_obj][0] = $temp_target_details[0];		// The x-move
				$move_operations[$temp_target_obj][1] = $temp_target_details[1];		// The y-move
			}


		}
		elseif (strtolower($temp_obj_name) == "movegroup")	// used to move objects that have a matching prefix
		{
			$temp_target_details = explode("," , $temp_obj_details[0]);

			$temp_target_obj = array_shift($temp_target_details);		// The name of the specific object we want to operate on


$calculations .= "
=========
movegroup
temp_obj_name = [" . $temp_obj_name . "],
temp_obj_details = [" . $temp_obj_details . "],
temp_target_obj = [" . $temp_target_obj . "],
temp_target_details[0] = [" . $temp_target_details[0] . "],
temp_target_details[1] = [" . $temp_target_details[1] . "],
===================
";

			if (array_key_exists($temp_target_obj , $move_group))
			{
				// Add the x-y coordinates to the existing entry

				$move_group[$temp_target_obj][0] += $temp_target_details[0];		// The x-move
				$move_group[$temp_target_obj][1] += $temp_target_details[1];		// The y-move
			}
			else
			{
				// It is a new entry

				$move_group[$temp_target_obj][0] = $temp_target_details[0];		// The x-move
				$move_group[$temp_target_obj][1] = $temp_target_details[1];		// The y-move
			}

		}
		elseif (
			(strtolower($temp_obj_name) == "var")	||
			(strtolower($temp_obj_name) == "variable")
		)
		{
			$temp_target_details = explode("," , $temp_obj_details[0]);

			$temp_target_obj = array_shift($temp_target_details);		// The name of the specific object we want to operate on


$calculations .= "
=========
variable
temp_obj_name = [" . $temp_obj_name . "],
temp_obj_details = [" . $temp_obj_details . "],
temp_target_obj = [" . $temp_target_obj . "],
temp_target_details[0] = [" . $temp_target_details[0] . "],
===================
";
			$variable_list[$temp_obj_name] = $temp_target_details[0];

		}
	}




	$border_defined = 0;


	for ($i = 0; $i < $num_objs; $i++)
	{

$calculations .= "
=========
Initial scan for internal_objs[$i] = [" . $internal_objs[$i] . "],
===================
";

		if ($internal_objs[$i] == "")
		{
			//
			// empty string
			//

			continue;
		}
		if (
			(stripos($internal_objs[$i] , "bgimgdims=") !== false) ||
			(stripos($internal_objs[$i] , "canvas=") !== false)
		)
		{
			// Note the use of "!==" instead of the normal "==" in the comparison just above.
			// This is required by stripos, strpos etc in case the position of the desired string is "0"
			//

			//
			// Anyway, do not process details for the background image dimensions because they were
			// processed before this for loop
			//

			continue;
		}
		if (stripos($internal_objs[$i] , "//") !== false)
		{
			// The user wants to ignore this entry

			continue;
		}
		if (
			(stripos($internal_objs[$i] , "move=") !== false) ||
			(stripos($internal_objs[$i] , "movegroup=") !== false) ||
			(stripos($internal_objs[$i] , "moveall=") !== false)
		)
		{
			//
			// The word "move" is in the very first position
			//
			// This is a "move" operation, which is only applicable to another object in the image.
			// ignore this entry

			continue;
		}
		if (
			(stripos($internal_objs[$i] , "var=") !== false) ||
			(stripos($internal_objs[$i] , "variable=") !== false)
		)
		{
			//
			// This is a "variable" definition, which is only applicable to another object in the image.
			// ignore this entry

			continue;
		}
		if (
			(stripos($internal_objs[$i] , "imagefile=") !== false)
		)
		{
			//
			// This is a "imagefile" definition, which is the name of the output file.  Only megatest02.php uses this line.
			// ignore this entry

			continue;
		}
		if (
			(stripos($internal_objs[$i] , "imageborder=") !== false)
		)
		{
			//
			// This is a "imageborder" definition, which specifies a border for the output file.
			// We only need the colour, transparency and thickness
			//

			$border_defined = 1;

			$border_line = $internal_objs[$i];	// oh, what a pun!

			continue;
		}



		//
		// Process this shape
		//

		$obj_details = explode("," , $internal_objs[$i]);

		$obj_id_color = array_shift($obj_details);
		$obj_id_color = preg_replace("/\s+/" , "" , $obj_id_color);	//remove any whitespace in the line

		$obj_info = explode("=" , $obj_id_color);

		$obj_name = $obj_info[0];

		$objColor_rgb = GetColorComponents($obj_info[1]);

		if (sizeof($obj_info) > 2)
		{
			$obj_pct_transparency = $obj_info[2];
		}
		else
		{
			$obj_pct_transparency = 0;
		}




$calculations .= "
=========
Part 1
internal_objs[$i] = [" . $internal_objs[$i] . "],
obj_id_color = [" . $obj_id_color . "],
obj_name = [" . $obj_name . "],
obj_info[0] = [" . $obj_info[0] . "],
obj_info[1] = [" . $obj_info[1] . "],
obj_info[2] = [" . $obj_info[2] . "],
obj_info[3] = [" . $obj_info[3] . "],
obj_id_color = [" . $obj_id_color . "],
===================
";





		if ($obj_pct_transparency == 100)
		{
			// This colour was set to be 100% transparent

			if ( $fully_transparent_color_defined )
			{
				// Another colour has already been defined as being 100% transparent.
				// Assign that previous colour to this object as well
				//

			}
			else
			{
				//
				// Set this object's colour as the 100% transparent colour
				//

				$fully_transparent_color_text = $obj_info[1];

				$fully_transparent_color_defined = 1;
			}

			$objColor_rgb = GetColorComponents($fully_transparent_color_text);

			$final_obj_color = imagecolorallocate($image , $objColor_rgb[0] , $objColor_rgb[1] , $objColor_rgb[2]);
		}
		else
		{
			$final_obj_color = imagecolorallocatealpha($image , $objColor_rgb[0] , $objColor_rgb[1] , $objColor_rgb[2] , ($obj_pct_transparency * 127 / 100));
		}



		imagesetthickness($image , 1);

		$thickness = 1;






		date_default_timezone_set('Australia/Melbourne');


		$start_time = microtime_float();


$calculations .= "
=========
Processing internal_objs[$i] = [" . $internal_objs[$i] . "],
start_time = [" . $start_time . "],
===================
";



		if (sizeof($obj_info) > 4)
		{

			//
			// A special shape is being drawn, eg a circle
			//

			$orig_thickness = $obj_info[3];
			$orig_width_thickness = $orig_thickness;
			$orig_height_thickness = $orig_thickness;

			if (stripos($orig_thickness , "/") !== false)
			{
				//
				// The user wants to specify different widths for the width and height of an arc, circle or ellipse
				//

				list($orig_width_thickness , $orig_height_thickness) = explode("/" , $orig_thickness);
			}





			$obj_thickness = abs($orig_thickness);			// used by squares, rectangles and lines
			$width_thickness = abs($orig_width_thickness);	// used by arcs
			$height_thickness = abs($orig_height_thickness);	// used by arcs


			//
			// Strip out any modifiers that were attached to the
			// shape-type.  That reduces the permutations that the
			// switch statement below has to check.
			//
			// Note that EllipseArc will use the value in the
			// $obj_info[4] variable, not the value in $obj_shape
			//

			$obj_shape = strtolower($obj_info[4]);
			$obj_shape = str_replace("trim" , "" , $obj_shape);
			$obj_shape = str_replace("smooth" , "" , $obj_shape);
			$obj_shape = str_replace("flipv" , "" , $obj_shape);
			$obj_shape = str_replace("fliph" , "" , $obj_shape);
			$obj_shape = str_replace("comp" , "" , $obj_shape);


			switch ($obj_shape)
			{
				case 'arc' :			// the formal name of an arc
				case 'arcchord' :		// the formal name of a chord.
				case 'arcc' :
				case 'chord' :
				case 'arcsegment' :		// the formal name of a segment.
				case 'arcs' :
				case 'pie' :
				case 'pieslice' :
				case 'segment' :
				case 'arctruechord' :	// the formal name of a tchord.
				case 'arct' :
				case 'arctchord' :
				case 'tchord' :
				case 'truechord' :
				case 'circle' :		// the formal name of a circle
				case 'ellipse' :	// the formal name of an ellipse



					//
					// All of these shapes use EllipseArc, so that is
					// what we will use to draw them
					//
					// Note that EllipseArc will use the value in the
					// $obj_info[4] variable, not the value in $obj_shape
					//


					array_walk($obj_details , 'trim_value');

					$arc_rotation_angle = $obj_details[0];
					$centre_x = $obj_details[1];
					$centre_y = $obj_details[2];
					$width = $obj_details[3];


					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					$centre_x += $delta_x;
					$centre_y += $delta_y;


					//
					//	   image	, centre, centre, width, height, angle, angle, color         , shape              , angle, thickness, thickness
					//	   id   	,   _x  ,   _y  ,      ,       , start,  end , color         , shape              , alpha,   width  ,  height
					//



					IMAGE_ELLIPSEARC_wrapper(
						$image ,
						$centre_x ,
						$centre_y ,
						$obj_details[3] ,		// $outer_x (the width)
						$obj_details[4] ,		// $outer_y (the height)
						$obj_details[5] ,		// $start_angle
						$obj_details[6] ,		// $end_angle
						$final_obj_color ,
						$obj_info[4] ,			// the true shape of this object
						$obj_details[0] ,		// $alpha
						$obj_info[3]			// $orig_thickness
					);



					break;

				case "square" :
				case "rect" :
				case "rectangle" :

					array_walk($obj_details , 'trim_value');

					$centre_x = $obj_details[0];
					$centre_y = $obj_details[1];
					$width = $obj_details[2];
					$height = ($obj_shape == "square") ? $width : $obj_details[3];



					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					$centre_x += $delta_x;
					$centre_y += $delta_y;




					$point1_x = $centre_x - ($width / 2);
					$point1_y = $centre_y - ($height / 2);
					$point2_x = $centre_x + ($width / 2);
					$point2_y = $centre_y + ($height / 2);


					if (!$obj_thickness)
					{
						// Draw a solid rectangle
						imagefilledrectangle($image , $point1_x , $point1_y , $point2_x , $point2_y , $final_obj_color);
					}
					else
					{
						if ($orig_thickness > 0)
						{
							$point3_x = $point1_x + (int)($obj_thickness / 2);
							$point3_y = $point1_y + (int)($obj_thickness / 2);
							$point4_x = $point2_x - (int)($obj_thickness / 2);
							$point4_y = $point2_y - (int)($obj_thickness / 2);

							imagesetthickness($image , $obj_thickness);
							imagerectangle($image , $point3_x , $point3_y , $point4_x , $point4_y , $final_obj_color);
						}
						else
						{
							//
							// Draw two hollow rectangles
							//

							$point5_x = $point1_x + $width_thickness;
							$point5_y = $point1_y + $height_thickness;
							$point6_x = $point2_x - $width_thickness;
							$point6_y = $point2_y - $height_thickness;

							imagesetthickness($image , 1);
							imagerectangle($image , $point1_x , $point1_y , $point2_x , $point2_y , $final_obj_color);
							imagerectangle($image , $point5_x , $point5_y , $point6_x , $point6_y , $final_obj_color);
						}

					}

					break;

				case "line" :

					array_walk($obj_details , 'trim_value');

					list($point1_x , $point1_y , $point2_x , $point2_y) = $obj_details;


					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					$point1_x += $delta_x;
					$point1_y += $delta_y;
					$point2_x += $delta_x;
					$point2_y += $delta_y;



					if ($orig_thickness > 1)
					{
						if ($point1_x == $point2_x)
						{
							// Vertical Line

							$w = $obj_thickness;
							$h = 0;
						}
						else if ($point1_y == $point2_y)
						{
							// Horizontal Line

							$w = 0;
							$h = $obj_thickness;
						}
						else
						{
							// Slanted Line

							$alpha = atan(-($point2_y - $point1_y) / ($point2_x - $point1_x));

							$w = (int)($obj_thickness * cos((M_PI / 2) - $alpha));
							$h = (int)($obj_thickness * sin((M_PI / 2) - $alpha));
						}

						$point3_x = $point1_x + $w;
						$point3_y = $point1_y + $h;
						$point4_x = $point2_x + $w;
						$point4_y = $point2_y + $h;

						$line_points = array($point1_x , $point1_y , $point2_x , $point2_y , $point4_x , $point4_y , $point3_x , $point3_y);

						imagefilledpolygon($image , $line_points , 4 , $final_obj_color);
					}
					else
					{
						imageline($image , $point1_x , $point1_y , $point2_x , $point2_y , $final_obj_color);
					}



					break;

				case "linea" :
				case "lineangle" :

					array_walk($obj_details , 'trim_value');

					list($centre_x , $centre_y , $angle , $length) = $obj_details;



					$point1_x = $centre_x;
					$point1_y = $centre_y;


					$point2_x = $centre_x + (int)(($length - 1 ) * cos(deg2rad($angle)));
					$point2_y = $centre_y - (int)(($length - 1 ) * sin(deg2rad($angle)));


					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					$point1_x += $delta_x;
					$point1_y += $delta_y;
					$point2_x += $delta_x;
					$point2_y += $delta_y;




					if ($orig_thickness > 1)
					{
						if ($point1_x == $point2_x)
						{
							// Vertical Line

							$w = $obj_thickness;
							$h = 0;
						}
						else if ($point1_y == $point2_y)
						{
							// Horizontal Line

							$w = 0;
							$h = $obj_thickness;
						}
						else
						{
							// Slanted Line

							$alpha = atan(-($point2_y - $point1_y) / ($point2_x - $point1_x));

							$w = (int)($obj_thickness * cos((M_PI / 2) - $alpha));
							$h = (int)($obj_thickness * sin((M_PI / 2) - $alpha));
						}

						$point3_x = $point1_x + $w;
						$point3_y = $point1_y + $h;
						$point4_x = $point2_x + $w;
						$point4_y = $point2_y + $h;

						$line_points = array($point1_x , $point1_y , $point2_x , $point2_y , $point4_x , $point4_y , $point3_x , $point3_y);

						imagefilledpolygon($image , $line_points , 4 , $final_obj_color);
					}
					else
					{
						imageline($image , $point1_x , $point1_y , $point2_x , $point2_y , $final_obj_color);
					}


					break;



				case "lineg" :
				case "linegrad" :
				case "linegradient" :

					array_walk($obj_details , 'trim_value');

					list($centre_x , $centre_y , $x_increment , $y_increment , $length) = $obj_details;


					if ($x_increment == 0)
					{
						if ($y_increment > 0)
						{
							$angle = 90;
						}
						else
						{
							$angle = -90;
						}
					}
					else
					{
						$angle = rad2deg(atan($y_increment / $x_increment));
					}



					$point1_x = $centre_x;
					$point1_y = $centre_y;

					$point2_x = $centre_x + (int)(( $length - 1 ) * cos(deg2rad($angle)));
					$point2_y = $centre_y - (int)(( $length - 1 ) * sin(deg2rad($angle)));


					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					$point1_x += $delta_x;
					$point1_y += $delta_y;
					$point2_x += $delta_x;
					$point2_y += $delta_y;




					if ($orig_thickness > 1)
					{
						if ($point1_x == $point2_x)
						{
							// Vertical Line

							$w = $obj_thickness;
							$h = 0;
						}
						else if ($point1_y == $point2_y)
						{
							// Horizontal Line

							$w = 0;
							$h = $obj_thickness;
						}
						else
						{
							// Slanted Line

							$alpha = atan(-($point2_y - $point1_y) / ($point2_x - $point1_x));

							$w = (int)($obj_thickness * cos((M_PI / 2) - $alpha));
							$h = (int)($obj_thickness * sin((M_PI / 2) - $alpha));
						}

						$point3_x = $point1_x + $w;
						$point3_y = $point1_y + $h;
						$point4_x = $point2_x + $w;
						$point4_y = $point2_y + $h;

						$line_points = array($point1_x , $point1_y , $point2_x , $point2_y , $point4_x , $point4_y , $point3_x , $point3_y);

						imagefilledpolygon($image , $line_points , 4 , $final_obj_color);
					}
					else
					{
						imageline($image , $point1_x , $point1_y , $point2_x , $point2_y , $final_obj_color);
					}


					break;



				case "text" :

					for ( $jj = 0; $jj < 5; $jj++ )
					{
						// I can not use the array_walk for "text" shapes
						// because the $desired_text might contain leading
						// or trailing whitespace that I want to keep
						//
						$obj_details[$jj] = trim( $obj_details[$jj] );
					}



					$font = strtolower($obj_details[0]);
					$font_size = ($obj_details[1] > 0) ? $obj_details[1] : 10 ;
					$point1_x = $obj_details[2];
					$point1_y = $obj_details[3];
					$text_angle = $obj_details[4];
					$desired_text_array = array_slice($obj_details , 5);

					$desired_text = implode("," , $desired_text_array);

					if (stripos($desired_text , "\\n") !== false)
					{
						// The text contains the \n character.
						// Split it across several lines

						$desired_text_fields1 = explode("\\n" , $desired_text);
						$desired_text = implode("\n" , $desired_text_fields1);
					}




					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					$point1_x += $delta_x;
					$point1_y += $delta_y;


$calculations .= "
font = [" . $font . "],
font_size = [" . $font_size . "],
point1_x = [" . $point1_x . "],
point1_y = [" . $point1_y . "],
text_angle = [" . $text_angle . "],
desired_text = [" . $desired_text . "],
";


					$size_of_font_array = sizeof($font_array);

					$font_file = $font_dir . $font_array[0] . ".ttf";		// Set this as the default

					for ($font_check = 0; $font_check < $size_of_font_array; $font_check++)
					{
						if ($font == $font_array[$font_check])
						{
							//
							// The user's supplied font is valid.  Use it
							//

							$font_file = $font_dir . $font . ".ttf";

							break;
						}
					}




					imagefttext($image , $font_size , $text_angle , $point1_x , $point1_y , $final_obj_color , $font_file , $desired_text);

					break;

				case "point" :

					array_walk($obj_details , 'trim_value');

					$point1_x = $obj_details[0];
					$point1_y = $obj_details[1];


					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					$point1_x += $delta_x;
					$point1_y += $delta_y;



					if (sizeof($obj_details) > 2)
					{
						// The user wants to fill an object with this point's color

						$fillborder_color_text = $obj_details[2];
						$fillborder_pct_transparency = (sizeof($obj_details) > 3) ? $obj_details[3] : 0;

						$fillborder_color_rgb = GetColorComponents($fillborder_color_text);
						$final_fillborder_color = imagecolorallocatealpha(
							$image ,
							$fillborder_color_rgb[0] ,
							$fillborder_color_rgb[1] ,
							$fillborder_color_rgb[2] ,
							($fillborder_pct_transparency * 127 / 100)
						);

						imagefilltoborder($image , $point1_x , $point1_y , $final_fillborder_color , $final_obj_color);
					}
					else
					{
						imageline($image , $point1_x , $point1_y , $point1_x , $point1_y , $final_obj_color);
					}

					break;


				case "poly" :
				case "polygon" :
					// Note that a polygon is the default type of shape that the system draws.
					// If the keyword "poly" or "polygon" is omitted altogether, then all the points provided
					// will be regarded as corners of a polygon shape
					//

					$obj_point_count = sizeof($obj_details) / 2;


					list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

					for ($m = 0; $m < $obj_point_count; $m+=2)
					{
						$obj_details[$m]		+= $delta_x;
						$obj_details[$m + 1]	+= $delta_y;
					}


					if ($obj_info[3])
					{
						// a non-zero value means that this polygon should be hollow

						imagepolygon($image , $obj_details , $obj_point_count , $final_obj_color);


					}
					else
					{
						imagefilledpolygon($image , $obj_details , $obj_point_count , $final_obj_color);
					}

					break;
			}
		}
		else
		{
			//
			// This object actually does not have a defined shape.  It looks like the user wants to draw a polygon.
			//

			$obj_point_count = sizeof($obj_details) / 2;

			list($delta_x , $delta_y) = GetXYMoveDelta($obj_name);

			for ($m = 0; $m < $obj_point_count; $m+=2)
			{
				$obj_details[$m]		+= $delta_x;
				$obj_details[$m + 1]	+= $delta_y;
			}



			imagesetthickness($image , $obj_thickness);

			if ( $obj_point_count == 0 )
			{
				//
				// This shape is completely invalid
				//

$calculations .= "
=========
ERROR: internal_objs[$i] = [" . $internal_objs[$i] . "] has [". $obj_point_count . "] points.  It is invalid.
";


			}
			elseif ( $obj_point_count == 1 )
			{
				//
				// This shape will just be a point
				//

				imagesetpixel($image, $obj_details[0], $obj_details[1], $final_obj_color);
			}
			elseif ( $obj_point_count == 2 )
			{
				//
				// This shape will just be a line
				//

				imageline($image, $obj_details[0], $obj_details[1], $obj_details[2], $obj_details[3], $final_obj_color);
			}
			else
			{
				if ($obj_info[3])
				{
					// a non-zero value means that this polygon should be hollow

					imagepolygon($image , $obj_details , $obj_point_count , $final_obj_color);

				}
				else
				{

$calculations .= "
=========
got the  imagefilledpolygon for internal_objs[$i] = [" . $internal_objs[$i] . "],
";



					imagefilledpolygon($image , $obj_details , $obj_point_count , $final_obj_color);
				}
			}

			imagesetthickness($image , 1);
		}




		$finish_time = microtime_float();

		$duration = $finish_time - $start_time;

$calculations .= "
=========
Finished processing internal_objs[$i] = [" . $internal_objs[$i] . "],
finish_time = [" . $finish_time . "],
duration = [" . $duration . "],
===================
";





	}






	//
	// Process the border colour if it is defined
	//

	if ( $border_defined )
	{


$calculations .= "
========= Processing the border
border_line = [" . $border_line . "],
===================
";

		list(
			$border_label ,
			$border_color_text ,
			$border_pct_transparency ,
			$border_thickness ,
		) = explode("=" , $border_line );


		$borderColor_rgb = GetColorComponents($border_color_text);


		if ($border_pct_transparency == 100)
		{
			// This colour was set to be 100% transparent

			if ( $fully_transparent_color_defined )
			{
				// Another colour has already been defined as being 100% transparent.
				// Assign that previous colour to this border as well
				//

			}
			else
			{
				//
				// Set this object's colour as the 100% transparent colour
				//

				$fully_transparent_color_text = $obj_info[1];

				$fully_transparent_color_defined = 1;
			}

			$borderColor_rgb = GetColorComponents($fully_transparent_color_text);

			$final_border_color = imagecolorallocate(
				$image ,
				$borderColor_rgb[0] ,
				$borderColor_rgb[1] ,
				$borderColor_rgb[2]
			);
		}
		else
		{
			$final_border_color = imagecolorallocatealpha(
				$image ,
				$borderColor_rgb[0] ,
				$borderColor_rgb[1] ,
				$borderColor_rgb[2] ,
				($border_pct_transparency * 127 / 100)
			);
		}



		imagesetthickness($image , $border_thickness);


		imagerectangle($image , 0 , 0 , $image_width_minus_1 , $image_height_minus_1 , $final_border_color);


		imagesetthickness($image , 1);

		$thickness = 1;









	}









	if ($fully_transparent_color_defined)
	{
		//
		// There is a fully transparent colour in the image.
		// Only the last fully transparent colour is processed
		//

		$fully_transparent_color_rgb = GetColorComponents($fully_transparent_color_text);
		$fully_transparent_color = imagecolorallocate($image , $fully_transparent_color_rgb[0] , $fully_transparent_color_rgb[1] , $fully_transparent_color_rgb[2]);

		imagecolortransparent($image , $fully_transparent_color);
	}




	if ($img_rotate)
	{
		//
		// The user wants to rotate the image
		//

		$image = imagerotate($image , $img_rotate , $final_img_color);
	}




	// Output image to the browser
	if ($img_type == "png")
	{
			header('Content-Type: image/png');
			imagepng($image);						// This one goes to the screen or stdout
	}
	else if ($img_type == "jpg")
	{
			header('Content-Type: image/jpeg');
			imagejpeg($image);						// This one goes to the screen or stdout
	}
	else if ($img_type == "gif")
	{
			header('Content-Type: image/gif');
			imagegif ($image);						// This one goes to the screen or stdout
	}
	else if ($img_type == "wbmp")
	{
			header('Content-Type: image/vnd.wap.wbmp');
			imagewbmp($image);						// This one goes to the screen or stdout
	}
	else
	{
			header('Content-Type: image/png');
			imagepng($image);						// This one goes to the screen or stdout
	}

	imagedestroy($image);




if (!$show_calculations)
{
$calculations = "";
}
else
{





$raw_calculations_file_name = "raw_calcs_polygon.txt";
$fp = fopen($raw_calculations_file_name , 'w');
fwrite($fp , "$calculations");
fclose($fp);



$running_raw_calculations_file_name = "running_raw_calcs_polygon.txt";
$fp = fopen($running_raw_calculations_file_name , 'w');
fwrite($fp , "$calculations");
fclose($fp);


}




	//
	// GetXYMoveDelta gets the final x-y distances that the various "move" operations
	// specified for $this_obj_name
	//

	function GetXYMoveDelta($obj_name)
	{


		global $move_operations;
		global $move_group;

		$delta_x = 0;
		$delta_y = 0;

		if (array_key_exists($obj_name , $move_operations))
		{
			$delta_x += $move_operations[$obj_name][0];
			$delta_y += $move_operations[$obj_name][1];
		}
		if (array_key_exists("moveall" , $move_operations))
		{
			$delta_x += $move_operations["moveall"][0];
			$delta_y += $move_operations["moveall"][1];
		}
		foreach ($move_group as $curr_prefix => $curr_values)
		{
			if (stripos($obj_name , $curr_prefix) !== false)
			{
				$delta_x += $move_group[$curr_prefix][0];
				$delta_y += $move_group[$curr_prefix][1];
			}
		}


		return array($delta_x , $delta_y);
	}






	function GetColorComponents($input_color)
	{
		$input_color = str_replace("#" , "" , $input_color);

		$input_color .= "000000";		// Ensuring that the array will always have at least 6 elements

		$hex_colors = str_split($input_color);

		if (sizeof($hex_colors) == 9)
		{
			// The color was supplied with the 3-byte notation eg "#faf" means "ffaaff"

			$hex_r = "0x" . $hex_colors[0] . $hex_colors[0];
			$hex_g = "0x" . $hex_colors[1] . $hex_colors[1];
			$hex_b = "0x" . $hex_colors[2] . $hex_colors[2];
		}
		else
		{
			$hex_r = "0x" . $hex_colors[0] . $hex_colors[1];
			$hex_g = "0x" . $hex_colors[2] . $hex_colors[3];
			$hex_b = "0x" . $hex_colors[4] . $hex_colors[5];
		}



		return array($hex_r , $hex_g , $hex_b);
	}




	//PEAR Ready
	//
	// trim_value was taken verbatim from the PHP Manual. Thanks guys!
	//
	// See "Example #2 Trimming array values with trim()" in the String
	// Functions chapter of the PHP Manual
	//

	function trim_value(&$value)
	{
		$value = trim($value);
	}




	// microtime_float was taken verbatim from the PHP Manual. Thanks guys!
	//
	// See "Example #1 Timing script execution with microtime()" under "microtime"
	// in the Date/Time Functions chapter of the PHP Manual
	//
	function microtime_float()
	{
		list($usec, $sec) = explode(" ", microtime());
		return ((float)$usec + (float)$sec);
	}



?>
