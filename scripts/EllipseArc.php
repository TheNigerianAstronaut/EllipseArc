<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Draws rotatable arcs, chords, segments, crescents and ellipses of
 * various thicknesses, including support for alpha colours
 *
 * EllipseArc attempts to address the "thickness" issues when drawing
 * arcs using built-in PHP functions like imagearc.
 *
 * The following shape-types are supported
 *
 *     <ol>
 *       <li>
 * Arcs: regular arcs with start and end angles
 *       </li>
 *       <li>
 *
 * Chords: the inner arc tips are connected by a straight line. The
 *      inner arc is not drawn.
 *       </li>
 *       <li>
 *
 * TrueChords: an imaginary "tchord" line cuts the shape between the
 *      outer tips. Part of the shape on either side of this line is
 *      truncated. If the line crosses through the inner arc, a "C"
 *      shape is produced, otherwise a "D" shape is produced.
 *       </li>
 *       <li>
 *
 * Segments: also known as "pie-slices". The start and end angles are
 *      connected to the centre of the shape.
 *       </li>
 *       <li>
 *
 * Ellipses: complete arcs whose width and height dimensions are
 *      different.
 *       </li>
 *       <li>
 *
 * Circles: complete arcs whose width and height dimensions are the
 *      same.
 *       </li>
 *       <li>
 *
 * Crescents: instead of uniform thickness within the shape, the inner
 *      arc has separate width/height parameters thereby enabling a
 *      crescent or "eye" shape to be formed.
 *       </li>
 *     </ol>
 *
 *
 * General features applicable to all shape-types
 *
 *
 *     <ol>
 *       <li>
 * Alpha colours are fully supported
 *       </li>
 *       <li>
 *
 * Shapes can be rotated through any angle in the X-Y plane.
 *       </li>
 *       <li>
 *
 * Shapes can be filled or just outlined
 *       </li>
 *       <li>
 *
 * Large and small shapes are supported. Theoretically the shape has
 *      to approach the sqrt(PHP_INT_MAX) limit before problems occur.
 *      (However the server's memory limitations will probably kick in
 *      long before then)
 *       </li>
 *       <li>
 *
 * Shapes can be flipped horizontally around the Y-axis (the "fliph"
 *      modifier)
 *       </li>
 *       <li>
 *
 * Shapes can be flipped vertically around the X-axis (the "flipv"
 *      modifier)
 *       </li>
 *       <li>
 *
 * Shape complements can be taken (the "comp" modifier). In this case
 *      the opposite section of the shape is drawn.
 *       </li>
 *     </ol>
 *
 *      The "fliph", "flipv" and "comp" modifiers can be added
 *      anywhere within the name of the desired shape, for example
 *      "arcflipvfliphcomp" and "arcfliphcompflipv" will produce the
 *      same result. They can also be added multiple times within the
 *      same shape, however the modifier will only apply if it is
 *      present an odd number of times in the shape's name. So
 *      "arcflipvcompfliphcompcomp" will produce the same shape as the
 *      previous two examples since the "comp" modifier occurs 3
 *      times.
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Image
 * @package   EllipseArc
 * @author    Austin Ekwebelam <aekwebelam@hotmail.com>
 * @copyright 1997-2011 The PHP Group
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version   SVN: 0.1.0
 * @link      http://pear.php.net/package/EllipseArc
 *
 * @todo Future improvements
 *     <ol>
 *       <li>
 *          Fine-tune tchord calculations so tangents can
 *          be drawn without double-filling
 *       </li>
 *       <li>
 *          Fine-tune tchord calculations for unfilled shapes
 *          so P and Q are guaranteed to be the best values
 *       </li>
 *       <li>
 *          Fine-tune _connectDots so the pixel after ($next_x,$next_y)
 *          is factored in. This will avoid double-filling in cases
 *          where the slope of the line changes sign at ($next_x,$next_y)
 *       </li>
 *       <li>
 *          Allow the inner arc to be offset from the centre
 *       </li>
 *       <li>
 *          Smoothing the left/right edges of inner arcs.  In small arcs
 *          they appear clipped.
 *       </li>
 *       <li>
 *          Allow a shape to truncate parts of the ellipse.
 *          For example a rectangle can be inscribed in the
 *          ellipse, so only the area outside the rectangle
 *          gets drawn.
 *       </li>
 *     </ol>
 *
 *
 */


/**
 * A wrapper function to enable end-users to call EllipseArc in a
 * similar manner to imagearc and imagefilledarc calls It creates a
 * new Image_EllipseArc object, draws the shape and then unsets the
 * object.
 *
 * @param resource &$image           a reference to the image object
 * @param int      $centre_x         x-coordinate of the centre of the
 *                      shape
 * @param int      $centre_y         y-coordinate of the centre of the
 *                      shape
 * @param int      $outer_width      the full horizontal width of the
 *                      shape, assuming it was a full unrotated
 *                      ellipse
 * @param int      $outer_height     the full vertical height of the
 *                      shape, assuming it was a full unrotated
 *                      ellipse
 * @param float    $start_angle      the starting angle of the revised
 *                      shape
 * @param float    $end_angle        the ending angle of the revised
 *                      shape
 * @param resource $arc_color        a reference to the shape's colour
 *                      from imagecolorallocate or
 *                      imagecolorallocatealpha
 * @param string   $obj_shape        the type of shape. Defaults to
 *                      'arc'.
 * @param float    $alpha            the axis of rotation. Defaults to
 *                      0.
 * @param int      $orig_thickness   the thickness. Defaults to 0. It
 *                      can be either a single number (for uniform
 *                      thickness) or a quoted string containing two
 *                      numbers separated by a forward slash "/". The
 *                      first number is the thickness of the width and
 *                      the second number is the thickness of the
 *                      height. For either number, a negative value
 *                      means that the preference is for the shape to
 *                      be outlined instead of being filled. The shape
 *                      will only be outlined if BOTH numbers are
 *                      negative.
 * @param int      $height_thickness the optional thickness of the
 *                      unrotated height. If supplied, overrides the
 *                      height that the $orig_thickness parameter
 *                      would have provided. It is a single number and
 *                      it only modifies the height. Defaults to null.
 *
 * If both $orig_thickness and $height_thickness are negative numbers,
 * the shape will be outlined instead of filled.
 *
 * @return array  containing the following parameters:
 *        string   $obj_shape        the standardised shape-type
 *        int      $errors           the sum of the error-codes that
 *                      were found when processing the shape. A value
 *                      of 8 or higher will result in the shape not
 *                      being drawn
 *
 * @access public
 *
 */
function IMAGE_ELLIPSEARC_wrapper(
    &$image,
    $centre_x,
    $centre_y,
    $outer_width,
    $outer_height,
    $start_angle,
    $end_angle,
    $arc_color,
    $obj_shape = 'arc',
    $alpha = 0,
    $orig_thickness = 0,
    $height_thickness = null
) {

    $output = new Image_EllipseArc(
        $image,
        $centre_x,
        $centre_y,
        $outer_width,
        $outer_height,
        $start_angle,
        $end_angle,
        $arc_color,
        $obj_shape,
        $alpha,
        $orig_thickness,
        $height_thickness
    );

    unset($output);
}


/**
 * Draws rotatable arcs, chords, segments, crescents and ellipses of
 * various thicknesses, including support for alpha colours
 *
 * EllipseArc attempts to address the "thickness" issues when drawing
 * arcs using built-in PHP functions like imagearc.
 *
 * The following shape-types are supported
 *
 *     <ol>
 *       <li>
 * Arcs: regular arcs with start and end angles
 *       </li>
 *       <li>
 *
 * Chords: the inner arc tips are connected by a straight line. The
 *      inner arc is not drawn.
 *       </li>
 *       <li>
 *
 * TrueChords: an imaginary "tchord" line cuts the shape between the
 *      outer tips. Part of the shape on either side of this line is
 *      truncated. If the line crosses through the inner arc, a "C"
 *      shape is produced, otherwise a "D" shape is produced.
 *       </li>
 *       <li>
 *
 * Segments: also known as "pie-slices". The start and end angles are
 *      connected to the centre of the shape.
 *       </li>
 *       <li>
 *
 * Ellipses: complete arcs whose width and height dimensions are
 *      different.
 *       </li>
 *       <li>
 *
 * Circles: complete arcs whose width and height dimensions are the
 *      same.
 *       </li>
 *       <li>
 *
 * Crescents: instead of uniform thickness within the shape, the inner
 *      arc has separate width/height parameters thereby enabling a
 *      crescent or "eye" shape to be formed.
 *       </li>
 *     </ol>
 *
 *
 * General features applicable to all shape-types
 *
 *
 *     <ol>
 *       <li>
 * Alpha colours are fully supported
 *       </li>
 *       <li>
 *
 * Shapes can be rotated through any angle in the X-Y plane.
 *       </li>
 *       <li>
 *
 * Shapes can be filled or just outlined
 *       </li>
 *       <li>
 *
 * Large and small shapes are supported. Theoretically the shape has
 *      to approach the sqrt(PHP_INT_MAX) limit before problems occur.
 *      (However the server's memory limitations will probably kick in
 *      long before then)
 *       </li>
 *       <li>
 *
 * Shapes can be flipped horizontally around the Y-axis (the "fliph"
 *      modifier)
 *       </li>
 *       <li>
 *
 * Shapes can be flipped vertically around the X-axis (the "flipv"
 *      modifier)
 *       </li>
 *       <li>
 *
 * Shape complements can be taken (the "comp" modifier). In this case
 *      the opposite section of the shape is drawn.
 *       </li>
 *     </ol>
 *
 *      The "fliph", "flipv" and "comp" modifiers can be added
 *      anywhere within the name of the desired shape, for example
 *      "arcflipvfliphcomp" and "arcfliphcompflipv" will produce the
 *      same result. They can also be added multiple times within the
 *      same shape, however the modifier will only apply if it is
 *      present an odd number of times in the shape's name. So
 *      "arcflipvcompfliphcompcomp" will produce the same shape as the
 *      previous two examples since the "comp" modifier occurs 3
 *      times.
 *
 * @todo Future improvements
 *     <ol>
 *       <li>
 *          Fine-tune tchord calculations so tangents can
 *          be drawn without double-filling
 *       </li>
 *       <li>
 *          Fine-tune tchord calculations for unfilled shapes
 *          so P and Q are guaranteed to be the best values
 *       </li>
 *       <li>
 *          Fine-tune _connectDots so the pixel after ($next_x,$next_y)
 *          is factored in. This will avoid double-filling in cases
 *          where the slope of the line changes sign at ($next_x,$next_y)
 *       </li>
 *       <li>
 *          Allow the inner arc to be offset from the centre
 *       </li>
 *       <li>
 *          Smoothing the left/right edges of inner arcs.  In small arcs
 *          they appear clipped.
 *       </li>
 *       <li>
 *          Allow a shape to truncate parts of the ellipse.
 *          For example a rectangle can be inscribed in the
 *          ellipse, so only the area outside the rectangle
 *          gets drawn.
 *       </li>
 *     </ol>
 *
 * PHP version 5
 *
 * LICENSE: This source file is subject to version 3.01 of the PHP license
 * that is available through the world-wide-web at the following URI:
 * http://www.php.net/license/3_01.txt.  If you did not receive a copy of
 * the PHP License and are unable to obtain it through the web, please
 * send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category  Image
 * @package   EllipseArc
 * @author    Austin Ekwebelam <aekwebelam@hotmail.com>
 * @copyright 1997-2011 The PHP Group
 * @license   http://www.php.net/license/3_01.txt  PHP License 3.01
 * @version   Release: 0.1.0
 * @link      http://pear.php.net/package/EllipseArc
 *
 * @access public
 *
 */
class Image_EllipseArc
{

    /**
     * Stores the standardised shape-type that EllipseArc determined
     * for the supplied inputs
     *
     * Potential values are
     *     <ol>
     *       <li>
     * arc
     *       </li>
     *       <li>
     * arcchord
     *       </li>
     *       <li>
     * arcsegment
     *       </li>
     *       <li>
     * arctruechord
     *       </li>
     *       <li>
     * circle
     *       </li>
     *       <li>
     * ellipse
     *       </li>
     *       <li>
     * The original name of the shape if it is invalid
     *       </li>
     *     </ol>
     *
     * The "flipv", "fliph", "comp" and "smooth" modifiers are
     * stripped from the shape-type since they are converted into
     * flags.
     *
     * The value can be obtained by running the reportStatus()
     * function on the owning object
     *
     * @var string
     * @access private
     *
     */
    private $_shape;


    /**
     * Stores codes relating to any errors that were found when
     * processing the shape
     *
     * Potential values are
     *     <ol>
     *       <li>
     * 0 - no errors found. The input was completely good
     *       </li>
     *       <li>
     * 1 - the thickness exceeds the outer dimensions of the shape.
     * The shape will be drawn with zero thickness and filled.
     *       </li>
     *       <li>
     * 8 - either the outer width or outer height was zero.
     *       </li>
     *       <li>
     * 128 - the shape-type is not supported.
     *       </li>
     *     </ol>
     *
     * The final _errors value is the sum of the error codes that were
     * found when processing the shape. A final result of 8 or higher
     * will result in the shape not being drawn.
     *
     * The value can be obtained by running the reportStatus()
     * function on the owning object
     *
     * @var int
     * @access private
     *
     */
    private $_errors;


    /**
     * Constructor for the Image_EllipseArc class
     *
     * @param resource &$image           a reference to the image
     *                      object
     * @param int      $centre_x         x-coordinate of the centre of
     *                      the shape
     * @param int      $centre_y         y-coordinate of the centre of
     *                      the shape
     * @param int      $outer_width      the full horizontal width of
     *                      the shape, assuming it was a full
     *                      unrotated ellipse
     * @param int      $outer_height     the full vertical height of
     *                      the shape, assuming it was a full
     *                      unrotated ellipse
     * @param float    $start_angle      the starting angle of the
     *                      revised shape
     * @param float    $end_angle        the ending angle of the
     *                      revised shape
     * @param resource $arc_color        a reference to the shape's
     *                      colour from imagecolorallocate or
     *                      imagecolorallocatealpha
     * @param string   $obj_shape        the type of shape. Defaults
     *                      to 'arc'.
     * @param float    $alpha            the axis of rotation.
     *                      Defaults to 0.
     * @param int      $orig_thickness   the thickness. Defaults to 0.
     *                      It can be either a single number (for
     *                      uniform thickness) or a quoted string
     *                      containing two numbers separated by a
     *                      forward slash "/". The first number is the
     *                      thickness of the width and the second
     *                      number is the thickness of the height. For
     *                      either number, a negative value means that
     *                      the preference is for the shape to be
     *                      outlined instead of being filled. The
     *                      shape will only be outlined if BOTH
     *                      numbers are negative.
     * @param int      $height_thickness the optional thickness of the
     *                      unrotated height. If supplied, overrides
     *                      the height that the $orig_thickness
     *                      parameter would have provided. It is a
     *                      single number and it only modifies the
     *                      height. Defaults to null.
     *
     * If both $orig_thickness and $height_thickness are negative
     * numbers, the shape will be outlined instead of filled.
     *
     * @return array  containing the following parameters:
     *        string   $this->_shape     the standardised shape-type
     *        int      $this->_errors    the sum of the error-codes
     *                      that were found when processing the shape.
     *                      A value of 8 or higher will result in the
     *                      shape not being drawn
     *
     * @access public
     *
     */
    function __construct(
        &$image,
        $centre_x,
        $centre_y,
        $outer_width,
        $outer_height,
        $start_angle,
        $end_angle,
        $arc_color,
        $obj_shape = 'arc',
        $alpha = 0,
        $orig_thickness = 0,
        $height_thickness = null
    ) {

        list(
            $this->_shape,
            $this->_errors,
        ) = $this->_drawArc(
            $image,
            $centre_x,
            $centre_y,
            $outer_width,
            $outer_height,
            $start_angle,
            $end_angle,
            $arc_color,
            $obj_shape,
            $alpha,
            $orig_thickness,
            $height_thickness
        );
    }


    /**
     * Adjusts the supplied angle to fit into the 0 - 360 degree range
     *
     * @param float $input_angle the input angle in degrees
     *
     *
     * NOTES The calculations deliberately use while loops instead of
     * the PHP modulus operator. The latter truncates the angle into
     * an integer, with the effect a chord going from 180 to 360
     * degrees will now have pixels for the 179 and 1 degree positions
     * showing.
     *
     * @return  float $input_angle adjusted to the range 0 to 360
     *                      range
     *
     * @access private
     *
     */
    private function _angleIn360($input_angle)
    {
        while ($input_angle > 360) {
            $input_angle -= 360;
        }
        while ($input_angle < 0) {
            $input_angle += 360;
        }

        return $input_angle;
    }


    /**
     * Returns the closest x-y pixel to the destination pixel from a
     * starting pixel
     *
     * This closest x-y pair is always adjacent to the destination
     * pixel. This is useful when drawing outlines: by default
     * imageline would fill in the pixels up to and including the
     * destination. However in an arc this means that the destination
     * pixel gets filled twice: once for the line connecting up to it;
     * and the second time for the line going from it to the next
     * point. This is noticeable when alpha colours are used.
     * _closestPoint prevents the destination itself will not be
     * coloured by imageline, thereby reducing the chances of
     * double-filling.
     *
     * NOTE: A future improvement to this function will be to take
     * into account the position of the next pixel AFTER the
     * destination. If the pixel after the destination is in the
     * opposite direction from the one leading to the destination, it
     * is possible for the destination to still get double-filled.
     *
     * @param float $start_x the x-value of the starting pixel
     * @param float $start_y the y-value of the starting pixel
     * @param float $dest_x  the x-value of the destination pixel
     * @param float $dest_y  the y-value of the destination pixel
     *
     * @return array  containing the following parameters:
     *        int   $final_x
     *        int   $final_y
     *
     * @access private
     *
     */
    private function _closestPoint($start_x, $start_y, $dest_x, $dest_y)
    {
        $final_x = $start_x;
        $final_y = $start_y;

        if ($start_x == $dest_x) {
            //
            // A vertical line
            //

            $final_y = $dest_y - 1 * $this->_getSign($dest_y - $start_y);
        }

        if ($start_y == $dest_y) {
            //
            // A horizontal line
            //

            $final_x = $dest_x - 1 * $this->_getSign($dest_x - $start_x);
        } else {
            $max_x_halvings = ($start_x == $dest_x)
            ? 0 : log(abs(abs($dest_x) - abs($start_x))) / log(2);

            $max_y_halvings = ($start_y == $dest_y)
            ? 0 : log(abs(abs($dest_y) - abs($start_y))) / log(2);

            $halvings_to_use = max(
                ceil($max_x_halvings),
                ceil($max_y_halvings)
            ) + 1;

            for ($i = 0; $i < $halvings_to_use; $i++) {
                //
                // Take the mid-point between the start and dest
                //

                $final_x = (($final_x + $dest_x) / 2);
                $final_y = (($final_y + $dest_y) / 2);
            }
        }

        return array($final_x, $final_y);
    }


    /**
     * Verifies if a line, pixel or nothing should be drawn between
     * $current_x and $next_x for a given $pixel_y
     *
     * The results are later fed to _drawLineOrPixel to do the actual
     * drawing activity.
     *
     * The line is ALWAYS from left to right. The line also goes from
     * the starting x-value up to (but not including) the destination
     * x-value. During the next iteration of the calling function, the
     * current destination x-value will become the starting point.
     * This prevents double-filling the start and end points.
     *
     * LHS = Left Hand Side RHS = Right Hand Side
     *
     * The companion function to _confirmLineOrPixel is
     * _drawLineOrPixel. _confirmLineOrPixel makes the decisions which
     * _drawLineOrPixel then executes.
     *
     * NOTE: _confirmLineOrPixel is only used when the shape is to be
     * filled. Another function is used if only the outline of the
     * shape is desired.
     *
     * @param int    $row_y                        the y-value of the
     *                      row being processed. Positive y-values are
     *                      closer to the top of the screen since the
     *                      true Cartesian system is used.
     * @param float  $current_x                    the x-value of the
     *                      starting pixel
     * @param float  $next_x                       the x-value of the
     *                      destination pixel. Note that
     *                      ($next_x,$pixel_y) is NOT populated in
     *                      this iteration.
     * @param bool   $trim_tips                    true if the edges
     *                      should be trimmed.
     * @param bool   $smooth_check                 true if lone pixels
     *                      should be removed
     * @param bool   $prev_adj_pixel_drawn         true if the
     *                      previous x-value before $current_x was
     *                      valid. It helps determine if $current_x is
     *                      a lone pixel.
     * @param array  $excl_x_points_of_row_y       an array containing
     *                      boundary x-values that are "excluded" from
     *                      the outer extremities of the shape for
     *                      this row.
     * @param array  $inner_excl_x_points_of_row_y an array containing
     *                      boundary x-values points within the inner
     *                      part of the shape. Any x-value within this
     *                      range will not be drawn as this area
     *                      should be hollow.
     * @param array  $tchord_x_points_of_row_y     an array containing
     *                      the points on the tchord that intersect
     *                      this horizontal row. The points may be
     *                      projections of the tchord line.
     * @param string $tchord_excl_side             states which area
     *                      (left, right, top or bottom) around the
     *                      tchord is excluded from being part of the
     *                      shape. The value is "none" if the shape is
     *                      not a tchord.
     * @param bool   $draw_tchord_line             true if the tchord
     *                      line must be drawn for the shape, false
     *                      otherwise.
     * @param float  $smallest_t_y                 the smallest
     *                      y-value of the tchord. It may be for the
     *                      starting point or the ending point. The
     *                      desired pixel's y-value will never be less
     *                      than this value.
     * @param float  $largest_t_y                  the largest y-value
     *                      of the tchord. It may be for the starting
     *                      point or the ending point. The desired
     *                      pixel's y-value will never be greater than
     *                      this value.
     *
     * @return array  containing the following parameters:
     *        bool   $draw_line_valid              a binary switch.
     *                      "1" if a line can be drawn using
     *                      imageline, "0" otherwise
     *        bool   $draw_lhs_pixel_valid         a binary switch.
     *                      "1" if $current_x can be coloured using
     *                      imagesetpixel, "0" otherwise.
     *        bool   $prev_adj_pixel_drawn
     *        float  $current_x                    the revised
     *                      $current_x that the line will begin from.
     *        float  $next_x                       the revised $next_x
     *                      that marks the pixel after the end of the
     *                      line.
     *
     * The returned $current_x and $next_x values are used by tchords
     * to determine where the next printing should start from. Only
     * tchords modify these values
     *
     * @access private
     *
     */
    private function _confirmLineOrPixel(
        $row_y,
        $current_x, $next_x, $trim_tips, $smooth_check,
        $prev_adj_pixel_drawn, $excl_x_points_of_row_y,
        $inner_excl_x_points_of_row_y,
        $tchord_x_points_of_row_y, $tchord_excl_side,
        $draw_tchord_line, $smallest_t_y, $largest_t_y
    ) {

        //
        // Initially assume that a line is invalid, so we have to prove
        // that a line is valid.
        // Also assume that the LHS pixel (ie $current_x) can be drawn
        //

        $draw_line_valid = 0;
        $draw_lhs_pixel_valid = 1;

        list(
            $min_excl_x,
            $max_excl_x
        ) = $excl_x_points_of_row_y;

        list(
            $min_inner_excl_x,
            $max_inner_excl_x
        ) = $inner_excl_x_points_of_row_y;

        $target_search_entry_lhs = $current_x;
        $target_search_entry_rhs = $next_x;

        if (($target_search_entry_rhs < $min_inner_excl_x)
            || ($target_search_entry_lhs > $max_inner_excl_x)
        ) {
            //
            // The $target_search_entry_rhs and $target_search_entry_lhs
            // are both on the same side of $inner_excl_x_points.
            // The line is looking valid
            //

            $draw_line_valid = 1;
        } else {
            //
            // This can not possibly be a line since it runs over
            // the "hollow" centre of the arc
            // Let's see whether a pixel is even valid
            //

            $draw_line_valid = 0;

            if (($current_x >= $min_inner_excl_x)
                && ($current_x <= $max_inner_excl_x)
            ) {
                //
                // The $current_x itself is in the "hollow" area, so
                // we will not even draw it.
                //
                // Note that we do not need to perform this test
                // for $next_x, because on the next iteration it will
                // become $current_x and will then get tested
                //

                $draw_lhs_pixel_valid = 0;
            }
        }

        if (($draw_line_valid) && ($current_x == $next_x)) {
            //
            // The start and end of the line are the same pixel.
            // Disable $draw_line_valid so imageline will not
            // double-up the pixels.  Also set $draw_lhs_pixel_valid
            // to true so imagesetpixel will draw the pixel for us.
            //

            $draw_line_valid = 0;
            $draw_lhs_pixel_valid = 1;
        }

        if ((($target_search_entry_lhs <= $min_excl_x)
            && ($min_excl_x < $target_search_entry_rhs))
            || (($target_search_entry_lhs <= $max_excl_x)
            && ($max_excl_x < $target_search_entry_rhs))
        ) {
            //
            // At least one of the regular excluded points are between
            // $target_search_entry_lhs and $target_search_entry_rhs.
            // We will not be able to draw a line
            //

            $draw_line_valid = 0;
        }

        $min_t_x = min($tchord_x_points_of_row_y);
        $max_t_x = max($tchord_x_points_of_row_y);

        if ($tchord_excl_side != 'none') {
            //
            // This is a tchord.
            // Check if the tchord line intersects between these points,
            // or if they are on the wrong side of the tchord line
            //

            switch($tchord_excl_side) {
            case 'exclude_top':
                if ($row_y > $largest_t_y) {
                    // This row is too high.  Do not draw anything
                    $draw_line_valid = 0;
                    $draw_lhs_pixel_valid = 0;
                }
                break;

            case 'exclude_bottom':
                if ($row_y < $smallest_t_y) {
                    // This row is too low.  Do not draw anything
                    $draw_line_valid = 0;
                    $draw_lhs_pixel_valid = 0;
                }
                break;

            case 'exclude_left':
                if (($row_y < $smallest_t_y)
                    || ($row_y > $largest_t_y)
                ) {
                    //
                    // This row beyond the range of the tchord line.
                    // Check if the line is affected by the projection
                    // of the tchord line.
                    //

                    if ($target_search_entry_lhs < ($min_t_x - 2)) {
                        //
                        // The LHS is on the wrong side of the
                        // projected tchord.
                        //

                        if ($target_search_entry_rhs >= $min_t_x) {
                            //
                            // The RHS was okay, so move the start of
                            // the line to $min_t_x pixel and print it
                            //

                            $current_x = $min_t_x;
                            $draw_line_valid = 1;
                            $draw_lhs_pixel_valid = 1;
                            break;
                        } else {
                            $draw_line_valid = 0;
                            $draw_lhs_pixel_valid = 0;
                            break;
                        }
                    }

                    if ($target_search_entry_rhs <= $min_t_x) {
                        //
                        // the whole line is on the wrong side of
                        // the projected tchord
                        //

                        $draw_line_valid = 0;
                        $draw_lhs_pixel_valid = 0;
                        break;
                    }

                    if (($target_search_entry_lhs <= $min_t_x)
                        && (! $draw_line_valid)
                        && ($draw_lhs_pixel_valid)
                    ) {
                        //
                        // the LHS is on the wrong side of the
                        // projected tchord and it only qualified to
                        // have the LHS pixel drawn.
                        // Since we print left-to-right, we can just
                        // skip this line altogether.
                        //

                        $draw_line_valid = 0;
                        $draw_lhs_pixel_valid = 0;
                        break;
                    }

                    if ($target_search_entry_rhs >= $max_t_x) {
                        //
                        // the RHS is on the correct side of the
                        // projected tchord
                        //

                        if ($current_x >= $min_t_x) {
                            //
                            // The line is okay.  The tchord does
                            // not split it
                            //
                        } else {

                            $current_x = $max_t_x;

                            if ($current_x == $target_search_entry_rhs) {
                                //
                                // The start and end are the same pixel.
                                // Draw a pixel instead of a line.
                                //

                                $draw_line_valid = 0;
                                $draw_lhs_pixel_valid = 1;
                            } else {
                                $draw_line_valid = 1;
                                $draw_lhs_pixel_valid = 1;
                            }
                            break;
                        }
                    }
                } else {
                    //
                    // This row is within the tchord line's range
                    //

                    if ($target_search_entry_lhs > $target_search_entry_rhs
                    ) {
                        //
                        // We have doubled back.
                        // Disable printing, and set $next_x to
                        // the LHS one
                        //

                        $next_x = $target_search_entry_lhs;

                        $draw_line_valid = 0;
                        $draw_lhs_pixel_valid = 0;
                        break;
                    }

                    if ($target_search_entry_lhs < $min_t_x) {
                        //
                        // The LHS is in the excluded area.
                        // Move $current_x to the right so it
                        // matches $min_t_x
                        //

                        $current_x = $min_t_x;

                        if ($target_search_entry_rhs < $min_t_x) {
                            //
                            // The RHS is also in the excluded area.
                            // Hence the whole line (LHS and RHS) were
                            // excluded despite our best efforts.
                            // Do not draw a line or pixel.
                            // Also extend $next_x so it
                            // becomes $min_t_x
                            //

                            $next_x = $min_t_x;

                            $draw_line_valid = 0;
                            $draw_lhs_pixel_valid = 0;
                        }

                        if ($draw_line_valid) {
                            //
                            // It is okay to proceed
                            // Just check for doubling
                            //

                            if ($current_x == $target_search_entry_rhs
                            ) {
                                if ($draw_line_valid) {
                                    //
                                    // Draw a pixel instead of a line
                                    // since they are the same pixel.
                                    //

                                    $draw_line_valid = 0;
                                    $draw_lhs_pixel_valid = 1;
                                }
                            }
                        } else {

                            if ($draw_tchord_line) {

                                $next_x = $max_t_x;
                            } else {

                                $draw_line_valid = 0;
                                $draw_lhs_pixel_valid = 0;
                            }
                        }
                    }
                }
                break;

            case 'exclude_right':
                if (($row_y < $smallest_t_y)
                    || ($row_y > $largest_t_y)
                ) {
                    //
                    // This row beyond the range of the tchord
                    // Do not change anything
                    //

                    if ($target_search_entry_lhs >= $max_t_x) {
                        //
                        // the line is on the wrong side of
                        // the projected tchord
                        //

                        $draw_line_valid = 0;
                        $draw_lhs_pixel_valid = 0;
                        break;
                    }

                    if ($target_search_entry_rhs >= $max_t_x) {
                        //
                        // the RHS is on the wrong side of
                        // the projected tchord
                        //

                        $next_x = $max_t_x;

                        if ($current_x == $next_x) {
                            $draw_line_valid = 0;
                            $draw_lhs_pixel_valid = 1;
                        } elseif ($next_x < $current_x) {
                            $draw_line_valid = 0;
                            $draw_lhs_pixel_valid = 0;
                        }
                    }
                } else {
                    //
                    // It is within the tchord's range
                    //

                    if ($target_search_entry_lhs > $target_search_entry_rhs
                    ) {
                        //
                        // The line has doubled back
                        //

                        $draw_line_valid = 0;
                        $draw_lhs_pixel_valid = 0;
                        break;
                    }

                    if ($target_search_entry_lhs > $max_t_x) {
                        //
                        // The whole line is in the excluded area
                        //

                        $draw_line_valid = 0;
                        $draw_lhs_pixel_valid = 0;
                        break;
                    }

                    if (($target_search_entry_lhs < ($min_t_x - 2))
                        && (! $draw_line_valid)
                        && ($draw_lhs_pixel_valid)
                    ) {
                        //
                        // The line failed but the LHS pixel is okay.
                        // The LHS pixel is also too far to the left
                        // of the tchord, so we will just accept the
                        // option of drawing the LHS pixel
                        //

                        break;
                    }

                    if ($target_search_entry_rhs > $max_t_x) {
                        //
                        // The RHS is in the excluded area
                        //

                        $next_x = $max_t_x;

                        if ($draw_line_valid) {
                            //
                            // It is okay to proceed
                            // Just check for doubling
                            //

                            if ($current_x == $next_x) {
                                //
                                // Draw a pixel instead of a line since
                                // they are the same pixel.
                                //

                                $draw_line_valid = 0;
                                $draw_lhs_pixel_valid = 1;

                                $next_x = $current_x + 1;
                            }
                        } else {

                            if ($draw_tchord_line) {

                                $current_x = $max_t_x;
                            } else {

                                $draw_line_valid = 0;
                                $draw_lhs_pixel_valid = 0;
                            }
                        }
                    }
                }
                break;
            }
        }

        if (($trim_tips) || ($smooth_check)) {
            if ($draw_line_valid) {
                // a line is valid, so $prev_adj_pixel_drawn will
                // remain true

                $prev_adj_pixel_drawn = 1;
            } else {
                if ($draw_lhs_pixel_valid) {
                    if ($prev_adj_pixel_drawn) {
                        //
                        // The previous adjacent pixel was drawn,
                        // so this pixel is okay to draw as well
                        // However we will set the new value of
                        // $prev_adj_pixel_drawn to false
                        //
                    } else {
                        //
                        // Disable $draw_lhs_pixel_valid since the
                        // previous pixel before it was not drawn,
                        // and this pixel itself is a lone pixel
                        //

                        $draw_lhs_pixel_valid = 0;
                    }

                    $prev_adj_pixel_drawn = 0;
                }
            }
        }

        return array(
            $draw_line_valid,
            $draw_lhs_pixel_valid,
            $prev_adj_pixel_drawn,
            $current_x,
            $next_x
        );
    }


    /**
     * draws approximate lines between the x-y points in the supplied
     * array
     *
     * It uses _closestPoint to avoid double-filling pixels. The
     * benefits are noticeable when using alpha colours.
     *
     * @param resource &$image             reference to the PHP image
     *                      object
     * @param array    $input_array_points contains all the points
     *                      between which lines are to be drawn. The
     *                      points are in the order in which the lines
     *                      will connect. It is one-dimensional with
     *                      alternating X and Y values; each pair
     *                      represents a point
     * @param resource &$arc_color         reference to the shape
     *                      outline colour, assigned by
     *                      imagecolorallocate or
     *                      imagecolorallocatealpha
     * @param bool     $solid_shape        if true (default), then the
     *                      first and last points in
     *                      $input_array_points are connected. It is
     *                      optional: only an incomplete arc with
     *                      single-line thickness uses this parameter
     *                      in the false position.
     *
     * The line always goes from ($start_x,$start_y) to
     * ($next_x_approx,$next_x_approx)
     *
     * @return  void
     *
     * @access private
     *
     */
    private function _connectDots(
        &$image,
        $input_array_points,
        &$arc_color,
        $solid_shape = 1
    ) {
        //
        // Some key variables in this function:
        // float    $start_x            the x-value of the pixel
        //                          being drawn FROM
        // float    $start_y            the y-value of the pixel
        //                          being drawn FROM
        // float    $next_x             the x-value of the pixel
        //                          being drawn TO
        // float    $next_y             the y-value of the pixel
        //                          being drawn TO
        // float    $next_x_approx      the x-value of the pixel
        //                          just before ($next_x,$next_y)
        // float    $next_y_approx      the y-value of the pixel
        //                          just before ($next_x,$next_y)
        //

        $start_x = array_shift($input_array_points);
        $start_y = array_shift($input_array_points);

        if ($solid_shape) {
            //
            // append $start_x and $start_y to $input_array_points so
            // they can be connected as the last points in the shape.
            //

            $input_array_points[] = $start_x;
            $input_array_points[] = $start_y;
        }

        $num_rem_points = sizeof($input_array_points);

        for ($i = 0; $i < $num_rem_points; $i += 2) {
            $next_x = (int)($input_array_points[$i]);
            $next_y = (int)($input_array_points[$i + 1]);

            if (($start_x == $next_x) && ($start_y == $next_y)) {
                //
                // Do not draw anything since the points overlap
                //

                continue;
            } else {
                //
                // Get the distance from ($start_x, $start_y) to
                // ($next_x, $next_y).
                // The value has to be greater than 2 for a line to be
                // justified.
                //
                // NOTE: the value of "2" is not random. Mathematically,
                // if the distance between any two points is less than
                // 2 then it means the points are adjacent.  It means
                // that if one of the points is the centre of a 3x3 grid,
                // then the other point is one of the other 8 pixels
                // surrounding the centre.
                //

                $x_diff = abs($start_x) - abs($next_x);
                $x_diff_sqr = $x_diff * $x_diff;
                $y_diff = abs($start_y) - abs($next_y);
                $y_diff_sqr = $y_diff * $y_diff;

                if (($x_diff_sqr + $y_diff_sqr) > 2) {
                    //
                    // The distance from ($start_x, $start_y) to
                    // ($next_x, $next_y) is greater
                    // than 2 full pixels so they are distant enough
                    // to justify a line between them.
                    // The line will be drawn from ($start_x, $start_y)
                    // to ($next_x_approx, $next_y_approx), which is the
                    // pixel just before ($next_x, $next_y)
                    //

                    list($next_x_approx, $next_y_approx)
                        = $this->_closestPoint(
                            $start_x,
                            $start_y,
                            $next_x,
                            $next_y
                        );

                    imageline(
                        $image, $start_x, $start_y,
                        $next_x_approx, $next_y_approx, $arc_color
                    );
                } else {
                    //
                    // The distance from ($start_x, $start_y) to
                    // ($next_x_approx, $next_y_approx) is less
                    // than 2 full pixels so only draw the
                    // ($start_x, $start_y) pixel to avoid an overlap
                    //

                    imagesetpixel(
                        $image,
                        $start_x, $start_y,
                        $arc_color
                    );
                }
            }

            $start_x = $next_x;
            $start_y = $next_y;
        }
    }


    /**
     * Attempts to draw the arc with the supplied parameters
     *
     * @param resource &$image           a reference to the image
     *                      object
     * @param int      $centre_x         x-coordinate of the centre of
     *                      the shape
     * @param int      $centre_y         y-coordinate of the centre of
     *                      the shape
     * @param int      $outer_width      the full horizontal width of
     *                      the shape, assuming it was a full
     *                      unrotated ellipse
     * @param int      $outer_height     the full vertical height of
     *                      the shape, assuming it was a full
     *                      unrotated ellipse
     * @param float    $start_angle      the starting angle of the
     *                      revised shape
     * @param float    $end_angle        the ending angle of the
     *                      revised shape
     * @param resource &$arc_color       a reference to the shape's
     *                      colour from imagecolorallocate or
     *                      imagecolorallocatealpha
     * @param string   $obj_shape        the type of shape. Defaults
     *                      to 'arc'.
     * @param float    $alpha            the axis of rotation.
     *                      Defaults to 0.
     * @param int      $orig_thickness   the thickness. Defaults to 0.
     *                      It can be either a single number (for
     *                      uniform thickness) or a quoted string
     *                      containing two numbers separated by a
     *                      forward slash "/". The first number is the
     *                      thickness of the width and the second
     *                      number is the thickness of the height. For
     *                      either number, a negative value means that
     *                      the preference is for the shape to be
     *                      outlined instead of being filled. The
     *                      shape will only be outlined if BOTH
     *                      numbers are negative.
     * @param int      $height_thickness the optional thickness of the
     *                      unrotated height. If supplied, overrides
     *                      the height that the $orig_thickness
     *                      parameter would have provided. It is a
     *                      single number and it only modifies the
     *                      height. Defaults to null.
     *
     * If both $orig_thickness and $height_thickness are negative
     * numbers, the shape will be outlined instead of filled.
     *
     * @return array  containing the following parameters:
     *        string   $obj_shape        the standardised shape-type
     *        int      $errors           the sum of the error-codes
     *                      that were found when processing the shape.
     *                      A value of 8 or higher will result in the
     *                      shape not being drawn
     *
     * @access private
     *
     */
    private function _drawArc(
        &$image,
        $centre_x,
        $centre_y,
        $outer_width,
        $outer_height,
        $start_angle,
        $end_angle,
        &$arc_color,
        $obj_shape = 'arc',
        $alpha = 0,
        $orig_thickness = 0,
        $height_thickness = null
    ) {

        list(
            $_errors,
            $obj_shape,
            $outer_width,
            $outer_height,
            $width_thickness,
            $height_thickness,
            $start_angle,
            $end_angle,
            $arc_alpha,
            $outer_radii_swapped,
            $filled,
            $comp_check,
            $flipv_check,
            $fliph_check,
            $trim_tips,
            $smooth_check,

        ) = $this->_sanitiseShapeInputs(
            $obj_shape,
            $outer_width,
            $outer_height,
            $orig_thickness,
            $height_thickness,
            $start_angle,
            $end_angle,
            $alpha
        );

        if ($_errors >= 8) {
            //
            // The shape is improper.
            // Return without drawing anything
            //

            return array($obj_shape, $_errors);
        }

        //
        // At this point the shape can definitely be processed
        //

        //
        // Get the outer and inner radii
        //

        $outer_radius_x = $outer_width / 2;
        $outer_radius_y = $outer_height / 2;

        if (($width_thickness == 0)
            || ($height_thickness == 0)
        ) {
            $inner_radius_x = 0;
            $inner_radius_y = 0;
        } else {
            $inner_radius_x = $outer_radius_x - $width_thickness + 1;
            $inner_radius_y = $outer_radius_y - $height_thickness + 1;
        }

        //
        // NOTES:
        // A "grid" is my definition of the number of pixels in the
        //    length or breadth of a shape:
        //    (1) a 20x20 circle has x and y grids "even";
        //    (2) a 31x31 circle has x and y grids "odd";
        //    (3) a 51x20 ellipse has x grid "odd" and y grid "even";
        //
        // I need the grids so I can detect the difference
        // between a 20x20 circle and a 21x21 circle (the latter will
        // be slightly larger, and will probably have the pointy tips
        // since the middle of the circle is an explicit row/column
        // in the circle's parameters.
        //
        // I define a "half-integer" as the number half-way between
        // two integer values eg 12.5, 13.5, 22.5 etc.
        //
        // The formula for the range of values in a grid is given by
        //
        //    min_value = (grid_value - 1) / 2
        //
        // When the grid_value is odd, the values start and end with
        // integers with a difference of one between values
        // Examples:
        //     A grid of "5" goes from -2, -1, 0, 1, 2
        //     A grid of "9" goes from -4, -3, -2, -1, 0, 1, 2, 3, 4
        //
        // When the grid_value is even, the values start and end with
        // half-integers with a difference of one between values
        // Examples:
        //     A grid of "4" goes from -1.5, -0.5, 0.5, 1.5
        //     A grid of "6" goes from -2.5, -1.5, -0.5, 0.5, 1.5, 2.5
        //
        //
        // Assume the grid-types for both axes are odd.
        // The grid-type is a flag.  It is true if the grid-type is odd.
        //     ("true" also equals "1" which is odd, so you might find
        //     that little relationship handy)
        //

        $x_grid_odd = 1;
        $y_grid_odd = 1;

        //
        // Get the ROTATED start and end angles
        //

        $phi_start_angle = $this->_angleIn360($start_angle + $arc_alpha);
        $phi_end_angle   = $this->_angleIn360($end_angle + $arc_alpha);

        while ($phi_end_angle <= $phi_start_angle) {
            // The end angle must always be bigger than the start angle
            $phi_end_angle += 360;
        }

        //
        // Find the maximum outer height of the rotated shape.
        // This will be used to finalise $y_grid_odd ie if the
        // height's grid-type should be even or odd.
        //
        // The "_trans" means "transition" ie this is the
        // point where the height transitions from
        // increasing TO decreasing or vice versa.  It is the
        // point where dy/dx = 0.
        //
        // Note that this calculation is done independent of the
        // grid system.  The results here are used to determine
        // the grid type of the height.
        //

        list(
            $dydx_outer_x_trans,
            $dydx_outer_y_trans
        ) = $this->_getDyDxZeroForAlpha(
            $arc_alpha,
            $outer_radius_x, $outer_radius_y,
            0, $x_grid_odd, $y_grid_odd
        );

        if (($arc_alpha == 90)
            || ($arc_alpha == 270)
        ) {
            //
            // The COS and SIN angle approximations sometimes result in
            // small losses to the final angle.  Eg SIN(30) should always
            // be "0.5" and SIN(90) should always be "1", however the
            // PHP SIN(30) gives "0.4999999999" and SIN(90) - "0.99999999"
            // These differences mean that if a circle of radius 20 is
            // rotated through 90 degrees, the new radius height is 19.99
            // which becomes 19 after I take the (int) of it.
            //
            // This if test tries to minimise the impact of the
            // approximations.  For 90 and 270 I specifically tell the
            // program to just use the theoretical values that should
            // be expected: at 90 degrees, the new height becomes the
            // previous width.
            //

            $dydx_outer_y_trans = $outer_radius_x;
        }

        $outer_arc_y_max = abs($dydx_outer_y_trans);

        //
        // Find the maximum outer width of the rotated shape.
        //
        // It is calculated by using a reflected form of the original
        // ellipse.  Notice that the $ellipse_a and $ellipse_b
        // in the _getDyDxZeroForAlpha function below are
        // the HEIGHT and WIDTH respectively.  The rotation angle
        // is also different.
        // It is effectively the point where dx/dy = 0.
        //
        // This will be used to finalise $y_grid_odd ie if the
        // height's grid-type should be even or odd.
        //
        // Note that this calculation is done independent of the
        // grid system.  The results here are used to determine
        // the grid type of the width.
        //

        list(
            $ignore_y,
            $outer_arc_x_max
        ) = $this->_getDyDxZeroForAlpha(
            (180 - $arc_alpha),
            $outer_radius_y, $outer_radius_x,
            0, $x_grid_odd, $y_grid_odd
        );

        if (($arc_alpha == 90)
            || ($arc_alpha == 270)
        ) {
            //
            // The COS and SIN angle approximations sometimes result in
            // small losses to the final angle.  Eg SIN(30) should always
            // be "0.5" and SIN(90) should always be "1", however the
            // PHP SIN(30) gives "0.4999999999" and SIN(90) - "0.99999999"
            // These differences mean that if a circle of radius 20 is
            // rotated through 90 degrees, the new radius height is 19.99
            // which becomes 19 after I take the (int) of it.
            //
            // This if test tries to minimise the impact of the
            // approximations.  For 90 and 270 I specifically tell the
            // program to just use the theoretical values that should
            // be expected: at 90 degrees, the new width becomes the
            // previous height.
            //

            $outer_arc_x_max = $outer_radius_y;
        }

        $outer_arc_x_max = abs($outer_arc_x_max);

        //
        // Get the inner arc's angles
        //

        $inner_alpha = $arc_alpha;

        $inner_phi_start_angle    = $phi_start_angle;
        $inner_phi_end_angle    = $phi_end_angle;
        $inner_radii_swapped = 0;

        if ($inner_radius_x < $inner_radius_y) {
            //
            // The inner arc is vertical.
            //

            $inner_radii_swapped = 1;

            $temp_var = $inner_radius_x;
            $inner_radius_x = $inner_radius_y;
            $inner_radius_y = $temp_var;

            $inner_alpha += 90;
            $inner_phi_start_angle -= 90;
            $inner_phi_end_angle -= 90;
            while ($inner_phi_end_angle <= $inner_phi_start_angle) {
                $inner_phi_end_angle += 360;
            }
        }

        //
        // Get the maximum rotated height of the inner arc.
        // It uses a similar process to "$dydx_outer_y_trans"
        //
        // Note that this calculation is done using the
        // grid system.
        //

        list(
            $dydx_inner_x_trans,
            $dydx_inner_y_trans
        ) = $this->_getDyDxZeroForAlpha(
            $inner_alpha,
            $inner_radius_x, $inner_radius_y,
            1, $x_grid_odd, $y_grid_odd
        );

        if (($arc_alpha == 90)
            || ($arc_alpha == 270)
        ) {
            //
            // The explanations for this if test can be found above.
            // Search for "$dydx_outer_y_trans"
            //
            // NOTE: for inner arcs, the sign of the transition point
            // is also important because the inner arc can be in sync
            // with the outer arc (uniform thickness), or it can
            // be 90 degrees out of sync (the height thickness is less
            // than the width thickness.  The latter scenario only
            // works if the sign is taken into account.
            //

            $dydx_inner_y_trans
                = $this->_getSign($dydx_inner_y_trans) * $inner_radius_x;
        }

        $inner_arc_y_max = abs($dydx_inner_y_trans);
        $inner_arc_y_min = -$inner_arc_y_max;

        //
        // Get the maximum rotated width of the inner arc.
        // It uses a similar process to "$outer_arc_x_max"
        //
        // Note that this calculation is done using the
        // grid system.
        //

        list(
            $ignore_y,
            $inner_arc_x_max
        ) = $this->_getDyDxZeroForAlpha(
            (180 - $inner_alpha),
            $inner_radius_y, $inner_radius_x,
            1, $x_grid_odd, $y_grid_odd
        );

        if (($inner_alpha == 90)
            || ($inner_alpha == 270)
        ) {
            //
            // The explanations for this if test can be found above.
            // Search for "$outer_arc_x_max"
            //

            $inner_arc_x_max = $inner_radius_y;
        }

        $inner_arc_x_max = abs($inner_arc_x_max);

        $inner_y_start_val = $inner_arc_y_min;
        $inner_y_end_val = $inner_arc_y_max;

        if ($trim_tips) {
            $inner_y_start_val = $inner_arc_y_min + 1;
            $inner_y_end_val = $inner_arc_y_max - 1;
        }

        ////////
        //
        // Get the grid ranges
        //
        ////////

        $int_outer_arc_y_max = (int)$outer_arc_y_max;
        $outer_arc_y_max_plus_05_then_int = (int)($outer_arc_y_max + 0.5);

        if ($int_outer_arc_y_max == $outer_arc_y_max_plus_05_then_int) {
            $y_grid_odd = 1;

            $first_term = -($int_outer_arc_y_max);
            $last_term = ($int_outer_arc_y_max);
        } else {
            $y_grid_odd = 0;

            $first_term = -($int_outer_arc_y_max + 0.5);
            $last_term = ($int_outer_arc_y_max + 0.5);
        }
        $outer_arc_y_max = $this->_gridAdjust($outer_arc_y_max, $y_grid_odd);

        $true_height = $last_term - $first_term + 1;

        $int_outer_arc_x_max = (int)$outer_arc_x_max;
        $outer_arc_x_max_plus_05_then_int = (int)($outer_arc_x_max + 0.5);

        if ($int_outer_arc_x_max == $outer_arc_x_max_plus_05_then_int) {
            $x_grid_odd = 1;
        } else {
            $x_grid_odd = 0;
        }
        $outer_arc_x_max = $this->_gridAdjust($outer_arc_x_max, $x_grid_odd);

        $outer_grid_y_start = 1;
        $outer_grid_y_end = $true_height;

        $inner_grid_y_start
            = (int)(($true_height - ((int)$inner_arc_y_max * 2)) / 2) - 1;

        $inner_grid_y_end = $true_height - $inner_grid_y_start + 1 + 1;

        $y_grid_offset = ($y_grid_odd) ? 0 : 0.5;
        $x_grid_offset = ($x_grid_odd) ? 0 : 0.5;

        //
        // Initialise the parameters that a tchord would use.
        // These are ignored if the shape is not a tchord.
        // See the _getTChordParams function for the definitions
        // of these variables.
        //

        $definite_t_inner_arc_intersect = 0;
        $draw_tchord_line = 0;
        $tchord_tangential = 0;
        $t_line_points = array();
        $tchord_line_slope    = null;
        $t_inner_arc_intersect = array();

        $smallest_t_x    = null;
        $smallest_t_y    = null;
        $largest_t_x    = null;
        $largest_t_y    = null;

        $p_x            = null;
        $p_y            = null;
        $q_x            = null;
        $q_y            = null;
        $p_x_neighbour    = null;
        $p_y_neighbour    = null;
        $q_x_neighbour    = null;
        $q_y_neighbour    = null;

        $tchord_excl_side = 'none';

        if ($obj_shape == 'arctruechord') {

            list(
                $definite_t_inner_arc_intersect,
                $draw_tchord_line,
                $tchord_tangential,
                $t_line_points,
                $tchord_line_slope,
                $t_inner_arc_intersect,
                $smallest_t_x,
                $smallest_t_y,
                $largest_t_x,
                $largest_t_y,
                $p_x,
                $p_y,
                $q_x,
                $q_y,
                $p_x_neighbour,
                $p_y_neighbour,
                $q_x_neighbour,
                $q_y_neighbour,
                $inner_phi_start_angle,
                $inner_phi_end_angle,
                $tchord_excl_side
            ) = $this->_getTChordParams(
                $arc_alpha, $phi_start_angle, $phi_end_angle,
                $outer_radius_x, $outer_radius_y,
                $inner_alpha,
                $inner_phi_start_angle, $inner_phi_end_angle,
                $inner_radius_x, $inner_radius_y,
                $outer_arc_x_max, $outer_arc_y_max,
                $outer_radii_swapped,
                $inner_radii_swapped,
                $trim_tips,
                $x_grid_odd, $y_grid_odd
            );
        }

        if ($filled) {
            $this->_drawFilledShape(
                $image,
                $arc_color,
                $centre_x, $centre_y,
                $obj_shape,
                $arc_alpha,
                $inner_alpha,
                $phi_start_angle, $phi_end_angle,
                $inner_phi_start_angle, $inner_phi_end_angle,
                $outer_radius_x, $outer_radius_y,
                $inner_radius_x, $inner_radius_y,
                $dydx_outer_x_trans, $dydx_outer_y_trans,
                $dydx_inner_x_trans, $dydx_inner_y_trans,
                $outer_arc_x_max, $outer_arc_y_max,
                $inner_arc_x_max, $inner_arc_y_max,
                $true_height,
                $outer_grid_y_start, $outer_grid_y_end,
                $inner_grid_y_start, $inner_grid_y_end,
                $outer_radii_swapped,
                $inner_radii_swapped,
                $trim_tips,
                $smooth_check,
                $x_grid_odd, $y_grid_odd,
                $definite_t_inner_arc_intersect,
                $draw_tchord_line,
                $tchord_tangential,
                $t_line_points,
                $tchord_line_slope,
                $t_inner_arc_intersect,
                $smallest_t_x,
                $smallest_t_y,
                $largest_t_x,
                $largest_t_y,
                $p_x,
                $p_y,
                $q_x,
                $q_y,
                $p_x_neighbour,
                $p_y_neighbour,
                $q_x_neighbour,
                $q_y_neighbour,
                $tchord_excl_side
            );
        } else {

            $this->_drawOutlinedShape(
                $image,
                $arc_color,
                $centre_x, $centre_y,
                $obj_shape,
                $arc_alpha,
                $inner_alpha,
                $phi_start_angle, $phi_end_angle,
                $inner_phi_start_angle, $inner_phi_end_angle,
                $outer_radius_x, $outer_radius_y,
                $inner_radius_x, $inner_radius_y,
                $dydx_outer_x_trans, $dydx_outer_y_trans,
                $dydx_inner_x_trans, $dydx_inner_y_trans,
                $outer_arc_x_max, $outer_arc_y_max,
                $inner_arc_x_max, $inner_arc_y_max,
                $true_height,
                $outer_grid_y_start, $outer_grid_y_end,
                $inner_grid_y_start, $inner_grid_y_end,
                $outer_radii_swapped,
                $inner_radii_swapped,
                $trim_tips,
                $smooth_check,
                $x_grid_odd, $y_grid_odd,
                $definite_t_inner_arc_intersect,
                $draw_tchord_line,
                $tchord_tangential,
                $t_line_points,
                $tchord_line_slope,
                $t_inner_arc_intersect,
                $smallest_t_x,
                $smallest_t_y,
                $largest_t_x,
                $largest_t_y,
                $p_x,
                $p_y,
                $q_x,
                $q_y,
                $p_x_neighbour,
                $p_y_neighbour,
                $q_x_neighbour,
                $q_y_neighbour,
                $tchord_excl_side
            );
        }

        return array($obj_shape, $_errors);
    }


    /**
     * draws the filled shape from the specified parameters
     *
     * The sister function to _drawFilledShape() is
     * _drawOutlinedShape(). They take exactly the same arguments but
     * process the data in different ways as required.
     *
     * @param resource &$image                         a reference to
     *                      the image object
     * @param resource &$arc_color                     a reference to
     *                      the shape's colour from imagecolorallocate
     * @param int      $centre_x                       x-coordinate of
     *                      the centre of the shape
     * @param int      $centre_y                       y-coordinate of
     *                      the centre of the shape
     * @param string   $obj_shape                      the type of
     *                      shape
     * @param float    $arc_alpha                      the axis of
     *                      rotation
     * @param float    $inner_alpha                    the axis
     *                      rotation angle of the inner arc. It may be
     *                      different from $alpha.
     * @param float    $phi_start_angle                the outer
     *                      starting angle of the rotated shape
     * @param float    $phi_end_angle                  the outer
     *                      starting angle of the rotated shape
     * @param float    $inner_phi_start_angle          the angle that
     *                      point P makes with the origin
     * @param float    $inner_phi_end_angle            the angle that
     *                      point Q makes with the origin
     * @param float    $outer_radius_x                 the radius of
     *                      the unrotated outer width
     * @param float    $outer_radius_y                 the radius of
     *                      the unrotated outer height
     * @param float    $inner_radius_x                 the radius of
     *                      the unrotated inner arc width
     * @param float    $inner_radius_y                 the radius of
     *                      the unrotated inner arc's height
     * @param float    $dydx_outer_x_trans             The maximum
     *                      x-value of the rotated shape when centred
     *                      at the origin. It is calculated using the
     *                      formula of a rotated ellipse. It helps
     *                      determine the final of $x_grid_odd.
     * @param float    $dydx_outer_y_trans             The maximum
     *                      y-value of the rotated shape when centred
     *                      at the origin. It is calculated using the
     *                      formula of a rotated ellipse. It helps
     *                      determine the final of $y_grid_odd.
     * @param float    $dydx_inner_x_trans             The maximum
     *                      x-value of the rotated inner arc when
     *                      centred at the origin. It helps determine
     *                      the x-grid boundaries for the inner arc.
     * @param float    $dydx_inner_y_trans             The maximum
     *                      y-value of the rotated inner arc when
     *                      centred at the origin. It helps determine
     *                      the y-grid boundaries for the inner arc.
     * @param float    $outer_arc_x_max                The maximum
     *                      x-value of the rotated shape. It is half
     *                      of the total width-space of the rotated
     *                      ellipse.
     * @param float    $outer_arc_y_max                The maximum
     *                      y-value of the rotated shape. It is half
     *                      of the total height-space of the rotated
     *                      ellipse.
     * @param float    $inner_arc_x_max                The maximum
     *                      x-value of the rotated inner arc. It is
     *                      half of the total width-space of the
     *                      rotated inner arc's ellipse.
     * @param float    $inner_arc_y_max                The maximum
     *                      y-value of the rotated inner arc. It is
     *                      half of the total height-space of the
     *                      rotated inner arc's ellipse.
     * @param int      $true_height                    the pixel
     *                      height of the shape's full rotated ellipse
     *                      in the grid system. For filled shapes it
     *                      helps limit the range of plottable
     *                      y-values for the inner and outer arcs.
     * @param int      $outer_grid_y_start             the lowest
     *                      pixel of the shape's full ellipse in the
     *                      grid system. It is typically "1", unless
     *                      $trim_tips is enabled, in which case it
     *                      becomes 2 (thereby truncating the bottom
     *                      pixel of the shape)
     * @param int      $outer_grid_y_end               the highest
     *                      pixel of the shape's full ellipse in the
     *                      grid system. It typically equals the value
     *                      in $true_height unless $trim_tips is
     *                      enabled, in which case it becomes one less
     *                      than $true_height (thereby truncating the
     *                      top pixel of the shape)
     * @param int      $inner_grid_y_start             the lowest
     *                      pixel of the inner arc's ellipse in the
     *                      grid system.
     * @param int      $inner_grid_y_end               the highest
     *                      pixel of the inner arc's ellipse in the
     *                      grid system.
     * @param bool     $outer_radii_swapped            true if the
     *                      outer width and height were swapped.
     * @param bool     $inner_radii_swapped            true if the
     *                      inner width and height were swapped.
     * @param bool     $trim_tips                      true if the
     *                      inner and outer edges should be truncated
     * @param bool     $smooth_check                   true if lone
     *                      pixels should be removed
     * @param bool     $x_grid_odd                     true if the
     *                      width-space uses an odd number of pixels
     * @param bool     $y_grid_odd                     true if the
     *                      height-space uses an odd number of pixels
     * @param bool     $definite_t_inner_arc_intersect true if the
     *                      tchord intersects the inner arc
     * @param bool     $draw_tchord_line               true if the
     *                      tchord line should be explicitly drawn
     * @param bool     $tchord_tangential              true if the
     *                      tchord is considered tangential to the
     *                      inner arc
     * @param array    $t_line_points                  an array
     *                      containing all the x-y points that make up
     *                      up the tchord
     * @param float    $tchord_line_slope              the slope of
     *                      the tchord line
     * @param array    $t_inner_arc_intersect          an array
     *                      containing the points where the tchord
     *                      approximately intersected the inner arc
     * @param float    $smallest_t_x                   the x-value
     *                      corresponding to the lowest vertical pixel
     *                      of the tchord
     * @param float    $smallest_t_y                   the y-value of
     *                      the lowest vertical pixel of the tchord
     * @param float    $largest_t_x                    the x-value
     *                      corresponding to the highest vertical
     *                      pixel of the tchord
     * @param float    $largest_t_y                    the y-value of
     *                      the highest vertical pixel of the tchord
     * @param float    $p_x                            the x-value of
     *                      the P intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      starting point of the tchord
     * @param float    $p_y                            the y-value of
     *                      the P intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      starting point of the tchord
     * @param float    $q_x                            the x-value of
     *                      the Q intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      ending point of the tchord
     * @param float    $q_y                            the y-value of
     *                      the Q intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      ending point of the tchord
     * @param float    $p_x_neighbour                  the x-value of
     *                      the pixel that is just after point P
     * @param float    $p_y_neighbour                  the y-value of
     *                      the pixel that is just after point P
     * @param float    $q_x_neighbour                  the x-value of
     *                      the pixel that is just before point Q
     * @param float    $q_y_neighbour                  the y-value of
     *                      the pixel that is just before point Q
     * @param string   $tchord_excl_side               the area of the
     *                      shape that is excluded because of the
     *                      presence of the tchord line
     *
     * @return void
     *
     * @access private
     *
     */
    private function _drawFilledShape(
        &$image,
        &$arc_color,
        $centre_x, $centre_y,
        $obj_shape,
        $arc_alpha,
        $inner_alpha,
        $phi_start_angle, $phi_end_angle,
        $inner_phi_start_angle, $inner_phi_end_angle,
        $outer_radius_x, $outer_radius_y,
        $inner_radius_x, $inner_radius_y,
        $dydx_outer_x_trans, $dydx_outer_y_trans,
        $dydx_inner_x_trans, $dydx_inner_y_trans,
        $outer_arc_x_max, $outer_arc_y_max,
        $inner_arc_x_max, $inner_arc_y_max,
        $true_height,
        $outer_grid_y_start, $outer_grid_y_end,
        $inner_grid_y_start, $inner_grid_y_end,
        $outer_radii_swapped,
        $inner_radii_swapped,
        $trim_tips,
        $smooth_check,
        $x_grid_odd, $y_grid_odd,
        $definite_t_inner_arc_intersect,
        $draw_tchord_line,
        $tchord_tangential,
        $t_line_points,
        $tchord_line_slope,
        $t_inner_arc_intersect,
        $smallest_t_x,
        $smallest_t_y,
        $largest_t_x,
        $largest_t_y,
        $p_x,
        $p_y,
        $q_x,
        $q_y,
        $p_x_neighbour,
        $p_y_neighbour,
        $q_x_neighbour,
        $q_y_neighbour,
        $tchord_excl_side
    ) {

        //
        // Drawing a FILLED arc
        //
        // The shape should be filled, so we will achieve this by
        // drawing lines between the outer and inner points.
        //
        // The process is below.  Some steps are skipped if the
        // shape does not require them:
        //  (0) The tchord parameters have already been collected
        //  (1) Get the min segment line ie the segment line
        //      corresponding to the start of an arc.  Also
        //      determine which side of the shape the min segment
        //      line excludes.
        //  (2) Get the max segment line ie the segment line
        //      corresponding to the end of an arc.  Also
        //      determine which side of the shape the max segment
        //      line excludes.
        //  (3) Get the chord line ie the segment line corresponding
        //      to the connection of the inner tips of an arc.
        //      Also determine which side of the shape the chord
        //      line excludes.
        //  (4) Get the outer arc points.  Where necessary check them
        //      against the exclusions of points 1, 2 and 3 above.
        //  (5) Get the inner arc points.  Where necessary check them
        //      against the exclusions of points 1, 2 and 3 above.
        //  (6) If the shape is hollow (eg a regular arc),
        //      specifically exclude the points just within the inner
        //      arc points.  This ensures that nothing gets drawn
        //      through the hollow area.
        //  (7) Having completed steps 1 to 6 above, now draw the
        //      shape, one y-row at a time.  If it is a tchord, do
        //      a final check against the tchord line.
        //      This method effectively determines what the shape
        //      would be WITHOUT the tchord, then overlays the tchord
        //      line on the shape for the last check before drawing.
        //

        //
        // Initialise the arrays that store the points and exclusions
        //

        $filled_points_all = array();
        $excl_x_points = array();
        $finalised_excl_x_points = array();
        $inner_excl_x_points = array();

        if ($trim_tips) {
            //
            // Skip the top and bottom rows of the shape
            //
            $outer_grid_y_start++;
            $outer_grid_y_end--;
        }

        //
        // Get the segment lines and their exclusions
        //

        $min_line_slope = null;
        $max_line_slope = null;
        $chord_line_slope = null;

        $min_seg_true_points = array();
        $max_seg_true_points = array();
        $chord_seg_true_points = array();

        if ((($phi_start_angle + 360) == $phi_end_angle)
            || ($obj_shape == 'circle')
            || ($obj_shape == 'ellipse')
            || ($obj_shape == 'arctruechord')
        ) {
            //
            // This is a complete shape, or it is a tchord.
            // None of these shapes need the min and
            // max segment lines.
            // Do not bother calculating the segment lines
            //
        } else {

            ////////////////////
            //
            // Get the $min_seg_line details
            //

            list(
                $min_seg_line_x_start,
                $min_seg_line_y_start
            ) = $this->_getEllipseXYGivenPhi(
                $phi_start_angle, $inner_alpha,
                $inner_radius_x, $inner_radius_y,
                1, $x_grid_odd, $y_grid_odd
            );

            list(
                $min_seg_line_x_end,
                $min_seg_line_y_end
            ) = $this->_getEllipseXYGivenPhi(
                $phi_start_angle, $arc_alpha,
                $outer_radius_x, $outer_radius_y,
                1, $x_grid_odd, $y_grid_odd
            );

            //
            // Determine which side the min exclusions are on.
            // Eg 'exclude_right' means that any pixel on the RIGHT
            // of the min segment line is excluded.  If the starting
            // angle is at 90 degrees, then anything to the right
            // should not be drawn.
            // Sometimes some other factors can prevent this rule
            // from taking full effect.
            //

            if ($min_seg_line_y_start < $min_seg_line_y_end) {
                $min_excl_side = 'exclude_right';
            } elseif (
                $min_seg_line_y_start > $min_seg_line_y_end
            ) {
                $min_excl_side = 'exclude_left';
            } elseif (
                $min_seg_line_y_start == $min_seg_line_y_end
            ) {
                if ($min_seg_line_x_start < $min_seg_line_x_end) {
                    $min_excl_side = 'exclude_bottom';
                } elseif (
                    $min_seg_line_x_start > $min_seg_line_x_end
                ) {
                    $min_excl_side = 'exclude_top';
                } elseif (
                    $min_seg_line_x_start == $min_seg_line_x_end
                ) {
                    $min_excl_side = 'none';
                }
            }

            $min_seg_line_points_all_x = $this->_getLinePointsAllXs(
                $min_seg_line_x_start, $min_seg_line_y_start,
                $min_seg_line_x_end, $min_seg_line_y_end,
                $x_grid_odd, $y_grid_odd
            );
            $min_line_slope = array_pop($min_seg_line_points_all_x);
            $num_points = array_pop($min_seg_line_points_all_x);

            $num_points_times_2 = $num_points * 2;

            for ($k = 0; $k < $num_points_times_2; $k += 2) {
                //
                // This is to add all the x-points into
                // $filled_points_all.  It stops horizontal
                // gaps from appearing
                //
                // Note that the exclusion checking is only done
                // using the _getLinePointsAllYs() calculations
                //

                if ($y_grid_odd) {
                    $min_seg_y_keystr
                        = $min_seg_line_points_all_x[$k + 1];
                } else {
                    $min_seg_y_keystr
                        = (int)($min_seg_line_points_all_x[$k + 1] + 0.5);
                }

                $min_seg_true_points[$min_seg_y_keystr][]
                    = $min_seg_line_points_all_x[$k];

                //
                // All the entries in the $min_seg_line_points_all_x
                // array are automatically added into $filled_points_all
                // without any checking for exclusions.
                //
                // This contrasts with $max_seg_line_points: the
                // latter has to perform several checks for exclusions
                // before it can safely add an entry into
                // $filled_points_all
                //

                $filled_points_all[$min_seg_y_keystr][]
                    = $min_seg_line_points_all_x[$k];
            }

            //
            // Get the points that lie on the min_angle segment line.
            //
            // Note: _getLinePointsAllYs() is used because it ensures
            // that every y-value is accounted for.
            //

            $min_seg_line_points_all_y = $this->_getLinePointsAllYs(
                $min_seg_line_x_start, $min_seg_line_y_start,
                $min_seg_line_x_end, $min_seg_line_y_end,
                $x_grid_odd, $y_grid_odd
            );
            $min_line_slope = array_pop($min_seg_line_points_all_y);
            $num_points = array_pop($min_seg_line_points_all_y);

            $num_points_times_2 = $num_points * 2;
            $num_points_times_2_minus_2 = $num_points * 2 - 2;

            for ($k = 0; $k < $num_points_times_2; $k += 2) {

                //
                // $min_seg_y_keystr is the label attached
                // to this entry in the $filled_points_all array.
                // My preference was to use the raw y-values,
                // however the PHP array_key_exists() function
                // complains if the raw y-value is a half-integer.
                // I tried using the (string) typecast but it was
                // not reliable, especially if the approximation
                // was something like SIN(30).
                // Anyway, an advantage of using $min_seg_y_keystr
                // in this way is that it becomes an integer AND it
                // now matches with the exact row that the pixels
                // be drawn in for the shape.
                //

                $min_seg_y_keystr = (int)(
                    $min_seg_line_points_all_y[$k + 1] + 0.5
                );

                if ($trim_tips) {
                    if (abs($min_seg_line_points_all_y[$k]) >= $outer_arc_x_max
                    ) {
                        $min_seg_line_points_all_y[$k]
                            = $this->_getSign($min_seg_line_points_all_y[$k])
                            * (abs($min_seg_line_points_all_y[$k]) - 1);
                    }
                }

                $min_seg_true_points[$min_seg_y_keystr][]
                    = $min_seg_line_points_all_y[$k];

                //
                // All the entries in the $min_seg_line_points_all_y
                // array are automatically added into $filled_points_all
                // without any checking for exclusions.
                //
                // This contrasts with $max_seg_line_points: the
                // latter has to perform several checks for exclusions
                // before it can safely add an entry into
                // $filled_points_all
                //

                $filled_points_all[$min_seg_y_keystr][]
                    = $min_seg_line_points_all_y[$k];
            }

            //
            // Arrange the $min_seg_true_points entries into a
            // straight line from lowest to highest x
            //

            $min_ykeys_array = array_keys($min_seg_true_points);
            if (sizeof($min_ykeys_array) > 0) {
                sort($min_ykeys_array);
                foreach ($min_ykeys_array as $min_ykey) {
                    sort($min_seg_true_points[$min_ykey]);
                }
            }

            //
            // Get the min exclusions:
            //

            foreach ($min_ykeys_array as $min_ykey) {

                $row_excl_x_point = null;

                $num_x_entries = sizeof(
                    $min_seg_true_points[$min_ykey]
                );

                $lowest_x = $min_seg_true_points[$min_ykey][0];
                $highest_x
                    = $min_seg_true_points[$min_ykey][$num_x_entries - 1];

                if ($min_excl_side == 'exclude_left') {
                    $row_excl_x_point = $lowest_x - 1;

                    $excl_x_points[$min_ykey][]
                        = $row_excl_x_point;
                } elseif ($min_excl_side == 'exclude_right') {
                    $row_excl_x_point = $highest_x + 1;

                    $excl_x_points[$min_ykey][]
                        = $row_excl_x_point;
                }
            }

            ////////////////////
            //
            // Get the $max_seg_line details
            //

            list(
                $max_seg_line_x_start,
                $max_seg_line_y_start
            ) = $this->_getEllipseXYGivenPhi(
                $phi_end_angle, $inner_alpha,
                $inner_radius_x, $inner_radius_y,
                1, $x_grid_odd, $y_grid_odd
            );

            list(
                $max_seg_line_x_end,
                $max_seg_line_y_end
            ) = $this->_getEllipseXYGivenPhi(
                $phi_end_angle, $arc_alpha,
                $outer_radius_x, $outer_radius_y,
                1, $x_grid_odd, $y_grid_odd
            );

            //
            // Determine which side the max exclusions are on.
            // 'exclude_right' means that any pixel on the RIGHT
            // of the max segment line is excluded.  If the starting
            // angle is at 90 degrees, then anything to the right
            // should not be drawn.
            // Sometimes some other factors can prevent this rule
            // from taking full effect.
            //
            // Note that the max exclusions are somewhat OPPOSITE
            // to what the min exclusions are.
            //

            if ($max_seg_line_y_start < $max_seg_line_y_end) {
                $max_excl_side = 'exclude_left';
            } elseif (
                $max_seg_line_y_start > $max_seg_line_y_end
            ) {
                $max_excl_side = 'exclude_right';
            } elseif (
                $max_seg_line_y_start == $max_seg_line_y_end
            ) {
                if ($max_seg_line_x_start < $max_seg_line_x_end) {
                    $max_excl_side = 'exclude_top';
                } elseif (
                    $max_seg_line_x_start > $max_seg_line_x_end
                ) {
                    $max_excl_side = 'exclude_bottom';
                } elseif (
                    $max_seg_line_x_start == $max_seg_line_x_end
                ) {
                    $max_excl_side = 'none';
                }
            }

            $max_seg_line_points_all_x = $this->_getLinePointsAllXs(
                $max_seg_line_x_start, $max_seg_line_y_start,
                $max_seg_line_x_end, $max_seg_line_y_end,
                $x_grid_odd, $y_grid_odd
            );
            $max_line_slope = array_pop($max_seg_line_points_all_x);
            $num_points = array_pop($max_seg_line_points_all_x);

            $num_points_times_2 = $num_points * 2;

            for ($k = 0; $k < $num_points_times_2; $k += 2) {
                //
                // This is to add all the x-points into
                // $filled_points_all.  It stops horizontal
                // gaps from appearing
                //
                // Note that the exclusion checking is only done
                // using the _getLinePointsAllYs() calculations
                //

                if ($y_grid_odd) {
                    $max_seg_y_keystr
                        = $max_seg_line_points_all_x[$k + 1];
                } else {
                    $max_seg_y_keystr
                        = (int)($max_seg_line_points_all_x[$k + 1] + 0.5);
                }

                if ($trim_tips) {
                    if (abs($max_seg_line_points_all_x[$k]) >= $outer_arc_x_max
                    ) {
                        $max_seg_line_points_all_x[$k]
                            = $this->_getSign($max_seg_line_points_all_x[$k])
                            * (abs($max_seg_line_points_all_x[$k]) - 1);
                    }
                }

                $max_seg_true_points[$max_seg_y_keystr][]
                    = $max_seg_line_points_all_x[$k];
            }

            //
            // Get the points that lie on the max segment line.
            //
            // Note: _getLinePointsAllYs() is used because it ensures
            // that every y-value is accounted for.
            //

            $max_seg_line_points_all_y = $this->_getLinePointsAllYs(
                $max_seg_line_x_start, $max_seg_line_y_start,
                $max_seg_line_x_end, $max_seg_line_y_end,
                $x_grid_odd, $y_grid_odd
            );
            $max_line_slope = array_pop($max_seg_line_points_all_y);
            $num_points = array_pop($max_seg_line_points_all_y);

            $num_points_times_2 = $num_points * 2;
            $num_points_times_2_minus_2 = $num_points * 2 - 2;

            for ($k = 0; $k < $num_points_times_2; $k += 2) {

                //
                // $max_seg_y_keystr is the label attached
                // to this entry in the $filled_points_all array.
                // My preference was to use the raw y-values,
                // however the PHP array_key_exists() function
                // complains if the raw y-value is a half-integer.
                // I tried using the (string) typecast but it was
                // not reliable, especially if the approximation
                // was something like SIN(30).
                // Anyway, an advantage of using $max_seg_y_keystr
                // in this way is that it becomes an integer AND it
                // now matches with the exact row that the pixels
                // be drawn in for the shape.
                //

                $max_seg_y_keystr = (int)(
                    $max_seg_line_points_all_y[$k + 1] + 0.5
                );

                if ($trim_tips) {
                    if (abs($max_seg_line_points_all_y[$k]) >= $outer_arc_x_max
                    ) {
                        $max_seg_line_points_all_y[$k]
                            = $this->_getSign($max_seg_line_points_all_y[$k])
                            * (abs($max_seg_line_points_all_y[$k]) - 1);
                    }
                }

                $max_seg_true_points[$max_seg_y_keystr][]
                    = $max_seg_line_points_all_y[$k];
            }

            //
            // Arrange the $max_seg_true_points entries into a
            // straight line with the x values in increasing
            // order
            //

            $max_ykeys_array = array_keys($max_seg_true_points);
            if (sizeof($max_ykeys_array) > 0) {
                sort($max_ykeys_array);
                foreach ($max_ykeys_array as $max_ykey) {

                        sort($max_seg_true_points[$max_ykey]);
                }
            }

            //
            // Get the max exclusions:
            //

            foreach ($max_ykeys_array as $max_ykey) {

                $row_excl_x_point = null;

                $num_x_entries = sizeof(
                    $max_seg_true_points[$max_ykey]
                );

                $lowest_x = $max_seg_true_points[$max_ykey][0];
                $highest_x
                    = $max_seg_true_points[$max_ykey][$num_x_entries - 1];

                if ($max_excl_side == 'exclude_left') {
                    $row_excl_x_point = $lowest_x - 1;
                } elseif ($max_excl_side == 'exclude_right') {
                    $row_excl_x_point = $highest_x + 1;
                }

                //
                // Check if $row_excl_x_point overlaps a valid
                // x-point that was created for the min segment line.
                // If the overlap exists, DO NOT include
                // $row_excl_x_point into $excl_x_points
                //

                if ($row_excl_x_point != null) {
                    $max_excl_min_incl_overlap_found = 0;
                    if (array_key_exists(
                        $max_ykey,
                        $filled_points_all
                    )
                    ) {
                        foreach (
                            $filled_points_all[$max_ykey]
                            as $pixel_x
                        ) {
                            if ($pixel_x == $row_excl_x_point) {
                                $max_excl_min_incl_overlap_found = 1;
                                break;
                            }
                        }
                    }
                    if (!$max_excl_min_incl_overlap_found) {
                        // It is okay to exclude this point

                        if ($max_excl_side != 'none') {
                            $excl_x_points[$max_ykey][]
                                = $row_excl_x_point;
                        }
                    }
                }

                //
                // Check if the x-value range from $lowest_x to
                // $highest_x for the max line overlaps an
                // excluded x-point that was created for the
                // minimum segment line.
                // If the overlap exists, DISCARD the previous excluded
                // x-point from $excl_x_points
                //

                if (array_key_exists(
                    $max_ykey,
                    $excl_x_points
                )
                ) {
                    $max_incl_min_excl_overlap_found = 0;
                    $idx_counter = -1;
                    foreach (
                        $excl_x_points[$max_ykey]
                        as $excluded_pixel_x
                    ) {
                        $idx_counter++;

                        if (($excluded_pixel_x >= $lowest_x)
                            && ($excluded_pixel_x <= $highest_x)
                        ) {
                            $max_incl_min_excl_overlap_found = 1;
                            break;
                        }
                    }
                    if ($max_incl_min_excl_overlap_found) {

                        array_splice(
                            $excl_x_points[$max_ykey],
                            $idx_counter, 1, -30303030
                        );
                    }
                }

                //
                // Check if this valid x-point already exists
                // in $filled_points_all.
                // If so, it means that we should DISCARD any
                // previous exclusions for the points
                // to the LEFT AND RIGHT of this x-point.
                //

                $pre_existing_pixel_x = null;

                if (array_key_exists(
                    $max_ykey,
                    $filled_points_all
                )
                ) {
                    $max_incl_min_incl_overlap_found = 0;
                    foreach (
                        $filled_points_all[$max_ykey]
                        as $existing_pixel_x
                    ) {
                        if (($existing_pixel_x >= $lowest_x)
                            && ($existing_pixel_x <= $highest_x)
                        ) {
                            $pre_existing_pixel_x = $existing_pixel_x;
                            $max_incl_min_incl_overlap_found = 1;
                            break;
                        }
                    }
                    if ($max_incl_min_incl_overlap_found) {
                        $idx_counter = -1;

                        $temp_overlap_array = array();

                        $x_on_lhs = $pre_existing_pixel_x - 1;
                        $x_on_rhs = $pre_existing_pixel_x + 1;

                        if (array_key_exists(
                            $max_ykey,
                            $excl_x_points
                        )
                        ) {
                            foreach (
                                $excl_x_points[$max_ykey]
                                as $excluded_pixel_x
                            ) {
                                $idx_counter++;

                                if (($excluded_pixel_x == $x_on_lhs)
                                    || ($excluded_pixel_x == $x_on_rhs)
                                ) {
                                    // Put $idx_counter at the front of
                                    // $temp_overlap_array.  That way it
                                    // is easier to remove them later on
                                    array_unshift(
                                        $temp_overlap_array,
                                        $idx_counter
                                    );
                                }
                            }
                            foreach ($temp_overlap_array as $curr_idx) {
                                // removing from back to front

                                array_splice(
                                    $excl_x_points[$max_ykey],
                                    $curr_idx, 1, -20202020
                                );
                            }
                        }
                    }
                }

                //
                // Now add the $max_seg_true_points[$max_ykey] points
                // into the $filled_points_all array
                // Note that I double-up on the entries at the start
                // and end of the lines.
                //

                $filled_points_all[$max_ykey][] = $lowest_x;
                $filled_points_all[$max_ykey][] = $highest_x;

                $outer_angle_sweep_is_not_360 = 0;
                if (($phi_start_angle + 360) != $phi_end_angle) {
                    //
                    // The start and end angles are the same point:
                    // This shape is really a complete ellipse.
                    // Do not provide any exclusions
                    $outer_angle_sweep_is_not_360 = 1;
                }

                $processing_outer_tip = 0;
                if (abs($max_seg_line_y_end - $max_ykey) < 1) {
                    //
                    // This $max_ykey is the outer tip.
                    // The grid system means the result might differ
                    // by about 0.5
                    //
                    $processing_outer_tip = 1;
                }

                if ($processing_outer_tip
                    && ($max_excl_side != 'none')
                    && $outer_angle_sweep_is_not_360
                ) {
                    // For the last k, we can force the exclusion to
                    // apply since it is the connection to the outer arc.
                    //
                    // Do not apply the exclusion if $max_excl_side
                    // says 'none'.
                    //
                    // Also do not apply the exclusion if the start and
                    // end angles are the same point
                    //
                    // Note that a chord can not have this exclusion
                    // since it only connects to points on the inner arc
                    //

                    $excl_x_points[$max_ykey][]
                        = $row_excl_x_point;
                }
            }

            if ($obj_shape == 'arcchord') {

                ////////////////////
                //
                // Get the $chord_angle_seg_line details
                //

                $chord_line_x_start = $min_seg_line_x_start;
                $chord_line_y_start = $min_seg_line_y_start;
                $chord_line_x_end = $max_seg_line_x_start;
                $chord_line_y_end = $max_seg_line_y_start;

                if ($chord_line_y_start < $chord_line_y_end) {
                    $chord_excl_side = 'exclude_left';
                } elseif ($chord_line_y_start > $chord_line_y_end) {
                    $chord_excl_side = 'exclude_right';
                } elseif ($chord_line_y_start == $chord_line_y_end) {
                    if ($chord_line_x_start < $chord_line_x_end) {
                        $chord_excl_side = 'exclude_top';
                    } elseif ($chord_line_x_start > $chord_line_x_end) {
                        $chord_excl_side = 'exclude_bottom';
                    } elseif ($chord_line_x_start == $chord_line_x_end) {
                        $chord_excl_side = 'none';
                    }
                }

                $chord_line_points = $this->_getLinePointsAllYs(
                    $chord_line_x_start, $chord_line_y_start,
                    $chord_line_x_end, $chord_line_y_end,
                    $x_grid_odd, $y_grid_odd
                );
                $chord_line_slope = array_pop($chord_line_points);
                $num_points = array_pop($chord_line_points);

                $num_points_times_2 = $num_points * 2;

                $last_k_to_exclude = $num_points_times_2 - 2;

                for ($k = 0; $k < $num_points_times_2; $k += 2) {
                    $chord_y_keystr = (int)(
                        $chord_line_points[$k + 1] + 0.5
                    );

                    //
                    // Get the chord exclusions:
                    //
                    // $exclusion_processed tracks whether this chord
                    // exclusion has been processed by one of the
                    // tests below.  There is a catch-all at the
                    // bottom that will process the exclusion if none
                    // of the other tests catches it beforehand.
                    //

                    $exclusion_processed = 0;

                    $row_excl_x_point = null;

                    if ($chord_excl_side == 'exclude_left') {
                        $row_excl_x_point
                            = $chord_line_points[$k] - 1;
                    } elseif (
                        $chord_excl_side == 'exclude_right'
                    ) {
                        $row_excl_x_point
                            = $chord_line_points[$k] + 1;
                    }

                    if ($row_excl_x_point != null) {

                        //
                        // Check if $row_excl_x_point overlaps a
                        // valid x-point that was created for the
                        // min segment line.
                        // If the overlap exists, DO NOT include
                        // $row_excl_x_point into $excl_x_points
                        //

                        $chord_excl_minmax_incl_overlap_found = 0;
                        if (array_key_exists(
                            $chord_y_keystr,
                            $filled_points_all
                        )
                        ) {
                            foreach (
                                $filled_points_all[$chord_y_keystr]
                                as $pixel_x
                            ) {
                                if ($pixel_x == $row_excl_x_point) {
                                    $chord_excl_minmax_incl_overlap_found
                                        = 1;
                                    break;
                                }
                            }
                        }
                        if (!$chord_excl_minmax_incl_overlap_found) {
                            // It is okay to exclude this point

                            $exclusion_processed += 10;

                            if ($chord_excl_side != 'none') {
                                $excl_x_points[$chord_y_keystr][]
                                    = $row_excl_x_point;
                            }
                        }
                    }

                    //
                    // Check if valid x-point overlaps an excluded
                    // x-point that was created for the min segment
                    // line.
                    // If the overlap exists, DISCARD the previous
                    // excluded x-point from $excl_x_points
                    //

                    if (array_key_exists(
                        $chord_y_keystr,
                        $excl_x_points
                    )
                    ) {
                        $chord_incl_minmax_excl_overlap_found = 0;
                        $idx_counter = -1;
                        foreach (
                            $excl_x_points[$chord_y_keystr]
                            as $excluded_pixel_x
                        ) {
                            $idx_counter++;

                            if ($excluded_pixel_x == $chord_line_points[$k]
                            ) {
                                $chord_incl_minmax_excl_overlap_found = 1;
                                break;
                            }
                        }
                        if ($chord_incl_minmax_excl_overlap_found) {

                            $exclusion_processed += 200;

                            array_splice(
                                $excl_x_points[$chord_y_keystr],
                                $idx_counter, 1, -70707070
                            );
                        }
                    }

                    //
                    // Check if this valid x-point already exists in
                    // $filled_points_all.
                    // If so, it means that we should DISCARD any
                    // previous exclusions for the points
                    // to the LEFT AND RIGHT of this x-point
                    //

                    if (array_key_exists(
                        $chord_y_keystr,
                        $filled_points_all
                    )
                    ) {
                        $chord_incl_minmax_incl_overlap_found = 0;
                        foreach (
                            $filled_points_all[$chord_y_keystr]
                            as $existing_pixel_x
                        ) {
                            if ($existing_pixel_x == $chord_line_points[$k]
                            ) {
                                $chord_incl_minmax_incl_overlap_found = 1;
                                break;
                            }
                        }
                        if ($chord_incl_minmax_incl_overlap_found) {
                            $idx_counter = -1;

                            $temp_overlap_array = array();

                            $x_on_lhs = $chord_line_points[$k] - 1;
                            $x_on_rhs = $chord_line_points[$k] + 1;

                            $exclusion_processed += 4000;

                            if (array_key_exists(
                                $chord_y_keystr,
                                $excl_x_points
                            )
                            ) {
                                foreach (
                                    $excl_x_points[$chord_y_keystr]
                                    as $excluded_pixel_x
                                ) {
                                    $idx_counter++;

                                    if (($excluded_pixel_x == $x_on_lhs)
                                        || ($excluded_pixel_x == $x_on_rhs)
                                    ) {
                                        //
                                        // Put $idx_counter at the front
                                        // of $temp_overlap_array.  That
                                        // way it is easier to remove
                                        // them later on
                                        //
                                        array_unshift(
                                            $temp_overlap_array,
                                            $idx_counter
                                        );
                                    }
                                }
                                foreach (
                                    $temp_overlap_array as $curr_idx
                                ) {
                                    // removing from back to front

                                    $exclusion_processed += 80000;

                                    array_splice(
                                        $excl_x_points[$chord_y_keystr],
                                        $curr_idx, 1, -40506070
                                    );
                                }
                            }
                        }
                    }

                    if ($exclusion_processed == 0) {
                        //
                        // None of the other tests above told us
                        // NOT to allow this exclusion, so we will
                        //

                        $excl_x_points[$chord_y_keystr][]
                            = $row_excl_x_point;
                    }

                    //
                    // Do a last check to see if we can apply
                    // $row_excl_x_point for this chord.
                    //
                    // If we are at the top or bottom of the chord
                    // where it intersects one of the segment lines,
                    // check if $chord_excl_side has the same
                    // value as $min_excl_side or
                    // $max_excl_side.  If the value is the same,
                    // then manually add an exclusion for the pixel
                    // to the side of the chord
                    //

                    if ($chord_y_keystr == $chord_line_y_start) {

                        if ($chord_excl_side == $min_excl_side) {

                            if (($chord_excl_side == 'exclude_left')
                                || ($chord_excl_side == 'exclude_right')
                            ) {
                                //
                                // $row_excl_x_point is valid
                                //

                                $excl_x_points[$chord_y_keystr][]
                                    = $row_excl_x_point;
                            }
                        }
                    }
                    if ($chord_y_keystr == $chord_line_y_end) {

                        if ($chord_excl_side == $max_excl_side) {

                            if (($chord_excl_side == 'exclude_left')
                                || ($chord_excl_side == 'exclude_right')
                            ) {
                                //
                                // $row_excl_x_point is valid
                                //

                                $excl_x_points[$chord_y_keystr][]
                                    = $row_excl_x_point;
                            }
                        }
                    }

                    //
                    // Note that I double-up on the entries at the
                    // start and end of the lines.
                    //

                    $filled_points_all[$chord_y_keystr][]
                        = $chord_line_points[$k];
                }
            }

            //
            // Due to approximations, it is sometimes possible for
            // the outer ends of the segment lines to not sync up
            // accurately with expectations.
            // If the start and end angles have the same y-value
            // in the outer arc, then check if the sweep from the
            // start to the end is greater than 180 degrees.  If
            // it is, then we are in the special case where these
            // two tips on the outer arc will try to connect to each
            // other.  To prevent this we will add an exclusion at
            // their mid-point.
            //
            // Here is the breakdown of the conditions below:
            //
            // (1)  Do the outer tips of the min & max segment lines
            //      have the same y-value?
            // (2)  Is the sweep of angles greater than 180 degrees?
            // (3)  Do the outer tips of the min & max segment lines
            //      have different x-values? (This is true if they
            //      are distinct points.  It also confirms that we
            //      are not dealing with a shape that is really a
            //      full circle or ellipse)
            // (4)  Is there a gap of at least more than 1 pixel
            //      between the full top of the shape and the full
            //      top of the inner arc?  (If $outer_arc_y_max and
            //      $inner_arc_y_max are too close, it could mean
            //      that the shape has a height-thickness of 1, in
            //      which case we do NOT want to execute the
            //      condition)
            // (5a) Does the shape have at least an inner height
            //      of "2"? This would also mean that the thickness
            //      is greater than zero, in which case the outer
            //      tips could qualify for this rule OR
            // (5b) Is the shape a segment?  My tests showed that
            //      segments always needed this rule unless they were
            //      full circles or ellipses (ie condition 3 above)
            //

            $outer_y_tips_match = 0;
            if ($min_seg_line_y_end == $max_seg_line_y_end) {
                $outer_y_tips_match = 1;
            }

            $outer_angle_sweep_exceeds_180 = 0;
            if ((($phi_start_angle + 360) != $phi_end_angle)
                && (($phi_end_angle - $phi_start_angle) >= 180)
            ) {
                $outer_angle_sweep_exceeds_180 = 1;
            }

            $outer_x_tips_are_different = 0;
            if ($min_seg_line_x_end != $max_seg_line_x_end) {
                $outer_x_tips_are_different = 1;
            }

            $height_thickness_exceeds_1 = 0;
            if (($outer_arc_y_max - $inner_arc_y_max) > 1) {
                $height_thickness_exceeds_1 = 1;
            }

            $inner_height_exceeds_1 = 0;
            if ($inner_arc_y_max > 1) {
                $inner_height_exceeds_1 = 1;
            }

            $shape_is_segment = 0;
            if ($obj_shape == 'arcsegment') {
                $shape_is_segment = 1;
            }

            if ($outer_y_tips_match
                && $outer_angle_sweep_exceeds_180
                && $outer_x_tips_are_different
                && $height_thickness_exceeds_1
                && ($inner_height_exceeds_1    || $shape_is_segment)
            ) {

                $min_end_y_keystr = (int)(
                    $min_seg_line_y_end + 0.5
                );

                $excl_x_points[$min_end_y_keystr][] = (
                    $min_seg_line_x_end + $max_seg_line_x_end
                ) / 2;

                //
                // Also exclude the possible position just beyond the
                // tips.  That way any stray extra tip pixels will
                // not cause an incorrect line to be drawn.
                //
                // $mid_angle is the angle half-way between the start
                // and end angles.  The end angle is ALWAYS set to be
                // larger than the start angle.
                // The $mid_angle is always in the area where the
                // shape is empty: if the shape is empty at the top,
                // then the $mid_angle will always be between 0 and
                // 180 degrees.  If the shape is empty at the bottom
                // then the $mid_angle is between 180 and 360.
                //

                $mid_angle = $this->_angleIn360(
                    ($phi_end_angle + $phi_start_angle) / 2
                );

                $remove_above = 1;
                if ($mid_angle < 180) {
                    $remove_above = 0;
                }

                if ($remove_above) {
                    // Put an exclusion just ABOVE the outer tip row

                    $above_min_y_keystr = (int)(
                        ($min_seg_line_y_end + 1) + 0.5
                    );

                    $excl_x_points[$above_min_y_keystr][]
                        = (
                            ($min_seg_line_x_end + $max_seg_line_x_end)
                            / 2
                        );
                } else {
                    // Put an exclusion just BELOW the outer tip row

                    $below_min_y_keystr = (int)(
                        ($min_seg_line_y_end - 1) + 0.5
                    );

                    $excl_x_points[$below_min_y_keystr][]
                        = (
                            ($min_seg_line_x_end + $max_seg_line_x_end)
                            / 2
                        );
                }
            }
        }

        $outer_degree_error_margin_y = rad2deg(
            atan(1 / $outer_radius_y)
        );

        //
        // tchords are drawn as if they are initially full ellipses.
        // To reflect this, the start and end angles are changed to
        // be those of a full ellipse.
        // $orig_phi_start_angle and $orig_phi_end_angle store the
        // original values of the start and end angles.
        //

        $orig_phi_start_angle = $phi_start_angle;
        $orig_phi_end_angle = $phi_end_angle;

        if ($obj_shape == 'arctruechord') {
            //
            // Tchords are drawn as if they are initially full ellipses,
            // so change the start and end angles
            // to be those of an ellipse.
            // They will get changed back later on
            //

            $phi_start_angle = 0;
            $phi_end_angle = 360;
        }

        for (
            $outer_grid_y = $outer_grid_y_start;
            $outer_grid_y <= $outer_grid_y_end;
            $outer_grid_y++
        ) {
            $outer_y = (2 * $outer_grid_y - 1 - $true_height) / 2;

            $outer_y_keystr = $outer_y;

            if (!$y_grid_odd) {
                $outer_y_keystr += 0.5;
            }
            if (abs($outer_y_keystr) < 0.4) {
                //
                // Round the number down to zero.
                //
                // I needed this because for some ranges eg an
                // 18x18 grid, PHP would show the $filled_ykey for
                // 0 as "1.7763568394003E-15".  This would probably
                // be harmless since fractions are truncated when
                // using imageline.  However I might want to use
                // $filled_ykey for something later, and I do not
                // want any anomalies in the keys.
                //

                $outer_y_keystr = 0;
            }
            $outer_y_keystr = (int)$outer_y_keystr;

            list(
                $x_1, $x_2,
                $phi_1, $phi_2
            ) = $this->_getEllipseXsGivenY(
                $outer_y, $arc_alpha,
                $outer_radius_x, $outer_radius_y,
                $x_grid_odd, $y_grid_odd
            );

            if ($trim_tips) {
                if (abs($x_1) >= $outer_arc_x_max) {
                    $x_1 = $this->_getSign($x_1)
                    * ($outer_arc_x_max - 1);
                }

                if (abs($x_2) >= $outer_arc_x_max) {
                    $x_2 = $this->_getSign($x_2)
                    * ($outer_arc_x_max - 1);
                }
            }

            $last_ditch_add_result = 0;

            $auxilliary_count = 0;

            if (! is_null($min_line_slope)) {
                $proj_min_x_for_y = $this->_getProjectedTChordXs(
                    $outer_y, $min_line_slope,
                    $min_seg_line_x_end, $min_seg_line_y_end,
                    $x_grid_odd, $y_grid_odd
                );

                $proj_min_x_angle = $this->_getAngle(
                    0, 0, $proj_min_x_for_y[0], $outer_y
                );

                while ($proj_min_x_angle > $phi_end_angle) {
                    $proj_min_x_angle -= 360;
                }
                while ($proj_min_x_angle < $phi_start_angle) {
                    $proj_min_x_angle += 360;
                }
            } else {
                $proj_min_x_for_y = null;
                $proj_min_x_angle = null;
            }

            if (! is_null($max_line_slope)) {
                $proj_max_x_for_y = $this->_getProjectedTChordXs(
                    $outer_y, $max_line_slope,
                    $max_seg_line_x_end, $max_seg_line_y_end,
                    $x_grid_odd, $y_grid_odd
                );

                $proj_max_x_angle = $this->_getAngle(
                    0, 0,
                    $proj_max_x_for_y[0], $outer_y
                );

                while ($proj_max_x_angle > $phi_end_angle) {
                    $proj_max_x_angle -= 360;
                }
                while ($proj_max_x_angle < $phi_start_angle) {
                    $proj_max_x_angle += 360;
                }
            } else {
                $proj_max_x_for_y = null;
                $proj_max_x_angle = null;
            }

            if (! is_null($phi_1)) {
                while ($phi_1 > $phi_end_angle) {
                    $phi_1 -= 360;
                }
                while ($phi_1 < $phi_start_angle) {
                    $phi_1 += 360;
                }

                if ($phi_1 <= $phi_end_angle) {

                    $filled_points_all[$outer_y_keystr][] = $x_1;
                } elseif ((! is_null($proj_min_x_for_y[0]))
                    && (abs($proj_min_x_for_y[0] - $x_1) <= 2)
                    && ($proj_min_x_for_y[0] < -1)
                    && (
                        abs(
                            $this->_angleIn360($proj_min_x_angle)
                            - $phi_start_angle
                        )
                        <= $outer_degree_error_margin_y)
                ) {
                    //
                    // $x_1 itself was outside the range,
                    // however the projected min segment line
                    // was close enough so we will use the
                    // latter's value.
                    //

                    $x_1 = $proj_min_x_for_y[0];

                    $auxilliary_count++;

                    $filled_points_all[$outer_y_keystr][] = $x_1;
                }
            }

            if (! is_null($phi_2)) {
                while ($phi_2 > $phi_end_angle) {
                    $phi_2 -= 360;
                }
                while ($phi_2 < $phi_start_angle) {
                    $phi_2 += 360;
                }

                if ($phi_2 <= $phi_end_angle) {

                    $filled_points_all[$outer_y_keystr][] = $x_2;
                } elseif ((! is_null($proj_min_x_for_y[0]))
                    && (abs($proj_min_x_for_y[0] - $x_2) <= 2)
                    && ($proj_min_x_for_y[0] > 1)
                    && (
                        abs(
                            $this->_angleIn360($proj_min_x_angle)
                            - $phi_start_angle
                        ) <= $outer_degree_error_margin_y)
                ) {
                    //
                    // $x_2 itself was outside the range, however
                    // the projected min segment line was close
                    // enough so we will use the latter's value.
                    //

                    $x_2 = $proj_min_x_for_y[0];

                    $auxilliary_count++;

                    $filled_points_all[$outer_y_keystr][] = $x_2;
                }
            }

            if (($auxilliary_count == 2)
                && ((($phi_start_angle + 360) != $phi_end_angle)
                && (($phi_end_angle - $phi_start_angle) >= 180))
            ) {
                //
                // Both $x_1 and $x_2 were added using the projected
                // segment lines, and the sweep is large meaning that
                // they are the top or bottom tips of the shape.  The
                // projected additions should be removed.
                //
                // We will delete the $filled_points_all entry for
                // $outer_y_keystr
                //

                unset($filled_points_all[$outer_y_keystr]);
            }
        }

        if (($obj_shape != 'arcchord')
            && ($obj_shape != 'arcsegment')
        ) {

            //
            // The grid mathematics is good, but sometimes the shapes
            // decide to do their own thing.
            // $min_valid_inner_y_keystr and
            // $max_valid_inner_y_keystr show me
            // which ranges of the $inner_y were actually valid for
            // the inner arc.  The "$min_" one shows the lowest valid
            // value of $inner_y; the "$max_" shows the highest valid
            // value of $inner_y
            //

            $min_valid_inner_y_keystr = null;
            $max_valid_inner_y_keystr = null;

            for (
                $inner_grid_y = $inner_grid_y_start;
                $inner_grid_y <= $inner_grid_y_end;
                $inner_grid_y++
            ) {

                $inner_y = (2 * $inner_grid_y - 1 - $true_height) / 2;

                $inner_y_keystr = $inner_y;

                if (!$y_grid_odd) {
                    $inner_y_keystr += 0.5;
                }
                if (abs($inner_y_keystr) < 0.4) {
                    //
                    // Round the number down to zero.
                    //
                    // I needed this because for some ranges eg an
                    // 18x18 grid, PHP would show the $filled_ykey for
                    // 0 as "1.7763568394003E-15".  This would probably
                    // be harmless since fractions are truncated when
                    // using imageline.  However I might want to use
                    // $filled_ykey for something later, and I do not
                    // want any anomalies in the keys.
                    //

                    $inner_y_keystr = 0;
                }
                $inner_y_keystr = (int)$inner_y_keystr;

                list(
                    $x_1, $x_2,
                    $phi_1, $phi_2
                ) = $this->_getEllipseXsGivenY(
                    $inner_y, $inner_alpha,
                    $inner_radius_x, $inner_radius_y,
                    $x_grid_odd, $y_grid_odd
                );

                if (is_null($x_1) || is_null($x_2)) {
                    //
                    // We are outside the boundaries of the inner arc.
                    // Just skip to the next $inner_y
                    //

                    if (array_key_exists(
                        $inner_y_keystr,
                        $t_line_points
                    )
                    ) {
                        foreach (
                            $t_line_points[$inner_y_keystr] as $t_x
                        ) {
                            $filled_points_all[$inner_y_keystr][]
                                = $t_x;
                        }
                    }

                    continue;
                }

                if (is_null($min_valid_inner_y_keystr)) {
                    //
                    // To get this far, the $inner_y value is
                    // definitely valid for the inner arc.
                    //

                    $min_valid_inner_y_keystr = $inner_y_keystr;
                }
                if (is_null($max_valid_inner_y_keystr)
                    || ($max_valid_inner_y_keystr < $inner_y_keystr)
                ) {
                    $max_valid_inner_y_keystr = $inner_y_keystr;
                }

                if ($trim_tips) {
                    if (abs($x_1) >= $inner_arc_x_max) {
                        $x_1 = $this->_getSign($x_1)
                        * ($inner_arc_x_max - 1);
                    }

                    if (abs($x_2) >= $inner_arc_x_max) {
                        $x_2 = $this->_getSign($x_2) *
                        ($inner_arc_x_max - 1);
                    }
                }

                while ($phi_1 > $phi_end_angle) {
                    $phi_1 -= 360;
                }
                while ($phi_1 < $phi_start_angle) {
                    $phi_1 += 360;
                }
                if ($phi_1 <= $phi_end_angle) {
                    $filled_points_all[$inner_y_keystr][] = $x_1;

                    if (($x_2 != $x_1)
                        && (abs($x_2 - $x_1) > 1)
                    ) {

                        $row_excl_x = $x_1 + 1;

                        $inner_excl_x_points[$inner_y_keystr][]
                            = $row_excl_x;
                    }
                }

                while ($phi_2 > $phi_end_angle) {
                    $phi_2 -= 360;
                }
                while ($phi_2 < $phi_start_angle) {
                    $phi_2 += 360;
                }
                if ($phi_2 <= $phi_end_angle) {

                    $filled_points_all[$inner_y_keystr][] = $x_2;

                    if (($x_2 != $x_1)
                        && (abs($x_2 - $x_1) > 1)
                    ) {

                        $row_excl_x = $x_2 - 1;

                        $inner_excl_x_points[$inner_y_keystr][]
                            = $row_excl_x;
                    }
                }
            }
        }

        if ((($obj_shape == 'arcsegment')
            || ($obj_shape == 'arcchord'))
            || (($inner_radius_x == 0)
            && ($inner_radius_y == 0))
        ) {
            //
            // Segments and chords do not use inner exclusions.
            // Fully solid shapes also do not use exclusions
            //
        } else {
            //
            // The inner ellipse is hollow.
            //
            // For non-chords, forcefully insert inner exclusions
            // for all the points that lie on the line axis
            // between $dydx_inner_y_trans max and
            // $dydx_inner_y_trans min.
            //
            // I deliberately do not add the first two and last
            // two entries because they are so close to the tips
            // that including them could cause _errors to appear
            //
            // Note that the trans line ALWAYS uses integer values
            // as if $x_grid_odd and $y_grid_odd are both 1.
            // It will never use even grid referencing because the
            // trans rows are always integer increments of y
            //

            $dydx_inner_x_trans_reflected = -$dydx_inner_x_trans;
            $dydx_inner_y_trans_reflected = -$dydx_inner_y_trans;

            $trans_axis_line_points = $this->_getLinePointsAllYs(
                (int)$dydx_inner_x_trans,
                (int)$dydx_inner_y_trans,
                (int)$dydx_inner_x_trans_reflected,
                (int)$dydx_inner_y_trans_reflected,
                1, 1
            );
            $line_slope = array_pop($trans_axis_line_points);
            $num_points = array_pop($trans_axis_line_points);

            $y_grid_offset = ($y_grid_odd) ? 0 : 0.5;

            $num_points_times_2 = $num_points * 2;
            $num_points_times_2_minus_2 = $num_points * 2 - 2;

            for ($k = 0; $k <= $num_points_times_2_minus_2; $k += 2) {

                $trans_line_y_keystr
                    = (int)($trans_axis_line_points[$k + 1]);

                if (is_null($trans_axis_line_points[$k + 1])) {
                    //
                    // This y-value is a null so it will not be added
                    // Skip to the next y-value
                    //

                    continue;
                }

                if (($trans_line_y_keystr < ($min_valid_inner_y_keystr + 1))
                    || ($trans_line_y_keystr > ($max_valid_inner_y_keystr - 1))
                ) {
                    //
                    // This exclusion will not be added.  It is too
                    // close to the top or bottom of the inner arc,
                    // so adding it could cause an error in the shape.
                    // Skip to the next y-value
                    //

                    continue;
                }

                //
                // It is alright to add the exclusion.
                // Note that it goes into the inner_excl_x_points
                // array, since the trans line always runs through
                // the hollow area of the inner arc.
                //

                $inner_excl_x_points[$trans_line_y_keystr][]
                    = $trans_axis_line_points[$k];
            }
        }

        $active_ykeys = array_keys($filled_points_all);

        $lowest_ykey = null;
        $highest_ykey = null;

        $num_ykeys = sizeof($active_ykeys);
        if ($num_ykeys) {
            sort($active_ykeys);

            $lowest_ykey = $active_ykeys[0];
            $highest_ykey = $active_ykeys[$num_ykeys - 1];
        }

        //
        // Now tidy up the contents of $excl_x_points, and find
        // out if any entries are missing
        //

        $finalised_excl_x_points = $this->_finaliseExcludedXPoints(
            $excl_x_points, $lowest_ykey, $highest_ykey,
            $outer_arc_x_max, $obj_shape
        );

        $inner_excl_x_points = $this->_finaliseInnerExcludedXPoints(
            $inner_excl_x_points, $lowest_ykey, $highest_ykey,
            $outer_arc_x_max, $obj_shape
        );

        //
        // Now draw the lines and pixels for each value of y
        //

        $filled_points_all_keys = array_keys($filled_points_all);
        sort($filled_points_all_keys);

        //
        // $prev_adj_pixel_drawn is true if the pixel just before
        // the current one was drawn successfully.  It is used by
        // $smooth_check to see if the current pixel is isolated
        // or if it is part of a continuous line.
        //

        $prev_adj_pixel_drawn = 0;

        foreach ($filled_points_all_keys as $pixel_y) {
            $xy_points = $filled_points_all[$pixel_y];

            if (! sizeof($xy_points)) {
                //
                // There is nothing to process for this row
                //

                $draw_line_valid        = 0;
                $draw_lhs_pixel_valid    = 0;

                continue;
            }

            $sorted_xy_points = array_unique($xy_points);
            sort($sorted_xy_points);

            if (is_null($sorted_xy_points[0])) {
                //
                // There is a null entry.  Remove it
                //

                array_shift($sorted_xy_points);
            }

            $num_sorted_points = sizeof($sorted_xy_points);

            if (! sizeof($sorted_xy_points)) {
                //
                // There is nothing to process for this row
                // This is the 2nd check, AFTER we have removed
                // the nulls
                //

                $draw_line_valid        = 0;
                $draw_lhs_pixel_valid    = 0;

                continue;
            }

            //
            // Set $prev_adj_pixel_drawn to false at the start of
            // each row
            //

            $prev_adj_pixel_drawn = 0;

            $last_plotted_x = null;

            $current_x = array_shift($sorted_xy_points);

            $num_rem_points = sizeof($sorted_xy_points);

            for ($i = 0; $i < $num_rem_points; $i++) {

                //
                // Check $current_x against the next point
                //

                if (is_null($sorted_xy_points[$i])) {

                    //
                    // There is a null entry.  Skip to the next one
                    //

                    continue;
                }

                //
                // My current method of drawing filled shapes is to
                // get all the boundary points and then connect the
                // horizontal lines between them as appropriate.
                // For tchords, I also do a final check just before
                // drawing each horizontal line, hence I have to
                // feed in tchord parameters as input to the
                // _confirmLineOrPixel() function which decides
                // if a line should be drawn or not.  For shapes
                // that are not tchords, I have to create some
                // dummy tchord parameters for _confirmLineOrPixel()
                // so it can process them cleanly.
                //

                if ($obj_shape != 'arctruechord') {
                    //
                    // This shape is not a tchord,
                    // so create 2 dummy values for the
                    // $t_line_points_for_row_y.
                    // They will use the "400" series
                    //

                    $t_line_points_for_row_y[]
                        = $outer_arc_x_max + 400;
                    $t_line_points_for_row_y[]
                        = $outer_arc_x_max + 400;
                } else {

                    if (array_key_exists($pixel_y, $t_line_points)) {
                        //
                        // The tchord has x-values for this $pixel_y.
                        // Feed them into $t_line_points_for_row_y
                        //

                        $t_line_points_for_row_y
                            = $t_line_points[$pixel_y];
                    } else {
                        $t_line_points_for_row_y
                            = $this->_getProjectedTChordXs(
                                $pixel_y, $tchord_line_slope,
                                $smallest_t_x, $smallest_t_y,
                                $x_grid_odd, $y_grid_odd
                            );
                    }
                }

                //
                // Assume that a line and pixel are valid for
                // the $current_x and $next_x values.
                //

                $draw_line_valid        = 1;
                $draw_lhs_pixel_valid    = 1;

                list(
                    $draw_line_valid,
                    $draw_lhs_pixel_valid,
                    $prev_adj_pixel_drawn, $current_x, $next_x
                ) = $this->_confirmLineOrPixel(
                    $pixel_y,
                    $current_x, $sorted_xy_points[$i], $trim_tips,
                    $smooth_check, $prev_adj_pixel_drawn,
                    $finalised_excl_x_points[$pixel_y],
                    $inner_excl_x_points[$pixel_y],
                    $t_line_points_for_row_y,
                    $tchord_excl_side,
                    0, $smallest_t_y, $largest_t_y
                );
                $this->_drawLineOrPixel(
                    $current_x, $next_x, $pixel_y,
                    $draw_line_valid, $draw_lhs_pixel_valid,
                    $centre_x, $centre_y, $image, $arc_color
                );

                $current_x = $next_x;
            }

            //
            // Processing the last x-value in the row
            //

            if ($obj_shape != 'arctruechord') {
                //
                // This shape is not a tchord,
                // so create dummy values for the
                // $t_line_points_for_row_y.
                // They will use the "400" series
                //

                $t_line_points_for_row_y[] = $outer_arc_x_max + 401;
                $t_line_points_for_row_y[] = $outer_arc_x_max + 401;
            } else {

                if (array_key_exists($pixel_y, $t_line_points)) {
                    $t_line_points_for_row_y
                        = $t_line_points[$pixel_y];
                } else {
                    $t_line_points_for_row_y
                        = $this->_getProjectedTChordXs(
                            $pixel_y, $tchord_line_slope,
                            $smallest_t_x, $smallest_t_y,
                            $x_grid_odd, $y_grid_odd
                        );
                }
            }

            //
            // A line can never be valid for the last pixel, however
            // a pixel might be valid.  Set the flags according to
            // this rule
            //

            $draw_line_valid        = 0;
            $draw_lhs_pixel_valid    = 1;

            list(
                $draw_line_valid,
                $draw_lhs_pixel_valid,
                $prev_adj_pixel_drawn, $current_x, $next_x
            ) = $this->_confirmLineOrPixel(
                $pixel_y,
                $current_x, $current_x, $trim_tips,
                $smooth_check, $prev_adj_pixel_drawn,
                $finalised_excl_x_points[$pixel_y],
                $inner_excl_x_points[$pixel_y],
                $t_line_points_for_row_y,
                $tchord_excl_side,
                0, $smallest_t_y, $largest_t_y
            );

            if ($obj_shape != 'arctruechord') {
                //
                // For non-tchords, reinforce  the fact that we
                // are only processing a pixel.
                //

                $next_x = $current_x;
            }

            $this->_drawLineOrPixel(
                $current_x, $next_x, $pixel_y,
                $draw_line_valid, $draw_lhs_pixel_valid,
                $centre_x, $centre_y, $image, $arc_color
            );
        }

        //
        // For tchords, graft in the tchord line between points P and Q.
        // Only do this if all the conditions below are true:
        //   (1) the shape is a tchord
        //   (2) the tchord line should be drawn
        //   (3) the tchord is tangential (a non-tangential tchord
        //       would have been processed easily by _confirmLineOrPixel)
        //   (4) the sweep of the angle from P to Q is greater
        //       than 180 degrees
        //   (5) the sweep of the angle from the outer tips of the
        //       tchord is greater than 180 degrees
        //

        if (($obj_shape == 'arctruechord')
            && ($draw_tchord_line)
            && ($tchord_tangential)
            && (($inner_phi_end_angle - $inner_phi_start_angle) > 180)
            && (($orig_phi_end_angle - $orig_phi_start_angle) > 180)
        ) {
            //
            // This section draws the points between P and Q
            //

            $dist_p_q = sqrt(
                (($p_x - $q_x) * ($p_x - $q_x))
                + (($p_y - $q_y) * ($p_y - $q_y))
            );

            foreach ($t_line_points as $t_pixel_y => $t_xy_points) {
                if (($dist_p_q > 2)
                    && (($t_pixel_y < $p_y) && ($t_pixel_y < $q_y))
                    || (($t_pixel_y > $p_y) && ($t_pixel_y > $q_y))
                ) {
                    //
                    // $t_pixel_y is outside the area between P and Q.
                    // Skip to the next $t_pixel_y
                    //
                    // NOTE: At the moment I have to include P and Q
                    // as being allowed in case the tchord is horizontal.
                    //
                    // FUTURE_UPDATE:
                    // A future version of this program will probably
                    // solve this issue. Ideally points P and Q should
                    // not be added in again because they could
                    // potentially double up.
                    //

                    continue;
                }

                $t_xy_points = $t_line_points[$t_pixel_y];

                if (! sizeof($t_xy_points)) {
                    continue;
                }

                $min_t_x = min($t_xy_points);
                $max_t_x = max($t_xy_points);

                $draw_line_valid = 1;
                $draw_lhs_pixel_valid = 1;

                if ($min_t_x == $max_t_x) {
                    $draw_line_valid = 0;
                }

                $this->_drawLineOrPixel(
                    $min_t_x, ($max_t_x + 1), $t_pixel_y,
                    $draw_line_valid, $draw_lhs_pixel_valid,
                    $centre_x, $centre_y, $image, $arc_color
                );
            }
        }
    }


    /**
     * Draws a horizontal line or pixel from a starting x-value to a
     * destination x-value for a given y-value
     *
     * The companion function to _drawLineOrPixel is
     * _confirmLineOrPixel. _confirmLineOrPixel makes the decisions
     * which _drawLineOrPixel then executes.
     *
     * NOTE: _drawLineOrPixel is only used for filled shapes. Another
     * function is used if only the outline of the shape is desired.
     *
     * @param float    $current_x            the x-value of the
     *                      starting point
     * @param float    $next_x               the x-value of the
     *                      destination point. Note that
     *                      ($next_x,$pixel_y) is NOT populated in
     *                      this iteration. See the description of
     *                      this function for more info.
     * @param int      $pixel_y              the y-value of the
     *                      horizontal line.
     * @param bool     $draw_line_valid      shows if a line is a
     *                      valid to draw
     * @param bool     $draw_lhs_pixel_valid shows if the starting
     *                      point is valid to draw
     * @param int      $centre_x             x-coordinate of the
     *                      centre of the shape
     * @param int      $centre_y             y-coordinate of the
     *                      centre of the shape
     * @param resource &$image               a reference to the image
     *                      object
     * @param resource &$arc_color           a reference to the
     *                      shape's colour
     *
     *
     * The line is ALWAYS drawn from left to right. The line also goes
     * from the starting x-value up to (but not including) the
     * destination x-value. During the next iteration of the calling
     * function, the current destination x-value will become the
     * starting point. This prevents double-filling the start and end
     * points.
     *
     *      LHS = Left Hand Side
     *      RHS = Right Hand Side
     *
     * @return  void
     *
     * @access private
     *
     */
    private function _drawLineOrPixel(
        $current_x, $next_x, $pixel_y,
        $draw_line_valid, $draw_lhs_pixel_valid, $centre_x, $centre_y,
        &$image, &$arc_color
    ) {
        if ($draw_line_valid) {
            if ($next_x == ($current_x + 1)) {
                //
                // A line would be valid, but the two points are just
                // one pixel apart.
                // Draw a pixel for ($current_x, $pixel_y)
                //

                imagesetpixel(
                    $image,
                    ($centre_x + $current_x), ($centre_y - $pixel_y),
                    $arc_color
                );
            } else {
                //
                // A line is definitely valid and the points are
                // far enough apart to justify a line.
                // Draw a line from ($current_x, $pixel_y) to
                // (($next_x - 1), $pixel_y)
                //

                imageline(
                    $image,
                    ($centre_x + $current_x),    ($centre_y - $pixel_y),
                    ($centre_x + $next_x - 1),    ($centre_y - $pixel_y),
                    $arc_color
                );
            }
        } elseif ($draw_lhs_pixel_valid) {
            //
            // We can not draw a line, however the lhs pixel was
            // still considered valid for this shape.
            // Draw the ($current_x, $pixel_y) pixel.
            //

            imagesetpixel(
                $image,
                ($centre_x + $current_x), ($centre_y - $pixel_y),
                $arc_color
            );
        } else {
            //
            // We can not draw a line, and the lhs pixel was NOT
            // considered valid for this shape.
            // Do not draw anything.
            //
        }
    }


    /**
     * draws the outlined shape from the specified parameters
     *
     * The sister function to _drawOutlinedShape() is
     * _drawFilledShape(). They take exactly the same arguments but
     * process the data in different ways as required.
     *
     * @param resource &$image                         a reference to
     *                      the image object
     * @param resource &$arc_color                     a reference to
     *                      the shape's colour from imagecolorallocate
     * @param int      $centre_x                       x-coordinate of
     *                      the centre of the shape
     * @param int      $centre_y                       y-coordinate of
     *                      the centre of the shape
     * @param string   $obj_shape                      the type of
     *                      shape
     * @param float    $arc_alpha                      the axis of
     *                      rotation
     * @param float    $inner_alpha                    the axis
     *                      rotation angle of the inner arc. It may be
     *                      different from $alpha.
     * @param float    $phi_start_angle                the outer
     *                      starting angle of the rotated shape
     * @param float    $phi_end_angle                  the outer
     *                      starting angle of the rotated shape
     * @param float    $inner_phi_start_angle          the angle that
     *                      point P makes with the origin
     * @param float    $inner_phi_end_angle            the angle that
     *                      point Q makes with the origin
     * @param float    $outer_radius_x                 the radius of
     *                      the unrotated outer width
     * @param float    $outer_radius_y                 the radius of
     *                      the unrotated outer height
     * @param float    $inner_radius_x                 the radius of
     *                      the unrotated inner arc width
     * @param float    $inner_radius_y                 the radius of
     *                      the unrotated inner arc's height
     * @param float    $dydx_outer_x_trans             The maximum
     *                      x-value of the rotated shape when centred
     *                      at the origin. It is calculated using the
     *                      formula of a rotated ellipse. It helps
     *                      determine the final of $x_grid_odd.
     * @param float    $dydx_outer_y_trans             The maximum
     *                      y-value of the rotated shape when centred
     *                      at the origin. It is calculated using the
     *                      formula of a rotated ellipse. It helps
     *                      determine the final of $y_grid_odd.
     * @param float    $dydx_inner_x_trans             The maximum
     *                      x-value of the rotated inner arc when
     *                      centred at the origin. It helps determine
     *                      the x-grid boundaries for the inner arc.
     * @param float    $dydx_inner_y_trans             The maximum
     *                      y-value of the rotated inner arc when
     *                      centred at the origin. It helps determine
     *                      the y-grid boundaries for the inner arc.
     * @param float    $outer_arc_x_max                The maximum
     *                      x-value of the rotated shape. It is half
     *                      of the total width-space of the rotated
     *                      ellipse.
     * @param float    $outer_arc_y_max                The maximum
     *                      y-value of the rotated shape. It is half
     *                      of the total height-space of the rotated
     *                      ellipse.
     * @param float    $inner_arc_x_max                The maximum
     *                      x-value of the rotated inner arc. It is
     *                      half of the total width-space of the
     *                      rotated inner arc's ellipse.
     * @param float    $inner_arc_y_max                The maximum
     *                      y-value of the rotated inner arc. It is
     *                      half of the total height-space of the
     *                      rotated inner arc's ellipse.
     * @param int      $true_height                    the pixel
     *                      height of the shape's full rotated ellipse
     *                      in the grid system. For filled shapes it
     *                      helps limit the range of plottable
     *                      y-values for the inner and outer arcs.
     * @param int      $outer_grid_y_start             the lowest
     *                      pixel of the shape's full ellipse in the
     *                      grid system. It is typically "1", unless
     *                      $trim_tips is enabled, in which case it
     *                      becomes 2 (thereby truncating the bottom
     *                      pixel of the shape)
     * @param int      $outer_grid_y_end               the highest
     *                      pixel of the shape's full ellipse in the
     *                      grid system. It typically equals the value
     *                      in $true_height unless $trim_tips is
     *                      enabled, in which case it becomes one less
     *                      than $true_height (thereby truncating the
     *                      top pixel of the shape)
     * @param int      $inner_grid_y_start             the lowest
     *                      pixel of the inner arc's ellipse in the
     *                      grid system.
     * @param int      $inner_grid_y_end               the highest
     *                      pixel of the inner arc's ellipse in the
     *                      grid system.
     * @param bool     $outer_radii_swapped            true if the
     *                      outer width and height were swapped.
     * @param bool     $inner_radii_swapped            true if the
     *                      inner width and height were swapped.
     * @param bool     $trim_tips                      true if the
     *                      inner and outer edges should be truncated
     * @param bool     $smooth_check                   true if lone
     *                      pixels should be removed
     * @param bool     $x_grid_odd                     true if the
     *                      width-space uses an odd number of pixels
     * @param bool     $y_grid_odd                     true if the
     *                      height-space uses an odd number of pixels
     * @param bool     $definite_t_inner_arc_intersect true if the
     *                      tchord intersects the inner arc
     * @param bool     $draw_tchord_line               true if the
     *                      tchord line should be explicitly drawn
     * @param bool     $tchord_tangential              true if the
     *                      tchord is considered tangential to the
     *                      inner arc
     * @param array    $t_line_points                  an array
     *                      containing all the x-y points that make up
     *                      up the tchord
     * @param float    $tchord_line_slope              the slope of
     *                      the tchord line
     * @param array    $t_inner_arc_intersect          an array
     *                      containing the points where the tchord
     *                      approximately intersected the inner arc
     * @param float    $smallest_t_x                   the x-value
     *                      corresponding to the lowest vertical pixel
     *                      of the tchord
     * @param float    $smallest_t_y                   the y-value of
     *                      the lowest vertical pixel of the tchord
     * @param float    $largest_t_x                    the x-value
     *                      corresponding to the highest vertical
     *                      pixel of the tchord
     * @param float    $largest_t_y                    the y-value of
     *                      the highest vertical pixel of the tchord
     * @param float    $p_x                            the x-value of
     *                      the P intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      starting point of the tchord
     * @param float    $p_y                            the y-value of
     *                      the P intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      starting point of the tchord
     * @param float    $q_x                            the x-value of
     *                      the Q intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      ending point of the tchord
     * @param float    $q_y                            the y-value of
     *                      the Q intersection point of the tchord and
     *                      the inner arc that is closest to the
     *                      ending point of the tchord
     * @param float    $p_x_neighbour                  the x-value of
     *                      the pixel that is just after point P
     * @param float    $p_y_neighbour                  the y-value of
     *                      the pixel that is just after point P
     * @param float    $q_x_neighbour                  the x-value of
     *                      the pixel that is just before point Q
     * @param float    $q_y_neighbour                  the y-value of
     *                      the pixel that is just before point Q
     * @param string   $tchord_excl_side               the area of the
     *                      shape that is excluded because of the
     *                      presence of the tchord line
     *
     * @return void
     *
     * @access private
     *
     */
    private function _drawOutlinedShape(
        &$image,
        &$arc_color,
        $centre_x, $centre_y,
        $obj_shape,
        $arc_alpha,
        $inner_alpha,
        $phi_start_angle, $phi_end_angle,
        $inner_phi_start_angle, $inner_phi_end_angle,
        $outer_radius_x, $outer_radius_y,
        $inner_radius_x, $inner_radius_y,
        $dydx_outer_x_trans, $dydx_outer_y_trans,
        $dydx_inner_x_trans, $dydx_inner_y_trans,
        $outer_arc_x_max, $outer_arc_y_max,
        $inner_arc_x_max, $inner_arc_y_max,
        $true_height,
        $outer_grid_y_start, $outer_grid_y_end,
        $inner_grid_y_start, $inner_grid_y_end,
        $outer_radii_swapped,
        $inner_radii_swapped,
        $trim_tips,
        $smooth_check,
        $x_grid_odd, $y_grid_odd,
        $definite_t_inner_arc_intersect,
        $draw_tchord_line,
        $tchord_tangential,
        $t_line_points,
        $tchord_line_slope,
        $t_inner_arc_intersect,
        $smallest_t_x,
        $smallest_t_y,
        $largest_t_x,
        $largest_t_y,
        $p_x,
        $p_y,
        $q_x,
        $q_y,
        $p_x_neighbour,
        $p_y_neighbour,
        $q_x_neighbour,
        $q_y_neighbour,
        $tchord_excl_side
    ) {

        //
        // The shape is not filled, so we will just draw the
        // inner and outer arcs
        //

        //
        // Drawing a NON-FILLED arc
        // Drawing a UN-FILLED arc
        // Drawing a NONFILLED arc
        // Drawing a UNFILLED arc
        //
        // The issue when drawing a non-filled arc is that you can
        // not just get all the points, connect them using imageline
        // and be done with it.  PHP's imageline will cause each
        // intersection of consecutive lines (ie all the points
        // of your arc) to have double pixel layers.  It is only
        // noticeable when using alpha colours.
        //
        // To solve this, do the following:
        //    (1) use _getEllipseXYGivenPhi() to find the points of the
        //      arc/ellipse around a 360 degree range.
        //    (2) Store the results in an array.
        //    (3) Use _connectDots to draw the contents of the array.
        //      It avoids overlapping of pixels.
        //    (4) If the arc has a thickness, you need two arrays: one
        //      each for the inner and outer arcs.
        //        Then after getting all the points you simply
        //      append the two arrays
        //

        $inner_arc_points_all = array();
        $outer_arc_points_all = array();

        $arc_width_thickness = $outer_radius_x - $inner_radius_x + 1;
        $arc_height_thickness = $outer_radius_y - $inner_radius_y + 1;

        $angle_increment = 1;

        if ((($arc_width_thickness == 1)
            && ($arc_height_thickness == 1))
        ) {
            //
            // This is a single arc
            //

            for (
                $arc_angle = $phi_start_angle;
                $arc_angle <= $phi_end_angle;
                $arc_angle+=$angle_increment
            ) {
                //
                // Get the outer points for this $arc_angle
                //

                $outer_arc_point = $this->_getEllipseXYGivenPhi(
                    $arc_angle, $arc_alpha,
                    $outer_radius_x, $outer_radius_y,
                    1, $x_grid_odd, $y_grid_odd
                );

                if ($trim_tips) {
                    if (abs($outer_arc_point[1]) < $outer_arc_y_max) {
                        if (abs($outer_arc_point[0]) > $outer_arc_x_max
                        ) {
                            $outer_arc_point[0]
                                = $this->_getSign($outer_arc_point[0])
                                * (abs($outer_arc_point[0]) - 1);
                        }
                    }
                }
                    array_push(
                        $outer_arc_points_all,
                        ($centre_x + $outer_arc_point[0]),
                        ($centre_y - $outer_arc_point[1])
                    );
            }

            if ((($phi_start_angle + 360) == $phi_end_angle)
                || ($obj_shape == 'circle')
                || ($obj_shape == 'ellipse')
                || ($obj_shape == 'arcsegment')
                || (($obj_shape == 'arctruechord')
                && ($draw_tchord_line))
                || ($obj_shape == 'arcchord')
            ) {
                //
                // This is a continuous unbroken shape.
                // Since the thickness is 1, just draw
                // the $outer_arc_points_all
                //

                // The order of the if test below is important:
                // if a segment has the start and end angles
                // differing by 360 degrees, it should be
                // handled by the first condition instead of dropping
                // into the second condition.
                //

                if ((($phi_start_angle + 360) == $phi_end_angle)
                    || ($obj_shape == 'circle')
                    || ($obj_shape == 'ellipse')
                    || ($obj_shape == 'arctruechord')
                    || ($obj_shape == 'arcchord')
                ) {
                    //
                    // Remove the last point since it is a duplicate.
                    //
                    // The 1st array_pop removes the last y-coordinate
                    // The 2nd array_pop removes the last x-coordinate
                    //

                    array_pop($outer_arc_points_all);
                    array_pop($outer_arc_points_all);
                } elseif ($obj_shape == 'arcsegment') {
                    //
                    // Add the origin as a point
                    //

                    $outer_arc_points_all[] = $centre_x;
                    $outer_arc_points_all[] = $centre_y;
                }

                //
                // Connect the dots of the shape.  The ends will be
                // joined by default
                //

                $this->_connectDots(
                    $image,
                    $outer_arc_points_all,
                    $arc_color
                );
            } else {
                //
                // Just draw a simple arc.  Do not join the ends
                // since it is not a solid shape
                //

                $this->_connectDots(
                    $image,
                    $outer_arc_points_all,
                    $arc_color, 0
                );
            }
        } else {

            //
            // This shape has distinct inner and outer arcs.
            //

            //
            // when drawing the inner arc, tchords need to use
            // the $inner_phi_start_angle and inner_phi_end_angle.
            // Other shapes only need the regular
            // $phi_start_angle and $phi_end_angle
            //

            if ($obj_shape == 'arctruechord') {
                $inner_starting_angle = $inner_phi_start_angle;
                $inner_ending_angle = $inner_phi_end_angle;
            } else {
                $inner_starting_angle = $phi_start_angle;
                $inner_ending_angle = $phi_end_angle;
            }

            for (
                $arc_angle = $inner_starting_angle;
                $arc_angle <= $inner_ending_angle;
                $arc_angle += $angle_increment
            ) {
                //
                // Get the inner points for this $arc_angle
                //

                $inner_arc_point = $this->_getEllipseXYGivenPhi(
                    $arc_angle, $inner_alpha,
                    $inner_radius_x, $inner_radius_y,
                    1, $x_grid_odd, $y_grid_odd
                );

                if ($trim_tips) {

                    if (abs(
                        $inner_arc_point[0]
                    ) >= $inner_arc_x_max
                    ) {
                        $inner_arc_point[0]
                            = $this->_getSign($inner_arc_point[0])
                            * (abs($inner_arc_point[0]) - 1);
                    }

                    if (abs(
                        $inner_arc_point[1]
                    ) >= $inner_arc_y_max
                    ) {
                        $inner_arc_point[1]
                            = $this->_getSign($inner_arc_point[1])
                            * (abs($inner_arc_point[1]) - 1);
                    }
                }

                //
                // Append these points to their arcs
                //
                // For $inner_arc_points_all, we will deliberately
                // prepend each new point to the array so later on it
                // will be easier to merge with $outer_arc_points_all
                // for cases where the arc is NOT a complete ellipse.
                // That way we can draw a solid polygon
                //

                array_unshift(
                    $inner_arc_points_all,
                    ($centre_x + $inner_arc_point[0]),
                    ($centre_y - $inner_arc_point[1])
                );
            }

            for (
                $arc_angle = $phi_start_angle;
                $arc_angle <= $phi_end_angle;
                $arc_angle += $angle_increment
            ) {
                //
                // Get the outer points for this $arc_angle
                //

                $outer_arc_point = $this->_getEllipseXYGivenPhi(
                    $arc_angle, $arc_alpha,
                    $outer_radius_x, $outer_radius_y,
                    1, $x_grid_odd, $y_grid_odd
                );

                if ($trim_tips) {

                    if (abs($outer_arc_point[0]) >= $outer_arc_x_max) {
                        $outer_arc_point[0]
                            = $this->_getSign($outer_arc_point[0])
                            * (abs($outer_arc_point[0]) - 1);
                    }

                    if (abs($outer_arc_point[1]) >= $outer_arc_y_max) {
                        $outer_arc_point[1]
                            = $this->_getSign($outer_arc_point[1])
                            * (abs($outer_arc_point[1]) - 1);
                    }
                }

                //
                // Append these points to the outer arc
                //

                array_push(
                    $outer_arc_points_all,
                    ($centre_x + $outer_arc_point[0]),
                    ($centre_y - $outer_arc_point[1])
                );
            }

            if ((($phi_start_angle + 360) == $phi_end_angle)
                || ($obj_shape == 'circle')
                || ($obj_shape == 'ellipse')
            ) {

                //
                // This is a complete ellipse.
                // Draw $inner_arc_points_all and $inner_arc_points_all
                // as separate shapes
                //
                // Note that we also need to discard the last x-y
                // coordinate pair that was read in because it is a
                // duplicate of the first x-y coordinate pair
                //
                // In both $inner_arc_points_all and
                // $outer_arc_points_all below:
                //     The 1st array_pop removes the last y-coordinate
                //     The 2nd array_pop removes the last x-coordinate
                //

                array_pop($inner_arc_points_all);
                array_pop($inner_arc_points_all);

                array_pop($outer_arc_points_all);
                array_pop($outer_arc_points_all);

                $num_points = sizeof($outer_arc_points_all) / 2;

                if ($obj_shape != 'arcchord') {
                    //
                    // A chord does not use the inner arc.
                    // Other shapes do
                    //

                    $this->_connectDots(
                        $image,
                        $inner_arc_points_all,
                        $arc_color
                    );
                }

                $this->_connectDots(
                    $image,
                    $outer_arc_points_all,
                    $arc_color
                );
            } else {

                //
                // The arcs for $inner_arc_points_all and
                // $outer_arc_points_all should be joined
                //

                if ($obj_shape == 'arcchord') {
                    //
                    // For a chord, the $inner_arc_points_all is not
                    // fully needed.
                    // We only need the first and last x-y coordinate
                    // pair from $inner_arc_points_all
                    //

                    $start_point_x = array_shift($inner_arc_points_all);
                    $start_point_y = array_shift($inner_arc_points_all);

                    $end_point_y = array_pop($inner_arc_points_all);
                    $end_point_x = array_pop($inner_arc_points_all);

                    $outer_arc_points_all[] = $start_point_x;
                    $outer_arc_points_all[] = $start_point_y;

                    $outer_arc_points_all[] = $end_point_x;
                    $outer_arc_points_all[] = $end_point_y;
                } elseif ($obj_shape == 'arctruechord') {

                    if ($definite_t_inner_arc_intersect == 0) {
                        //
                        // For this tchord, the $inner_arc_points_all
                        // is a complete ellipse and is separate from
                        // the outer arc.
                        // We need to get the remaining points from
                        // $inner_phi_end_angle back to
                        // $inner_phi_start_angle and attach them to
                        // $inner_arc_points_all
                        //

                        if ((($phi_start_angle + 360) != $phi_end_angle)
                            && (($phi_end_angle - $phi_start_angle) >= 180)
                        ) {
                            //
                            // This is the major cut of the tchord
                            // and the inner arc does not intersect
                            // the tchord.
                            //
                            // Draw the full inner ellipse. We
                            // deliberately start the inner loop from
                            // 1 to 360 instead of 0 to 360 so the
                            // first and last points of the inner
                            // ellipse do not overlap
                            //
                            // Note that $start_angle and $end_angle
                            // used in the if test condition are those
                            // of the outer arc.  It is the outer arc
                            // that determines if we are in the major
                            // or minor cut of the tchord.
                            //

                            $loop_start_angle = $inner_phi_end_angle + 1;
                            $loop_end_angle = $inner_phi_start_angle + 360;

                            for (
                                $arc_angle = $loop_start_angle;
                                $arc_angle <= $loop_end_angle;
                                $arc_angle+=$angle_increment
                            ) {
                                //
                                // Get the inner points for this $arc_angle
                                //

                                $inner_arc_point
                                    = $this->_getEllipseXYGivenPhi(
                                        $arc_angle, $inner_alpha,
                                        $inner_radius_x, $inner_radius_y,
                                        1, $x_grid_odd, $y_grid_odd
                                    );

                                if ($trim_tips) {
                                    if (abs(
                                        $inner_arc_point[0]
                                    ) >= $inner_arc_x_max
                                    ) {
                                        $inner_arc_point[0]
                                            = $this->_getSign(
                                                $inner_arc_point[0]
                                            )
                                            * (
                                                abs($inner_arc_point[0])
                                                - 1
                                            );
                                    }

                                    if (abs(
                                        $inner_arc_point[1]
                                    ) >= $inner_arc_y_max
                                    ) {
                                        $inner_arc_point[1]
                                            = $this->_getSign(
                                                $inner_arc_point[1]
                                            )
                                            * (
                                                abs($inner_arc_point[1])
                                                - 1
                                            );
                                    }
                                }

                                array_unshift(
                                    $inner_arc_points_all,
                                    ($centre_x + $inner_arc_point[0]),
                                    ($centre_y - $inner_arc_point[1])
                                );
                            }

                            $this->_connectDots(
                                $image,
                                $inner_arc_points_all,
                                $arc_color
                            );
                        }
                    } elseif ($definite_t_inner_arc_intersect == 1) {
                        //
                        // The inner arc touches the tchord
                        //

                        if (($phi_end_angle - $phi_start_angle) < 180) {
                            //
                            // The sweep is small.  This is the
                            // smaller cut of the tchord.
                            //

                            if ($draw_tchord_line) {
                                //
                                // We will just connect the tips of
                                // the outer arc.
                                // Do nothing extra.
                                //
                            } else {
                                //
                                // Draw part of the inner arc.
                                // Go backwards from Q to P
                                //

                                $inner_phi_angle_sweep
                                    = $inner_phi_end_angle
                                    - $inner_phi_start_angle;

                                if ($inner_phi_angle_sweep == 360) {
                                    //
                                    // _getTChordParams felt that the
                                    // inner arc would be complete.
                                    // However we know that this is the
                                    // smaller cut of the shape because
                                    // the sweep of $start_angle and
                                    // $end_angle is small, so we will
                                    // instead use the original $p_x,
                                    // $p_y, $q_x and $q_y that were
                                    // provided by _getTChordParams
                                    //
                                    // FUTURE_UPDATE:
                                    // Later I will update
                                    // _getTChordParams to fix the
                                    // inner angles so this calculation
                                    // is not necessary.  If there is
                                    // a definite intersection, then
                                    // _getTChordParams should not say
                                    // that the inner arc is also a
                                    // complete ellipse unless the
                                    // intersection is a tangent
                                    //

                                    $inner_phi_start_angle
                                        = $this->_getAngle(
                                            0, 0, $p_x, $p_y
                                        );

                                    $inner_phi_end_angle
                                        = $this->_getAngle(
                                            0, 0, $q_x, $q_y
                                        );
                                }

                                for (
                                    $arc_angle
                                        = $inner_phi_end_angle;
                                    $arc_angle
                                        >= $inner_phi_start_angle;
                                    $arc_angle
                                        -= $angle_increment
                                ) {
                                    //
                                    // Get the inner points for $arc_angle
                                    //

                                    $inner_arc_point
                                        = $this->_getEllipseXYGivenPhi(
                                            $arc_angle, $inner_alpha,
                                            $inner_radius_x,
                                            $inner_radius_y,
                                            1, $x_grid_odd, $y_grid_odd
                                        );

                                    if ($trim_tips) {
                                        if (abs(
                                            $inner_arc_point[0]
                                        ) >= $inner_arc_x_max
                                        ) {
                                            $inner_arc_point[0]
                                                = $this->_getSign(
                                                    $inner_arc_point[0]
                                                )
                                                * (
                                                    abs($inner_arc_point[0])
                                                    - 1
                                                );
                                        }

                                        if (abs(
                                            $inner_arc_point[1]
                                        ) >= $inner_arc_y_max
                                        ) {
                                            $inner_arc_point[1]
                                                = $this->_getSign(
                                                    $inner_arc_point[1]
                                                )
                                                * (
                                                    abs($inner_arc_point[1])
                                                    - 1
                                                );
                                        }
                                    }

                                    array_push(
                                        $outer_arc_points_all,
                                        ($centre_x + $inner_arc_point[0]),
                                        ($centre_y - $inner_arc_point[1])
                                    );
                                }
                            }
                        } else {
                            //
                            // The sweep is large.
                            // At least part of the inner arc must be drawn
                            //

                            //
                            // We have to draw part of the inner arc.
                            // Go backwards from Q to P
                            //

                            $inner_phi_angle_sweep
                                = $inner_phi_end_angle
                                - $inner_phi_start_angle;

                            if ($inner_phi_angle_sweep == 360) {

                                    //
                                    // _getTChordParams felt that the
                                    // inner arc would be complete.
                                    // However we know that this is the
                                    // smaller cut of the shape because
                                    // the sweep of $start_angle and
                                    // $end_angle is small, so we will
                                    // instead use the original $p_x,
                                    // $p_y, $q_x and $q_y that were
                                    // provided by _getTChordParams
                                    //
                                    // FUTURE_UPDATE:
                                    // Later I will update
                                    // _getTChordParams to fix the
                                    // inner angles so this calculation
                                    // is not necessary.  If there is
                                    // a definite intersection, then
                                    // _getTChordParams should not say
                                    // that the inner arc is also a
                                    // complete ellipse unless the
                                    // intersection is a tangent
                                    //

                                $inner_phi_end_angle
                                    = $this->_getAngle(0, 0, $q_x, $q_y);

                                $inner_phi_start_angle
                                    = $this->_getAngle(0, 0, $p_x, $p_y);
                            }

                            if ($inner_phi_start_angle >= $inner_phi_end_angle
                            ) {

                                $inner_phi_end_angle += 360;
                            }

                            for (
                                $arc_angle
                                    = $inner_phi_end_angle;
                                $arc_angle
                                    >= $inner_phi_start_angle;
                                $arc_angle
                                    -= $angle_increment
                            ) {
                                //
                                // Get the inner points for
                                // this $arc_angle
                                //

                                $inner_arc_point
                                    = $this->_getEllipseXYGivenPhi(
                                        $arc_angle, $inner_alpha,
                                        $inner_radius_x, $inner_radius_y,
                                        1, $x_grid_odd, $y_grid_odd
                                    );

                                if ($trim_tips) {
                                    if (abs(
                                        $inner_arc_point[0]
                                    ) >= $inner_arc_x_max
                                    ) {
                                        $inner_arc_point[0]
                                            = $this->_getSign(
                                                $inner_arc_point[0]
                                            )
                                            * (
                                                abs($inner_arc_point[0])
                                                - 1
                                            );
                                    }

                                    if (abs(
                                        $inner_arc_point[1]
                                    ) >= $inner_arc_y_max
                                    ) {
                                        $inner_arc_point[1]
                                            = $this->_getSign(
                                                $inner_arc_point[1]
                                            )
                                            * (
                                                abs($inner_arc_point[1])
                                                - 1
                                            );
                                    }
                                }

                                array_push(
                                    $outer_arc_points_all,
                                    ($centre_x + $inner_arc_point[0]),
                                    ($centre_y - $inner_arc_point[1])
                                );
                            }

                            if ($tchord_tangential) {
                                //
                                // The tchord was actually tangential to
                                // the inner arc.
                                // We will draw a separate line between
                                // P and Q to simulate this tangent
                                //

                                imageline(
                                    $image,
                                    ($centre_x + $p_x_neighbour),
                                    ($centre_y - $p_y_neighbour),
                                    ($centre_x + $q_x_neighbour),
                                    ($centre_y - $q_y_neighbour),
                                    $arc_color
                                );
                            }
                        }
                    }
                } elseif ($obj_shape == 'arcsegment') {
                    //
                    // For a segment, the $inner_arc_points_all is not
                    // needed at all.
                    // We only need to add the origin
                    //

                    $outer_arc_points_all[] = $centre_x;
                    $outer_arc_points_all[] = $centre_y;
                } else {
                    //
                    // This is a plain arc.
                    // Since this shape is NOT a chord, we should
                    // append $inner_arc_points_all to
                    // $outer_arc_points_all
                    //

                    $outer_arc_points_all = array_merge(
                        $outer_arc_points_all,
                        $inner_arc_points_all
                    );
                }

                $num_points = sizeof($outer_arc_points_all) / 2;

                $this->_connectDots(
                    $image,
                    $outer_arc_points_all,
                    $arc_color
                );
            }
        }
    }


    /**
     * Tidies up the $excl_x_points array and ensures that every
     * y-point has exclusion values
     *
     * For each y-key, it sorts the entries and then takes the lowest
     * and highest x-values as the only entries to remain: the array's
     * task is to mark the outer boundaries of the shapenothing is
     * allowed between the lowest and highest entries so there is no
     * need to keep any other points. It also ensures that for every
     * y-value in the full shape, there will always be an
     * $excl_x_points entry. If necessary it creates dummy values. It
     * is only used for filled shapes.
     *
     * @param array  $excl_x_points   the array containing the
     *                      boundary points of the outer part of the
     *                      shape. X-values are allowed on either side
     *                      or between the $excl_x_points range, but
     *                      lines or pixels will not be drawn across
     *                      or on the $excl_x_points values
     *                      themselves.
     * @param int    $min_outer_y     the lowest y-value of the full
     *                      ellipse of which this arc is a part.
     * @param int    $max_outer_y     the highest y-value of the full
     *                      ellipse of which this arc is a part.
     * @param float  $outer_arc_x_max the highest x-value in the full
     *                      ellipse. Used when creating dummy values
     * @param string $obj_shape       used to put in special
     *                      exclusions For example, an "arcsegment"
     *                      should never have an exclusion at (0,0)
     *
     * NOTES: Coded numbers were used for the dummy values. They
     * should not interfere with any possible shape unless the shape's
     * dimensions approach the PHP_INT_MAX limit
     *
     * This function uses the "200" series of manual exclusions: dummy
     * exclusions will be about 200 pixels larger than the
     * $outer_arc_x_max value.
     *
     * @return  array $output_array
     *
     * @access private
     *
     */
    private function _finaliseExcludedXPoints(
        $excl_x_points, $min_outer_y, $max_outer_y,
        $outer_arc_x_max, $obj_shape
    ) {

        //
        // Some key variables in this function:
        // array  $output_array    the sanitised version of
        //                          $excl_x_points.
        //

        $output_array = array();

        for ($i = $min_outer_y; $i <= $max_outer_y; $i++) {

            $i_y_keystr = $i;

            if (array_key_exists($i_y_keystr, $excl_x_points)) {

                $temp_excl_x_points = array_unique(
                    $excl_x_points[$i_y_keystr]
                );
                sort($temp_excl_x_points);

                $output_array[$i_y_keystr][] = $temp_excl_x_points[0];
                $output_array[$i_y_keystr][] = array_pop(
                    $temp_excl_x_points
                );
            } else {
                // Create dummy values for it

                $output_array[$i_y_keystr][] = $outer_arc_x_max + 200;
                $output_array[$i_y_keystr][] = $outer_arc_x_max + 200;
            }
        }

        if ($obj_shape == "arcsegment") {
            //
            // Segments never have any exclusion at the origin (0,0)
            // Enforce this rule
            //
            // Create dummy values for it

            $output_array[0] = array();
            $output_array[0][] = $outer_arc_x_max + 201;
            $output_array[0][] = $outer_arc_x_max + 201;
        }

        return $output_array;
    }


    /**
     * Tidies up the $inner_excl_x_points array and ensures that every
     * y-point has exclusion values
     *
     * For each y-key, it sorts the entries and then takes the lowest
     * and highest x-values as the only entries to remain: nothing is
     * allowed between the lowest and highest entries so there is no
     * need to keep any other points. It also ensures that for every
     * y-value in the full shape, there will always be an
     * $inner_excl_x_points entry. If necessary it creates dummy
     * values. It is only used for filled shapes.
     *
     * @param array  $inner_excl_x_points the array containing the
     *                      boundary points within the inner part of
     *                      the shape. Any x-value within this range
     *                      will not be drawn as this area should be
     *                      hollow.
     * @param int    $min_outer_y         the lowest y-value of the
     *                      full ellipse of which this arc is a part.
     * @param int    $max_outer_y         the highest y-value of the
     *                      full ellipse of which this arc is a part.
     * @param float  $outer_arc_x_max     the highest x-value in the
     *                      full ellipse. Used when creating dummy
     *                      values
     * @param string $obj_shape           used to put in special
     *                      exclusions For example, an "arcsegment"
     *                      should never have an exclusion at (0,0)
     *
     * NOTES: Coded numbers were used for the dummy values. They
     * should not interfere with any possible shape unless the shape's
     * dimensions approach the PHP_INT_MAX limit
     *
     * This function uses the "100" series of manual exclusions: dummy
     * exclusions will be about 100 pixels larger than the
     * $outer_arc_x_max value.
     *
     * @return  array $output_array the sanitised version of
     *                      $inner_excl_x_points.
     *
     * @access private
     *
     */
    private function _finaliseInnerExcludedXPoints(
        $inner_excl_x_points, $min_outer_y, $max_outer_y,
        $outer_arc_x_max, $obj_shape
    ) {
        //
        // Some key variables in this function:
        // array  $output_array        the sanitised version of
        //                          $inner_excl_x_points.
        //

        $output_array = array();

        for ($i = $min_outer_y; $i <= $max_outer_y; $i++) {
            $i_y_keystr = $i;

            if (array_key_exists(
                $i_y_keystr,
                $inner_excl_x_points
            )
            ) {

                $temp_inner_excl_x_points
                    = $inner_excl_x_points[$i_y_keystr];
                $temp_inner_excl_x_points
                    = array_unique($temp_inner_excl_x_points);

                //
                // Remove any nulls that might have crept into the data
                //
                // FUTURE_UPDATE: Stop these nulls from occurring at all
                //

                $temp2_inner_excl_x_points = array();
                if (sizeof($temp_inner_excl_x_points)) {
                    foreach ($temp_inner_excl_x_points as $temp_x) {
                        if (is_null($temp_x)) {
                            continue;
                        }
                        $temp2_inner_excl_x_points[] = $temp_x;
                    }
                }
                sort($temp2_inner_excl_x_points);

                $num_entries = sizeof($temp2_inner_excl_x_points);

                if ($num_entries > 0) {
                    //
                    // It contains valid data.
                    // Read the first and last entries into $output_array
                    //

                    $output_array[$i_y_keystr][]
                        = $temp2_inner_excl_x_points[0];

                    $output_array[$i_y_keystr][]
                        = array_pop($temp2_inner_excl_x_points);
                } else {
                    //
                    // The array entry for this $i exists but is empty.
                    // The entries were probably deleted because they
                    // clashed with points that were known to be valid
                    // for the shape.
                    // Create dummy entries.
                    //

                    $output_array[$i_y_keystr][] = $outer_arc_x_max + 100;
                    $output_array[$i_y_keystr][] = $outer_arc_x_max + 100;
                }
            } else {
                // Create dummy values for it

                $output_array[$i_y_keystr][] = $outer_arc_x_max + 101;
                $output_array[$i_y_keystr][] = $outer_arc_x_max + 101;
            }
        }

        if ($obj_shape == "arcsegment") {
            //
            // Segments never have any exclusion at the origin (0,0)
            // Enforce this rule
            //
            // Create dummy values for it
            //

            $output_array[0] = array();
            $output_array[0][] = $outer_arc_x_max + 102;
            $output_array[0][] = $outer_arc_x_max + 102;
        }

        return $output_array;
    }


    /**
     * Returns the angle that one x-y point makes with another
     *
     * The angle is always taken counter-clockwise from the starting
     * point to the ending point, with the 3 o'clock position being
     * zero degrees as per the Cartesian system. Most of the time the
     * starting point is the origin (0, 0).
     *
     * @param float $x1 the x-value of the starting point
     * @param float $y1 the y-value of the starting point
     * @param float $x2 the x-value of the ending point
     * @param float $y2 the y-value of the ending point
     *
     * @return  float $angle
     *
     * @access private
     *
     */
    private function _getAngle($x1, $y1, $x2, $y2)
    {
        //
        // Some key variables in this function:
        // float $angle the between the starting and ending points
        //

        $x_length = $x2 - $x1;
        $y_length = $y2 - $y1;

        if (($x2 == $x1) && ($y2 == $y1)) {
            //
            // It is the same point.
            // The angle will be set to 0
            //

            $angle = 0;
        } else {
            $x_length_sqr = $x_length *$x_length;
            $y_length_sqr = $y_length *$y_length;

            $hyp = sqrt($x_length_sqr + $y_length_sqr);

            $angle = abs(rad2deg(acos(abs($x_length) / $hyp)));
        }

        $x_is_positive = ($x_length < 0) ? 0 : 1;
        $y_is_positive = ($y_length < 0) ? 0 : 1;

        if ($y_is_positive) {
            if ($x_is_positive) {
                // 1st quadrant
                $angle = $angle;
            } else {
                // 2nd quadrant
                $angle = 180 - $angle;
            }
        } else {
            if ($x_is_positive) {
                // 4th quadrant
                $angle = 360 - $angle;
            } else {
                // 3rd quadrant
                $angle = 180 + $angle;
            }
        }

        return $angle;
    }


    /**
     * Returns the x-y coordinates where the slope of the ellipse is
     * zero
     *
     * This corresponds to the minimum and maximum values that y can
     * have in this ellipse. This was calculated from first principles
     * by finding the value of x that makes dy/dx of a normal ellipse
     * = -Tan($alpha)
     *
     * If the original point had coordinates ($x_orig,$y_orig) with
     * slope dy/dx=-Tan($alpha), then the rotation through $alpha will
     * cause dy/dx to be zero.
     *
     * Note: this function only returns one of the two points where
     * dy/dx is 0. Taking the inversion of the returned point will
     * yield the other point. For example if one of the points is
     * (4,13), then the other point will be (-4,-13). The two points
     * are always symmetrical about the line y=x.
     *
     * @param float $alpha       the axis rotation angle
     * @param float $ellipse_a   the semi-major axis of the unrotated
     *                      ellipse.
     * @param float $ellipse_b   the semi-minor axis of the unrotated
     *                      ellipse.
     * @param bool  $apply_grids a binary switch. If true, then the
     *                      $x_grid_odd and $y_grid_odd parameters
     *                      should be applied to the returned x-y
     *                      point.
     * @param bool  $x_grid_odd  a binary switch. If "1", then the
     *                      x-value used in the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     * @param bool  $y_grid_odd  a binary switch. If "1", then the
     *                      y-value used in the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     *
     * The $apply_grids parameter is only false when calculating the
     * initial outer height and width of the rotated ellipse. Those
     * results then determine whether the grids are even or odd.
     *
     * @return array  containing the following parameters:
     *                      the x-y point where dy/dx = 0 for alpha.
     *                      This is done by rotating point
     *                      ($x_orig,$y_orig) through $alpha
     *
     * @access private
     *
     */
    private function _getDyDxZeroForAlpha(
        $alpha,
        $ellipse_a,
        $ellipse_b,
        $apply_grids = 1,
        $x_grid_odd = 1,
        $y_grid_odd = 1
    ) {
        //
        // Some key variables in this function:
        // float $x_orig      the x-value on the original ellipse
        //                          before it was rotated. The dy/dx for
        //                          $x_orig is -Tan($alpha)
        // float $y_orig      the y-value on the original ellipse
        //                          before it was rotated. The dy/dx for
        //                          $y_orig is -Tan($alpha)
        // float $orig_angle  the angle that $x_orig and its
        //                          y-value made with the origin of the
        //                          unrotated ellipse
        //

        if (($ellipse_a == 0) || ($ellipse_b == 0)) {
            return array(0, 0);
        }

        //
        // If $alpha is a multiple of 90 or 180, these
        // are special cases whose answers are easily
        // determined.  Do not use the calculations because
        // they introduce unnecessary errors eg instead of
        // x being "0", it will be "5.765E-15".
        //

        if (! ($alpha % 180)) {
            //
            // The angle is a multiple of 180, so dy/dx
            // is zero at x=0, y=$ellipse_b.
            // Return these values directly
            //

            return array(0, $ellipse_b);
        } elseif (! ($alpha % 90)) {

            //
            // The angle is a multiple of 90.  We have already
            // eliminated the multiples of 180, so this time the
            // ellipse is flipped through 90 degrees.  The dy/dx
            // is zero at x=0, y=$ellipse_a.
            // Return these values directly
            //

            return array(0, $ellipse_a);
        }

        $x_orig = null;
        $orig_angle = null;

        if (($alpha == 0) || ($alpha == 180)) {
            //
            // The ellipse is symmetrical around the y-axis.
            //
            //
            $x_orig = $ellipse_a;
            $orig_angle = 90;
        } elseif (($alpha == 90) || ($alpha == 270)) {
            $x_orig = $ellipse_b;
            $orig_angle = 0;
        } else {
            $tan_alpha = tan(deg2rad(($alpha)));

            $a_sqr = $ellipse_a * $ellipse_a;
            $b_sqr = $ellipse_b * $ellipse_b;
            $tan_alpha_sqr = $tan_alpha * $tan_alpha;

            if (abs($tan_alpha) > 10000) {
                //
                // The tan is excessively large, so just approximate
                //

                $tan_alpha = $this->_getSign($tan_alpha) * 10000;
            }

            $x_orig = ($a_sqr * $tan_alpha)
                / sqrt($a_sqr * $tan_alpha_sqr + $b_sqr);

            //
            // Now calculate the y-value that matches this $x_orig.
            // To avoid potential issues with Tan, Sin and Cos being zero
            // and then giving misleading values when dividing, I use
            // the normal equation of an ellipse to find y ie
            // (x/a)^2 + (y/b)^2 = 1
            //

            $x_orig_sqr = $x_orig * $x_orig;

            $y_orig = sqrt(
                ($a_sqr * $b_sqr)
                - ($b_sqr * $x_orig_sqr)
            )
            / $ellipse_a;

            $orig_angle = $this->_getAngle(0, 0, $x_orig, $y_orig);
        }

        //
        // We now have ($x_orig, $y_orig) for the original ellipse before
        // it was rotated.  Now rotate it through angle $alpha and return
        // the result
        //

        return $this->_getEllipseXYGivenPhi(
            ($orig_angle + $alpha), $alpha,
            $ellipse_a, $ellipse_b,
            $apply_grids, $x_grid_odd, $y_grid_odd
        );
    }


    /**
     * Finds the two X values that correspond to a particular Y on an
     * ellipse that has rotated
     *
     * @param float $y          the y-value whose x-values need to be
     *                      determined.
     * @param float $alpha      the axis rotation angle
     * @param float $ellipse_a  the semi-major axis of the unrotated
     *                      ellipse.
     * @param float $ellipse_b  the semi-minor axis of the unrotated
     *                      ellipse.
     * @param bool  $x_grid_odd a binary switch. If "1", then the
     *                      x-value from the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     * @param bool  $y_grid_odd a binary switch. If "1", then the
     *                      y-value from the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     *
     * Note that $ellipse_a can be less than $ellipse_b.
     *
     * @return array  containing the following parameters:
     *        float $x_1
     *        float $x_2
     *        float $phi_1
     *        float $phi_2
     *
     * @access private
     *
     */
    private function _getEllipseXsGivenY(
        $y, $alpha,
        $ellipse_a, $ellipse_b,
        $x_grid_odd, $y_grid_odd
    ) {
        //
        // Some key variables in this function:
        // float $f          the focus, given by $f_sqr = $a_sqr -
        //                          $b_sqr
        // float $x_1        the x-value on the left
        // float $x_2        the x-value on the right
        // float $phi_1      the angle that $x_1 makes with the
        //                          origin. It is adjusted to fit in the
        //                          0<phi<360 range
        // float $phi_2      the angle that $x_2 makes with the
        //                          origin. It is adjusted to fit in the
        //                          0<phi<360 range
        //

        $a_sqr = $ellipse_a * $ellipse_a;
        $b_sqr = $ellipse_b * $ellipse_b;
        $f_sqr = $a_sqr - $b_sqr;
        $cos_alpha = cos(deg2rad($alpha));
        $sin_alpha = sin(deg2rad($alpha));
        $cos_alpha_sqr = $cos_alpha * $cos_alpha;
        $sin_alpha_sqr = $sin_alpha * $sin_alpha;

        $y_sqr = $y * $y;

        $phi_1 = null;
        $phi_2 = null;

        //
        // $tan_phi_1 and $tan_phi_2 are the roots of a quadratic
        // equation with $q_a, $q_b and $q_c as the coefficients
        //

        $q_a = $a_sqr * ($b_sqr - $y_sqr)
            + $y_sqr * $f_sqr * $sin_alpha_sqr;

        $q_b = 2 * $y_sqr * $f_sqr * $cos_alpha * $sin_alpha;

        $q_c = $y_sqr * ($f_sqr * $cos_alpha_sqr - $a_sqr);

        if (abs($q_a) < 0.00000001) {
            // $q_a is excessively small, so approximate it to zero

            $q_a = 0;
        }

        if ($q_a == 0) {
            //
            // The denominator is zero, so we have to determine
            // $phi_1 and $phi_2 by other means
            //

            $x_1 = 0.0000001;
            $x_2 = -0.0000001;

            $phi_1 = 90;
            $phi_2 = 270;
        } else {
            //
            // $sub_sqrt is the second numerator in the quadratic
            // formula
            //

            $sub_sqrt = sqrt($q_b * $q_b - 4 * $q_a * $q_c);

            if (is_nan($sub_sqrt)) {
                //
                // The result is an "NAN" ie an imaginary number.  Abort
                // this calculation
                //

                return array(null, null, null, null);
            }

            $tan_phi_1 = (-$q_b + $sub_sqrt) / (2 * $q_a);
            $tan_phi_2 = (-$q_b - $sub_sqrt) / (2 * $q_a);

            $phi_1 = rad2deg(atan($tan_phi_1));
            $phi_2 = rad2deg(atan($tan_phi_2));

            if ($tan_phi_1 == 0) {
                list($x_1, $ignored_y)
                    = $this->_getEllipseXYGivenPhi(
                        180, $alpha,
                        $ellipse_a, $ellipse_b,
                        1, $x_grid_odd, $y_grid_odd
                    );
            } else {
                $x_1 = $y / $tan_phi_1;
            }

            if ($tan_phi_2 == 0) {
                list($x_2, $ignored_y)
                    = $this->_getEllipseXYGivenPhi(
                        0, $alpha,
                        $ellipse_a, $ellipse_b,
                        1, $x_grid_odd, $y_grid_odd
                    );
            } else {
                $x_2 = $y / $tan_phi_2;
            }
        }

        $phi_1 = $this->_angleIn360($phi_1);

        if ($phi_1 > 270) {
            $phi_1_min = 360 - $phi_1;
        } elseif ($phi_1 > 180) {
            $phi_1_min = $phi_1 - 180;
        } elseif ($phi_1 > 90) {
            $phi_1_min = 180 - $phi_1;
        } else {
            $phi_1_min = $phi_1;
        }

        if ($y >= 0) {
            // $phi_1 must be in the 1st or 2nd quadrants

            if ($x_1 >= 0) {
                // $phi_1 is in the 1st quadrant
                $phi_1 = $phi_1_min;
            } else {
                // $phi_1 is in the 2nd quadrant
                $phi_1 = 180 - $phi_1_min;
            }
        } else {
            // $phi_1 must be in the 3rd or 4th quadrants

            if ($x_1 >= 0) {
                // $phi_1 is in the 4th quadrant
                $phi_1 = 360 - $phi_1_min;
            } else {
                // $phi_1 is in the 3rd quadrant
                $phi_1 = 180 + $phi_1_min;
            }
        }

        $phi_2 = $this->_angleIn360($phi_2);

        if ($phi_2 > 270) {
            $phi_2_min = 360 - $phi_2;
        } elseif ($phi_2 > 180) {
            $phi_2_min = $phi_2 - 180;
        } elseif ($phi_2 > 90) {
            $phi_2_min = 180 - $phi_2;
        } else {
            $phi_2_min = $phi_2;
        }

        if ($y >= 0) {
            // $phi_2 must be in the 1st or 2nd quadrants

            if ($x_2 >= 0) {
                // $phi_2 is in the 1st quadrant
                $phi_2 = $phi_2_min;
            } else {
                // $phi_2 is in the 2nd quadrant
                $phi_2 = 180 - $phi_2_min;
            }
        } else {
            // $phi_2 must be in the 3rd or 4th quadrants

            if ($x_2 >= 0) {
                // $phi_2 is in the 4th quadrant
                $phi_2 = 360 - $phi_2_min;
            } else {
                // $phi_2 is in the 3rd quadrant
                $phi_2 = 180 + $phi_2_min;
            }
        }

        if ($x_2 < $x_1) {
            //
            // swap $x_1 and $x_2 so that $x_1 is always on the left
            //

            $temp_x = $x_1;
            $x_1 = $x_2;
            $x_2 = $temp_x;

            $temp_phi = $phi_1;
            $phi_1 = $phi_2;
            $phi_2 = $temp_phi;
        }

        return array(
            $this->_gridAdjust($x_1, $x_grid_odd),
            $this->_gridAdjust($x_2, $x_grid_odd),
            $phi_1,
            $phi_2
        );
    }


    /**
     * Finds the X-Y pair that match a particular angle on an ellipse
     *
     * It uses the equation of an ellipse that has rotated anywhere in
     * the X-Y plane.
     *
     * The formula uses first principles to find the X value of a
     * point on an ellipse that has rotated through angle $alpha. The
     * expression is done in terms of the major/minor axes, the
     * rotation angle $alpha, and the known final angle $phi of the
     * point.
     *
     * After calculating the X value with the formula, it then finds
     * the Y-value using y = x * tan(phi)
     *
     * The formula works even if the minor axis is greater than the
     * major axis.
     *
     * @param int   $phi         the final angle of the point in the
     *                      rotated ellipse, given by $phi = $theta +
     *                      $alpha, where $theta would have been the
     *                      point's angle with the origin in a
     *                      non-rotated ellipse
     * @param float $alpha       the axis rotation angle
     * @param float $ellipse_a   the semi-major axis of the unrotated
     *                      ellipse.
     * @param float $ellipse_b   the semi-minor axis of the unrotated
     *                      ellipse.
     * @param bool  $apply_grids a binary switch. Defaults to 1. If
     *                      true, then the $x_grid_odd and $y_grid_odd
     *                      parameters should be applied to the
     *                      returned x-y point.
     * @param bool  $x_grid_odd  a binary switch. Defaults to 1. If
     *                      "1", then the x-value from the
     *                      calculations will be rounded down to the
     *                      nearest integer. If "0", it will be
     *                      rounded to the nearest half-integer. For
     *                      example "0.5", "12.5", "-6.5"
     * @param bool  $y_grid_odd  a binary switch. Defaults to 1. If
     *                      "1", then the y-value from the
     *                      calculations will be rounded down to the
     *                      nearest integer. If "0", it will be
     *                      rounded to the nearest half-integer. For
     *                      example "0.5", "12.5", "-6.5"
     *
     * Note that $ellipse_a can be less than $ellipse_b.
     *
     * @return array  containing the following parameters:
     *        int   $x
     *        int   $y
     *
     * @access private
     *
     */
    private function _getEllipseXYGivenPhi(
        $phi,
        $alpha,
        $ellipse_a,
        $ellipse_b,
        $apply_grids = 1,
        $x_grid_odd = 1,
        $y_grid_odd = 1
    ) {
        //
        // Some key variables in this function:
        // float $f           the focus, where $f_sqr = $a_sqr -
        //                          $b_sqr
        // float $x           the x-value of the desired point
        // float $y           the y-value of the desired point
        //

        if (($ellipse_a == 0) || ($ellipse_b == 0)) {
            return array(
                $this->_gridAdjust($ellipse_a, $x_grid_odd),
                $this->_gridAdjust($ellipse_b, $y_grid_odd)
            );
        }

        $phi = $this->_angleIn360($phi);

        $a_sqr = $ellipse_a * $ellipse_a;
        $b_sqr = $ellipse_b * $ellipse_b;
        $f_sqr = $a_sqr - $b_sqr;
        $tan_phi = tan(deg2rad($phi));
        $cos_alpha = cos(deg2rad($alpha));
        $sin_alpha = sin(deg2rad($alpha));
        $tan_phi_sqr = $tan_phi * $tan_phi;

        $numerator = $b_sqr;        // ie (a_sqr - $f_sqr)
        $denom_1 = $a_sqr * (1 + $tan_phi_sqr);
        $denom_2 = $f_sqr * ($cos_alpha + $sin_alpha * $tan_phi)
            * ($cos_alpha + $sin_alpha * $tan_phi);

        $denominator = $denom_1 - $denom_2;

        $x = abs($ellipse_a * sqrt($numerator / $denominator));
        $y = abs($x * $tan_phi);

        $phi_quadrant = $this->_getQuadrant($phi, 1);

        switch ($phi_quadrant) {
        //
        // As per the ASTC (All-Sin-Tan-Cos) quadrant convention.
        //
        // $x follows COS, $y follows SIN

        case 1:
        case 0:
        case 5:
            // Do nothing since SIN and COS are both positive
            break;

        case 2:
            // Only SIN is positive
            $x = -$x;
            break;

        case 3:
            // SIN and COS are both negative
            $x = -$x;
            $y = -$y;
            break;

        case 4:
            // Only COS is positive
            $y = -$y;
            break;
        }

        if ($apply_grids) {
            return array(
                $this->_gridAdjust($x, $x_grid_odd),
                $this->_gridAdjust($y, $y_grid_odd)
            );
        } else {
            return array(
                $x,
                $y
            );
        }
    }


    /**
     * returns the coefficients of the line equation given two points
     * on the line
     *
     * EllipseArc often needs to calculate the distance from a point
     * to a line, so this function standardises the process.
     *
     * @param float $x_start the x-value of the starting point of the
     *                      line
     * @param float $y_start the y-value of the starting point of the
     *                      line
     * @param float $x_end   the x-value of the ending point of the
     *                      line
     * @param float $y_end   the y-value of the ending point of the
     *                      line
     *
     * @return array  containing the following parameters:
     *        int   $line_x_coeff
     *        int   $line_y_coeff
     *        int   $line_x_coeff
     *
     * @access private
     *
     */
    private function _getLineEqnCoeffs(
        $x_start, $y_start, $x_end, $y_end
    ) {
        //
        // Some key variables in this function:
        // float $line_x_coeff the x-coefficient of the line equation.
        // float $line_y_coeff the y-coefficient of the line equation.
        // float $line_x_coeff the constant of the line equation.
        //

        $line_x_coeff = ($y_end - $y_start);
        $line_y_coeff = ($x_start - $x_end);
        $line_c_coeff = $x_start * ($y_start - $y_end)
            + $y_start * ($x_end - $x_start);

        return array($line_x_coeff, $line_y_coeff, $line_c_coeff);
    }


    /**
     * Returns all the points on a line; ensuring that every X-value
     * between the start and end is represented
     *
     * It caters for lines that have very small slopes: if such lines
     * were plotted using only the y-values, they would have many
     * holes since the y-value can only map to one of the multiple
     * x-values.
     *
     * This function ensures that all the points in the line are drawn
     * properly. It is mainly used by tchords.
     *
     * The sister-function is _getLinePointsAllYs.
     *
     * @param float $x_start    the x-value of the starting point of
     *                      the line
     * @param float $y_start    the y-value of the starting point of
     *                      the line
     * @param float $x_end      the x-value of the ending point of the
     *                      line
     * @param float $y_end      the y-value of the ending point of the
     *                      line
     * @param bool  $x_grid_odd a binary switch. If "1", then the
     *                      x-value used in the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     * @param bool  $y_grid_odd a binary switch. If "1", then the
     *                      y-value used in the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     *
     * @return array  containing the following parameters:
     *        int   $line_xy_points
     *        int   $num_points
     *        int   $line_slope
     *
     * The last two variables are appended to $line_xy_points.
     *
     * NOTE: The line always has the y-values increasing along the
     * line. This is deliberate because EllipseArc essentially parses
     * UP through the shape from the lowest to the highest point.
     *
     * @access private
     *
     */
    private function _getLinePointsAllXs(
        $x_start, $y_start,
        $x_end, $y_end,
        $x_grid_odd, $y_grid_odd
    ) {
        //
        // Some key variables in this function:
        // array $line_xy_points a one-dimensional array containing all
        //                      the x-y points in the line. The points
        //                      are arranged in a linear row
        //                      x1,y1,x2,y2,x3,y3...
        // float $line_slope determines if the gradient is
        //                      positive, negative or 0
        // string $insertion_direction shows whether the points are
        //                      appended or prepended to $line_xy_points.
        //                      This is necessary to ensure that the
        //                      y-values are always increasing regardless
        //                      of the true slope or nature of the line.
        // int   $num_points the number of points that have been
        //                      added
        //

        $line_xy_points = array();
        $num_points = 0;
        $line_slope = null;

        $insertion_direction = "left X TO right";

        if (($x_start == $x_end) && ($y_start == $y_end)) {
            //
            // Do nothing.  This is a single point
            //
        } elseif ($x_end == $x_start) {
            //
            // A vertical line
            // Plot points for every y from the lowest to the highest
            //

            $line_slope = 10000;

            $line_y_start = $y_start;
            $line_y_end    = $y_end;

            if ($line_y_start > $line_y_end) {
                $line_y_start = $y_end;
                $line_y_end    = $y_start;
                $line_slope = -10000;

                $insertion_direction = "right X TO left";
            }

            for (
                $line_y = $line_y_start;
                $line_y <= $line_y_end;
                $line_y++
            ) {
                if ($insertion_direction == "left X TO right") {
                    array_push($line_xy_points, $x_start, $line_y);
                } else {
                    array_unshift($line_xy_points, $x_start, $line_y);
                }

                $num_points++;
            }
        } elseif ($y_end == $y_start) {
            //
            // A horizontal line
            // Plot points for every x from the lowest to the highest
            //

            $line_slope = 0;

            $line_x_start = $x_start;
            $line_x_end    = $x_end;
            $y_start = $y_start;

            if ($line_x_start > $line_x_end) {
                $line_x_start = $x_end;
                $line_x_end    = $x_start;

                $insertion_direction = "right X TO left";
            }

            for (
                $line_x = $line_x_start;
                $line_x <= $line_x_end;
                $line_x++
            ) {
                if ($insertion_direction == "left X TO right") {
                    array_push($line_xy_points, $line_x, $y_start);
                } else {
                    array_unshift($line_xy_points, $line_x, $y_start);
                }

                $num_points++;
            }
        } else {
            //
            // The line has a regular slope
            //

            $line_x_start = $x_start;
            $line_y_start = $y_start;
            $line_x_end    = $x_end;
            $line_y_end    = $y_end;

            if ($line_x_start > $line_x_end) {

                $line_x_start = $x_end;
                $line_y_start = $y_end;
                $line_x_end    = $x_start;
                $line_y_end    = $y_start;

                $insertion_direction = "right X TO left";
            }

            $line_slope = ($y_end - $y_start) / ($x_end - $x_start);

            //
            // The line equation should be determined in terms of x.  This
            // guarantees that every x-point will be defined
            //

            for (
                $line_x = $line_x_start;
                $line_x <= $line_x_end;
                $line_x++
            ) {
                $line_y = $this->_gridAdjust(
                    $line_y_start + $line_slope
                    * ($line_x - $line_x_start),
                    $y_grid_odd
                );

                if ((($line_y < $line_y_start)
                    && ($line_y < $line_y_end))
                    || (($line_y > $line_y_start)
                    && ($line_y > $line_y_end))
                ) {
                    //
                    // This point is outside the defined parameters of
                    // the line ie it is beyond the start and end points
                    // Do not add this point to the $line_xy_points array
                    //
                } else {
                    //
                    // The parameters are okay.  Add them to the
                    // $line_xy_points array
                    //

                    if ($insertion_direction == "left X TO right") {
                        array_push($line_xy_points, $line_x, $line_y);
                    } else {
                        array_unshift(
                            $line_xy_points,
                            $line_x, $line_y
                        );
                    }

                    $num_points++;
                }
            }
        }

        //
        // append $num_points and $line_slope to this array
        //

        $line_xy_points[] = $num_points;
        $line_xy_points[] = $line_slope;

        return $line_xy_points;
    }


    /**
     * Returns all the points on a line; ensuring that every Y-value
     * between the start and end is represented
     *
     * It caters for lines that have very large slopes: if such lines
     * were plotted using only the x-values, they would have many
     * holes since the x-value can only map to one of the multiple
     * y-values.
     *
     * This is the main function EllipseArc uses when getting line
     * points, as most lines can be built sufficiently from the
     * horizontal y-pixel information. This function prevents any gaps
     * appearing between the y-rows. It is only when building tchord
     * lines that the sister function (_getLinePointsAllXs) is
     * required.
     *
     * The sister function is _getLinePointsAllXs
     *
     * @param float $x_start    the x-value of the starting point of
     *                      the line
     * @param float $y_start    the y-value of the starting point of
     *                      the line
     * @param float $x_end      the x-value of the ending point of the
     *                      line
     * @param float $y_end      the y-value of the ending point of the
     *                      line
     * @param bool  $x_grid_odd a binary switch. If "1", then the
     *                      x-value used in the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     * @param bool  $y_grid_odd a binary switch. If "1", then the
     *                      y-value used in the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     *
     * @return array  containing the following parameters:
     *        int   $line_xy_points
     *        int   $num_points
     *        int   $line_slope
     *
     * The last two variables are appended to $line_xy_points.
     *
     * NOTE: The line ALWAYS has the y-values increasing along the
     * line. This is deliberate because EllipseArc essentially parses
     * UP through the shape from the lowest to the highest point.
     *
     * @access private
     *
     */
    private function _getLinePointsAllYs(
        $x_start, $y_start,
        $x_end, $y_end,
        $x_grid_odd, $y_grid_odd
    ) {
        //
        // Some key variables in this function:
        // array $line_xy_points a one-dimensional array containing all
        //                      the x-y points in the line. The
        //                      points are arranged in a linear row
        //                      x1,y1,x2,y2,x3,y3...
        // float $line_slope determines if the gradient is
        //                      positive, negative or 0
        // string $insertion_direction shows whether the points are
        //                      appended or prepended to $line_xy_points.
        //                      This is necessary to ensure that the
        //                      y-values are always increasing regardless
        //                       of the true slope or nature of the line.
        // int   $num_points the number of points that have been
        //                      added
        //

        $line_xy_points = array();
        $num_points = 0;
        $line_slope = null;

        $insertion_direction = "left X TO right";

        if (($x_start == $x_end) && ($y_start == $y_end)) {
            //
            // Do nothing.  This is a single point
            //
        } elseif ($x_end == $x_start) {
            //
            // A vertical line
            // Plot points for every y from the lowest to the highest
            //

            $line_slope = 10000;

            $line_y_start = $y_start;
            $line_y_end    = $y_end;

            if ($line_y_start > $line_y_end) {
                $line_y_start = $y_end;
                $line_y_end    = $y_start;
                $line_slope = -10000;

                $insertion_direction = "right X TO left";
            }

            for (
                $line_y = $line_y_start;
                $line_y <= $line_y_end;
                $line_y++
            ) {
                if ($insertion_direction == "left X TO right") {
                    array_push($line_xy_points, $x_start, $line_y);
                } else {
                    array_unshift($line_xy_points, $x_start, $line_y);
                }

                $num_points++;
            }
        } elseif ($y_end == $y_start) {
            //
            // A horizontal line
            // Plot points for every x from the lowest to the highest
            //

            $line_slope = 0;

            $line_x_start = $x_start;
            $line_x_end    = $x_end;
            $y_start = $y_start;

            if ($line_x_start > $line_x_end) {
                $line_x_start = $x_end;
                $line_x_end    = $x_start;

                $insertion_direction = "right X TO left";
            }

            for (
                $line_x = $line_x_start;
                $line_x <= $line_x_end;
                $line_x++
            ) {
                if ($insertion_direction == "left X TO right") {
                    array_push($line_xy_points, $line_x, $y_start);
                } else {
                    array_unshift($line_xy_points, $line_x, $y_start);
                }

                $num_points++;
            }
        } else {
            //
            // The line has a regular slope
            //

            $line_x_start = $x_start;
            $line_y_start = $y_start;
            $line_x_end    = $x_end;
            $line_y_end    = $y_end;

            if ($line_y_start > $line_y_end) {

                $line_x_start = $x_end;
                $line_y_start = $y_end;
                $line_x_end    = $x_start;
                $line_y_end    = $y_start;

                $insertion_direction = "right X TO left";
            }

            $line_slope = ($y_end - $y_start) / ($x_end - $x_start);

            //
            // The line equation should be determined in terms of y.
            // This guarantees that every y-point will be defined
            //

            for (
                $line_y = $line_y_start;
                $line_y <= $line_y_end;
                $line_y++
            ) {

                $line_x = ($line_x_start
                    + ($line_y - $line_y_start) / $line_slope
                );

                if ((($line_x < $line_x_start)
                    && ($line_x < $line_x_end))
                    || (($line_x > $line_x_start)
                    && ($line_x > $line_x_end))
                ) {
                    //
                    // This point is outside the defined parameters
                    // for the line ie it is beyond the start and
                    // end points
                    // Do not add this point to the $line_xy_points
                    // array as it could cause some pixels to be skipped
                    //
                } else {
                    //
                    // The parameters are okay.  Add them to the
                    // $line_xy_points array
                    //

                    $line_x = $this->_gridAdjust($line_x, $x_grid_odd);

                    if ($insertion_direction == "left X TO right") {
                        array_push($line_xy_points, $line_x, $line_y);
                    } else {
                        array_unshift($line_xy_points, $line_x, $line_y);
                    }

                    $num_points++;
                }
            }
        }

        //
        // append $num_points and $line_slope to this array
        //

        $line_xy_points[] = $num_points;
        $line_xy_points[] = $line_slope;

        return $line_xy_points;
    }


    /**
     * Used when the tchord does not have an explicit entry in
     * $t_line_points for a particular y-value
     *
     * The x-values on the inner and outer arcs can be compared
     * against the results of this function, and depending on the
     * value of $tchord_excl_side, the x-values might be added to
     * $filled_points_all
     *
     * @param int   $row_y             the y-value of the point being
     *                      processed
     * @param float $tchord_line_slope the slope of the tchord line
     * @param float $tchord_x          an x-value on the tchord line
     * @param float $tchord_y          a y-value on the tchord line
     * @param bool  $x_grid_odd        a binary switch. If "1", then
     *                      the x-value used in the calculations will
     *                      be rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     * @param bool  $y_grid_odd        a binary switch. If "1", then
     *                      the y-value used in the calculations will
     *                      be rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     *
     * @return array  containing the following parameters:
     *                      the projected x-values that match this
     *                      y-value. If there is a unique x-value,
     *                      then it is duplicated whereas a horizontal
     *                      line provides a large range.
     *
     * It returns an array so that horizontal lines can be represented
     * correctly. Usually a line can only have one x-value for any
     * given y-value, but horizontal lines have an infinite number of
     * x-values for their y-value, which is why a range is returned.
     * For a regular line the x-value range is the same number; for a
     * horizontal line the values are a very large negative and a very
     * large positive number range.
     *
     * @access private
     *
     */
    private function _getProjectedTChordXs(
        $row_y, $tchord_line_slope,
        $tchord_x, $tchord_y,
        $x_grid_odd, $y_grid_odd
    ) {
        if ($tchord_line_slope == 0) {
            // A horizontal line
            $projected_tchord_x_1 = -89898989;
            $projected_tchord_x_2 =  89898989;
        } elseif (abs($tchord_line_slope) == 10000) {
            // A vertical line
            $projected_tchord_x_1 = $tchord_x;
            $projected_tchord_x_2 = $tchord_x;
        } else {
            // A regular line
            $projected_tchord_x_1 = $this->_gridAdjust(
                (
                    $tchord_x
                    + ($row_y - $tchord_y) / $tchord_line_slope
                ),
                $x_grid_odd
            );
            $projected_tchord_x_2 = $projected_tchord_x_1;
        }

        return array($projected_tchord_x_1, $projected_tchord_x_2);
    }


    /**
     * Returns the Cartesian quadrant of the supplied angle
     *
     * @param float $input_angle the angle to check.
     * @param bool  $offset      a flag. Permits explicit control of
     *                      the quadrant for angles that are multiples
     *                      of 90. If true, the angle will be dropped
     *                      down into the next lower quadrant. For
     *                      example, 90 degrees would normally be
     *                      grouped into the 2nd quadrant, however if
     *                      $offset is TRUE then 90 degrees will be
     *                      grouped in the 1st quadrant.
     *
     * @return  float $quad_result
     *
     * @access private
     *
     */
    private function _getQuadrant($input_angle, $offset)
    {
        //
        // Some key variables in this function:
        // float $quad_result the quadrant of the $input_angle
        //

        while ($input_angle < 0) {
            $input_angle += 360;
        }

        $quad_result = (int)($input_angle / 90) + 1;

        if ($offset) {
            while ($input_angle >= 90) {
                $input_angle -= 90;
            }

            if ($input_angle == 0) {
                //
                // $input_angle is a perfect multiple of 90, so we
                // are using $offset to indicate that the quadrant
                // should be 1 lower than what was originally
                // reported by $result
                //

                $quad_result--;
            }
        }

        return $quad_result;
    }


    /**
     * Returns the sign of the input parameter
     *
     * It makes the code look neater since the signs of variables are
     * frequently checked by EllipseArc
     *
     * @param float $input the number to check.
     *
     * @return  int "-1" if the number is less than zero, "1"
     *                      otherwise.
     *
     * @access private
     *
     */
    private function _getSign($input)
    {
        return ($input < 0) ? -1 : 1;
    }


    /**
     * Generates all the parameters of the tchord line for a tchord
     * shape
     *
     * Only tchords use this function.
     *
     * @param float $alpha                 the axis rotation angle
     * @param float $phi_start_angle       the outer starting angle of
     *                      the rotated shape
     * @param float $phi_end_angle         the outer starting angle of
     *                      the rotated shape
     * @param float $outer_radius_x        the radius of the unrotated
     *                      outer width
     * @param float $outer_radius_y        the radius of the unrotated
     *                      outer height
     * @param float $inner_alpha           the axis rotation angle of
     *                      the inner arc. It may be different from
     *                      $alpha.
     * @param float $inner_phi_start_angle the starting angle of the
     *                      inner arc. This is associated with point P
     * @param float $inner_phi_end_angle   the ending angle of the
     *                      inner arc. This is associated with point Q
     * @param float $inner_radius_x        the radius of the unrotated
     *                      inner arc width
     * @param float $inner_radius_y        the radius of the unrotated
     *                      inner arc's height
     * @param float $outer_arc_x_max       The maximum x-value of the
     *                      rotated shape. It is half of the total
     *                      width-space of the rotated ellipse.
     * @param float $outer_arc_y_max       The maximum y-value of the
     *                      rotated shape. It is half of the total
     *                      height-space of the rotated ellipse.
     * @param bool  $outer_radii_swapped   true if the outer width and
     *                      height were swapped.
     * @param bool  $inner_radii_swapped   true if the inner width and
     *                      height were swapped.
     * @param bool  $trim_tips             true if the inner and outer
     *                      edges should be truncated.
     * @param bool  $x_grid_odd            true if the width-space
     *                      uses an odd number of pixels.
     * @param bool  $y_grid_odd            true if the height-space
     *                      uses an odd number of pixels.
     *
     * For consistency, some of the tchord parameters are determined
     * using the "unrotated" state of the shape eg when deciding if
     * the tchord line should be drawn for a particular shape. This
     * ensures that the shape will look similar no matter what angle
     * it is later rotated through. The unrotated state is taken as
     * the "base shape". Internal calculations for the unrotated state
     * are typically prefixed with "u_": for example, "$u_p_x" is the
     * x-value of the unrotated P intersection point.
     *
     * @return array  containing the following parameters:
     *        bool  $definite_t_inner_arc_intersect true if the tchord
     *                      intersects the inner arc
     *        bool  $draw_tchord_line      true if the tchord line
     *                      should be explicitly drawn
     *        bool  $tchord_tangential     true if the tchord is
     *                      considered tangential to the inner arc
     *        array $t_line_points         an array containing all the
     *                      x-y points that make up up the tchord
     *        float $tchord_line_slope     the slope of the tchord
     *                      line
     *        array $t_inner_arc_intersect an array containing the
     *                      points where the tchord approximately
     *                      intersected the inner arc
     *        float $smallest_t_x          the x-value corresponding
     *                      to the lowest vertical pixel of the tchord
     *        float $smallest_t_y          the y-value of the lowest
     *                      vertical pixel of the tchord
     *        float $largest_t_x           the x-value corresponding
     *                      to the highest vertical pixel of the
     *                      tchord
     *        float $largest_t_y           the y-value of the highest
     *                      vertical pixel of the tchord
     *        float $p_x                   the x-value of the P
     *                      intersection point of the tchord and the
     *                      inner arc that is closest to the starting
     *                      point of the tchord
     *        float $p_y                   the y-value of the P
     *                      intersection point of the tchord and the
     *                      inner arc that is closest to the starting
     *                      point of the tchord
     *        float $q_x                   the x-value of the Q
     *                      intersection point of the tchord and the
     *                      inner arc that is closest to the ending
     *                      point of the tchord
     *        float $q_y                   the y-value of the Q
     *                      intersection point of the tchord and the
     *                      inner arc that is closest to the ending
     *                      point of the tchord
     *        float $p_x_neighbour         the x-value of the pixel
     *                      that is just after point P
     *        float $p_y_neighbour         the y-value of the pixel
     *                      that is just after point P
     *        float $q_x_neighbour         the x-value of the pixel
     *                      that is just before point Q
     *        float $q_y_neighbour         the y-value of the pixel
     *                      that is just before point Q
     *        float $inner_phi_start_angle the angle that point P
     *                      makes with the origin
     *        float $inner_phi_end_angle   the angle that point Q
     *                      makes with the origin
     *        string$tchord_excl_side      the area of the shape that
     *                      is excluded because of the presence of the
     *                      tchord line
     *
     * @access private
     *
     */
    private function _getTChordParams(
        $alpha, $phi_start_angle, $phi_end_angle,
        $outer_radius_x, $outer_radius_y,
        $inner_alpha, $inner_phi_start_angle, $inner_phi_end_angle,
        $inner_radius_x, $inner_radius_y,
        $outer_arc_x_max, $outer_arc_y_max,
        $outer_radii_swapped,
        $inner_radii_swapped,
        $trim_tips,
        $x_grid_odd, $y_grid_odd
    ) {

        $definite_t_inner_arc_intersect = 0;
        $draw_tchord_line = 0;
        $tchord_tangential = 0;
        $t_line_points = array();
        $tchord_line_slope = null;
        $t_inner_arc_intersect = array();

        $smallest_t_x    = null;
        $smallest_t_y    = null;
        $largest_t_x    = null;
        $largest_t_y    = null;

        $p_x            = null;
        $p_y            = null;
        $q_x            = null;
        $q_y            = null;
        $p_x_neighbour    = null;
        $p_y_neighbour    = null;
        $q_x_neighbour    = null;
        $q_y_neighbour    = null;

        $tchord_excl_side = 'none';

        ////////////////////
        //
        // Get the $t_line_points details
        //

        list(
            $t_line_x_start,
            $t_line_y_start
        ) = $this->_getEllipseXYGivenPhi(
            $phi_start_angle, $alpha,
            $outer_radius_x, $outer_radius_y,
            1, $x_grid_odd,    $y_grid_odd
        );

        list(
            $t_line_x_end,
            $t_line_y_end
        ) = $this->_getEllipseXYGivenPhi(
            $phi_end_angle, $alpha,
            $outer_radius_x, $outer_radius_y,
            1, $x_grid_odd, $y_grid_odd
        );

        if ($t_line_y_start < $t_line_y_end) {
            $tchord_excl_side = 'exclude_left';
        } elseif ($t_line_y_start > $t_line_y_end) {
            $tchord_excl_side = 'exclude_right';
        } elseif ($t_line_y_start == $t_line_y_end) {
            if ($t_line_x_start < $t_line_x_end) {
                $tchord_excl_side = 'exclude_top';
            } elseif ($t_line_x_start > $t_line_x_end) {
                $tchord_excl_side = 'exclude_bottom';
            } elseif ($t_line_x_start == $t_line_x_end) {
                $tchord_excl_side = 'none';
            }
        }

        $y_grid_offset = ($y_grid_odd) ? 0 : 0.5;

        $t_line_points_all_x = $this->_getLinePointsAllXs(
            $t_line_x_start, $t_line_y_start,
            $t_line_x_end, $t_line_y_end,
            $x_grid_odd, $y_grid_odd
        );
        $line_slope = array_pop($t_line_points_all_x);
        $num_points = array_pop($t_line_points_all_x);
        $num_points_times_2 = $num_points * 2;

        for ($k = 0; $k < $num_points_times_2; $k += 2) {
            $t_y_keystr = (int)(
                $t_line_points_all_x[$k + 1]
                + $y_grid_offset
            );

            $t_line_points[$t_y_keystr][] = $t_line_points_all_x[$k];
        }

        $tchord_line_slope = $line_slope;

        $t_line_points_all_y = $this->_getLinePointsAllYs(
            $t_line_x_start, $t_line_y_start,
            $t_line_x_end, $t_line_y_end,
            $x_grid_odd, $y_grid_odd
        );
        $line_slope = array_pop($t_line_points_all_y);
        $num_points = array_pop($t_line_points_all_y);
        $num_points_times_2 = $num_points * 2;

        for ($k = 0; $k < $num_points_times_2; $k += 2) {
            $t_y_keystr = (int)(
                $t_line_points_all_y[$k + 1]
                + $y_grid_offset
            );

            if (array_key_exists($t_y_keystr, $t_line_points)) {
                //
                // A y-value already exists in $t_line_points.
                // Only add this new entry if it is unique
                //

                $unique_entry = 1;
                $new_x = $t_line_points_all_y[$k];
                $temp_array = $t_line_points[$t_y_keystr];
                foreach ($temp_array as $existing_x) {
                    if ($existing_x == $new_x) {
                        $unique_entry = 0;
                        break;
                    }
                }
                if ($unique_entry) {
                    $t_line_points[$t_y_keystr][] = $new_x;
                }
            } else {
                $t_line_points[$t_y_keystr][]
                    = $t_line_points_all_y[$k];
            }
        }

        //
        // Arrange the $t_line_points entries into a straight line
        //

        $t_ykeys_array = array_keys($t_line_points);
        if (sizeof($t_ykeys_array) > 0) {
            sort($t_ykeys_array);
            foreach ($t_ykeys_array as $t_ykey) {
                if ($line_slope < 0) {
                    rsort($t_line_points[$t_ykey]);
                } else {
                    sort($t_line_points[$t_ykey]);
                }

                if ($trim_tips) {
                    //
                    // Reduce the x-values if they are greater
                    // than the $outer_arc_x_max
                    //

                    $num_x = sizeof($t_line_points[$t_ykey]);

                    for ($k = 0; $k < $num_x; $k++) {
                        $x_sign = $this->_getSign(
                            $t_line_points[$t_ykey][$k]
                        );
                        $temp_x = abs($t_line_points[$t_ykey][$k]);

                        if ($temp_x >= $outer_arc_x_max) {
                            $t_line_points[$t_ykey][$k]
                                = $x_sign * ($temp_x - 1);
                        }
                    }
                }
            }
        }

        $t_ykeys_array = array_keys($t_line_points);
        if (sizeof($t_ykeys_array) > 0) {
            sort($t_ykeys_array);

            foreach ($t_ykeys_array as $t_ykey) {
                foreach ($t_line_points[$t_ykey] as $x_value) {
                    $t_intersects_inner_arc
                        = $this->_innerArcIntersectsT(
                            $x_value, $t_ykey, $inner_alpha,
                            $inner_radius_x, $inner_radius_y,
                            $x_grid_odd, $y_grid_odd
                        );

                    if ($t_intersects_inner_arc) {
                        $t_inner_arc_intersect[$t_ykey][] = $x_value;
                    }
                }
            }
        }

        if (sizeof($t_inner_arc_intersect) > 0) {
            $temp_y_array = array_keys($t_inner_arc_intersect);
            sort($temp_y_array);

            //
            // To find P, go through $t_inner_arc_intersect
            // from the beginning and get the last intersection that
            // is no more than 2 pixels away from the adjacent
            // intersection.
            //
            // Q is the next point after P, since it
            // is not adjacent to P
            //

            $p_y = $temp_y_array[0];
            $p_x = $t_inner_arc_intersect[$p_y][0];

            $q_y = $p_y;
            $q_x = $p_x;

            foreach ($temp_y_array as $next_p_y) {
                $temp_x_array = $t_inner_arc_intersect[$next_p_y];

                foreach ($temp_x_array as $next_p_x) {
                    $p_diff = ($next_p_x - $p_x) * ($next_p_x - $p_x)
                        + ($next_p_y - $p_y) * ($next_p_y - $p_y);

                    if ($p_diff < 2) {
                        //
                        // The points are close enough to be adjacent.
                        //

                        $p_y = $next_p_y;
                        $p_x = $next_p_x;
                    } else {
                        //
                        // This is the other end of the intersection.
                        // It is really where Q is.
                        //

                        $q_y = $next_p_y;
                        $q_x = $next_p_x;

                        break;
                    }
                }
            }
        } else {
            //
            // The tchord does not intersect the inner arc
            //

            $p_x = $t_line_x_start;
            $p_y = $t_line_y_start;

            $q_x = $t_line_x_end;
            $q_y = $t_line_y_end;
        }

        //
        // Apply the following logic to find out which of the
        // intersections is the starting point of the inner arc:
        //       Get their distances from the outer starting
        //     point.  The one with the shorter distance is
        //     always the starting point of the inner arc.
        //     Make it P and the other one Q
        //

        $dist_to_p = ($t_line_x_start - $p_x) * ($t_line_x_start - $p_x)
            + ($t_line_y_start - $p_y) * ($t_line_y_start - $p_y);
        $dist_to_q = ($t_line_x_start - $q_x) * ($t_line_x_start - $q_x)
            + ($t_line_y_start - $q_y) * ($t_line_y_start - $q_y);

        if ($dist_to_p > $dist_to_q) {
            //
            // q is closer to the starting point.  Swap them
            //

            $temp = $p_x;
            $p_x = $q_x;
            $q_x = $temp;

            $temp = $p_y;
            $p_y = $q_y;
            $q_y = $temp;
        }

        $inner_phi_start_angle = $this->_getAngle(0, 0, $p_x, $p_y);
        $inner_phi_end_angle = $this->_getAngle(0, 0, $q_x, $q_y);

        while ($inner_phi_end_angle <= $inner_phi_start_angle) {
            $inner_phi_end_angle+= 360;
        }

        if (! sizeof($t_inner_arc_intersect)) {
            //
            // There are no intersection points with the inner arc.
            // This means that the inner arc can potentially be
            // a full ellipse
            //

            $inner_phi_start_angle = 0;
            $inner_phi_end_angle = 360;
        }

        //
        // Get the top and bottom of the tchord line
        //

        $smallest_t_x    = $t_line_x_start;
        $smallest_t_y    = $t_line_y_start;
        $largest_t_x    = $t_line_x_end;
        $largest_t_y    = $t_line_y_end;

        if ($smallest_t_y > $largest_t_y) {
            $temp_y = $smallest_t_y;
            $smallest_t_y = $largest_t_y;
            $largest_t_y = $temp_y;

            $temp_x = $smallest_t_x;
            $smallest_t_x = $largest_t_x;
            $largest_t_x = $temp_x;
        }

        //
        // Get the pixels just between P and Q.
        //

        if ($p_y <= $q_y) {
            list($p_x_neighbour, $p_y_neighbour)
                = $this->_tChordPointAfter(
                    $p_x, $p_y, $largest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
            list($q_x_neighbour, $q_y_neighbour)
                = $this->_tChordPointBefore(
                    $q_x, $q_y, $smallest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
        } elseif ($p_y > $q_y) {
            list($p_x_neighbour, $p_y_neighbour)
                = $this->_tChordPointBefore(
                    $p_x, $p_y, $smallest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
            list($q_x_neighbour, $q_y_neighbour)
                = $this->_tChordPointAfter(
                    $q_x, $q_y, $largest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
        } elseif ($p_x < $q_x) {
            list($p_x_neighbour, $p_y_neighbour)
                = $this->_tChordPointAfter(
                    $p_x, $p_y, $largest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
            list($q_x_neighbour, $q_y_neighbour)
                = $this->_tChordPointBefore(
                    $q_x, $q_y, $smallest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
        } elseif ($p_x > $q_x) {
            list($p_x_neighbour, $p_y_neighbour)
                = $this->_tChordPointBefore(
                    $p_x, $p_y, $smallest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
            list($q_x_neighbour, $q_y_neighbour)
                = $this->_tChordPointAfter(
                    $q_x, $q_y, $largest_t_y, $t_line_points,
                    $x_grid_odd, $y_grid_odd
                );
        } else {
            $p_x_neighbour = $p_x;
            $p_y_neighbour = $p_y;
            $q_x_neighbour = $q_x;
            $q_y_neighbour = $q_y;
        }

        $definite_t_inner_arc_intersect
            = (sizeof($t_inner_arc_intersect) > 0)
            ? 1 : 0;

        //
        // Tchords have a notorious "now-you-see-it-now-you-dont"
        // quality which stems from the pixel and angle approximations.
        // The method below ensures that if a shape should have a tchord,
        // then it always will regardless of the rotation.
        //
        // (1) Assume that the outer ellipse is in the unrotated state.
        // (2) Find the tchord line equation
        // (3) Starting from 0 through to 360 degrees
        //     (a) Get a point on the inner ellipse
        //     (b) If the distance from that point to the tchord
        //         line is ever less than 2, then store these values in
        //         the $unrotated_intersect_points.
        //     (c) Take note of the largest positive and largest negative
        //         distances from the line.
        //         (1) If one of them is null, this means that the whole
        //             inner arc is on one side of the line), so there is
        //             no intersection
        //         (2) If one of them is less than 2, this means that the
        //             inner arc is tangential
        // (4) (a) If $unrotated_intersect_points == 0, then there is no
        //         intersection NO MATTER WHAT ROTATION THE SHAPE HAS.
        //         The tchord line will always be drawn.
        //     (b) If $default_intersect_points > 0, then go through
        //         consecutive entries of $unrotated_intersect_points to
        //         see if any adjacent pair have a distance > 2
        //         (1) If this pair is NOT found, then the tchord was
        //             tangential so every point was within 2 pixels of
        //             the next point.  The tchord line will be drawn
        //             NO MATTER WHAT ROTATION THE SHAPE HAS.
        //         (2) If this pair is found, then the tchord had a
        //             clear intersection.  The tchord line will NOT be
        //             drawn NO MATTER WHAT ROTATION THE SHAPE HAS.
        //         (3) If $default_intersect_points only contains one
        //             point, then it is tangential.  However we will
        //             only draw the tchord line IFF the sweep from P to
        //             Q is greater than 180 degrees.  Note that P and Q
        //             were calculated earlier in this function by the
        //             $t_inner_arc_intersect array.  They are not found
        //             by the method used below.
        //
        // This eliminates issues whereby rotation by a particular angle
        // suddenly causes the tchord line to appear: if it had no tchord
        // line at 0 degree rotation, then it never will.
        //
        // BTW, the "u_" prefix in the variables below means "Unrotated"
        //

        $u_alpha = $alpha;
        $u_outer_radius_x = $outer_radius_x;
        $u_outer_radius_y = $outer_radius_y;

        $u_inner_alpha = $u_alpha;
        $u_inner_radius_x = $inner_radius_x;
        $u_inner_radius_y = $inner_radius_y;

        $u_phi_start_angle = $phi_start_angle - $alpha;
        $u_phi_end_angle = $phi_end_angle - $alpha;

        if ($outer_radii_swapped) {

            $u_alpha -= 90;

            $u_inner_alpha = $u_alpha;
        }

        if ($inner_radii_swapped) {

            $u_inner_alpha -= 90;

            $temp_var = $u_inner_radius_x;
            $u_inner_radius_x = $u_inner_radius_y;
            $u_inner_radius_y = $temp_var;
        }

        list($unrotated_t_x_start, $unrotated_t_y_start)
            = $this->_getEllipseXYGivenPhi(
                $u_phi_start_angle, 0,
                $u_outer_radius_x, $u_outer_radius_y,
                0, $x_grid_odd, $y_grid_odd
            );

        list($unrotated_t_x_end, $unrotated_t_y_end)
            = $this->_getEllipseXYGivenPhi(
                $u_phi_end_angle, 0,
                $u_outer_radius_x, $u_outer_radius_y,
                0, $x_grid_odd, $y_grid_odd
            );

        $unrotated_intersect_points = array();

        list($line_x_coeff, $line_y_coeff, $line_c_coeff)
            = $this->_getLineEqnCoeffs(
                $unrotated_t_x_start, $unrotated_t_y_start,
                $unrotated_t_x_end, $unrotated_t_y_end
            );

        $positive_counter = 0;
        $negative_counter = 0;

        $largest_positive = null;
        $largest_negative = null;

        $u_changeover_points = array();

        $abovebelow_string="";

        for ($i = 0; $i <= 360; $i++) {
            list($temp_inner_x, $temp_inner_y)
                = $this->_getEllipseXYGivenPhi(
                    $i, 0,
                    $u_inner_radius_x, $u_inner_radius_y,
                    0, $x_grid_odd, $y_grid_odd
                );

            $dist_to_t = $this->_pointDistanceToLine(
                $temp_inner_x, $temp_inner_y,
                $line_x_coeff, $line_y_coeff, $line_c_coeff
            );

            $above_or_below_line =    $line_x_coeff * $temp_inner_x
                                    + $line_y_coeff * $temp_inner_y
                                    + $line_c_coeff;

            $line_y_for_x = ($line_y_coeff == 0)
                ? 10000
                : -1 * (
                        $line_x_coeff * $temp_inner_x + $line_c_coeff
                        ) / $line_y_coeff;

            if ($above_or_below_line < 0) {
                ++$negative_counter;
                $largest_negative = is_null($largest_negative)
                    ? abs($dist_to_t)
                    : max(abs($dist_to_t), $largest_negative);

                if ($abovebelow_string == "below") {
                    //
                    // The previous point was BELOW the line,
                    // while the current point is ABOVE the line.
                    // A changeover has occurred, so this is either
                    // the unrotated P or Q
                    //

                    $u_changeover_points[$temp_inner_y][] = $temp_inner_x;
                }

                $abovebelow_string="above";
            } elseif ($above_or_below_line > 0) {
                ++$positive_counter;
                $largest_positive = is_null($largest_positive)
                    ? abs($dist_to_t)
                    : max(abs($dist_to_t), $largest_positive);

                if ($abovebelow_string == "above") {
                    //
                    // The previous point was ABOVE the line,
                    // while the current point is BELOW the line.
                    // A changeover has occurred, so this is either
                    // the unrotated P or Q
                    //

                    $u_changeover_points[$temp_inner_y][] = $temp_inner_x;
                }

                $abovebelow_string="below";
            }

            if ($dist_to_t < 2) {
                $unrotated_intersect_points[$temp_inner_y][] = $temp_inner_x;
            }
        }

        $unrotated_p_x = null;
        $unrotated_p_y = null;
        $unrotated_q_x = null;
        $unrotated_q_y = null;

        foreach ($u_changeover_points as $u_y_key => $u_x_array) {
            foreach ($u_x_array as $u_x) {

                if (is_null($unrotated_p_x)) {
                    $unrotated_p_x = $u_x;
                    $unrotated_p_y = $u_y_key;
                } else {
                    $unrotated_q_x = $u_x;
                    $unrotated_q_y = $u_y_key;
                }
            }
        }
        if (is_null($unrotated_q_x)) {
            $unrotated_q_x = $unrotated_p_x;
            $unrotated_q_y = $unrotated_p_y;
        }

        $u_dist_to_p
            = ($unrotated_t_x_start - $unrotated_p_x)
            * ($unrotated_t_x_start - $unrotated_p_x)
            + ($unrotated_t_y_start - $unrotated_p_y)
            * ($unrotated_t_y_start - $unrotated_p_y);

        $u_dist_to_q
            = ($unrotated_t_x_start - $unrotated_q_x)
            * ($unrotated_t_x_start - $unrotated_q_x)
            + ($unrotated_t_y_start - $unrotated_q_y)
            * ($unrotated_t_y_start - $unrotated_q_y);

        if ($u_dist_to_p > $u_dist_to_q) {
            //
            // Q is closer to the starting point.  Swap them
            // so P is always closer to the starting point
            //

            $temp = $unrotated_p_x;
            $unrotated_p_x = $unrotated_q_x;
            $unrotated_q_x = $temp;

            $temp = $unrotated_p_y;
            $unrotated_p_y = $unrotated_q_y;
            $unrotated_q_y = $temp;
        }

        $inner_phi_start_angle = $this->_angleIn360(
            $alpha
            + $this->_getAngle(0, 0, $unrotated_p_x, $unrotated_p_y)
        );

        $inner_phi_end_angle = $this->_angleIn360(
            $alpha
            + $this->_getAngle(0, 0, $unrotated_q_x, $unrotated_q_y)
        );

        while ($inner_phi_end_angle <= $inner_phi_start_angle) {
            $inner_phi_end_angle+= 360;
        }

        $num_x_intersections = null;
        $num_y_intersections = sizeof($unrotated_intersect_points);

        if ($num_y_intersections == 1) {
            //
            // When there is only one y-value for
            // $unrotated_intersect_points, we need to check if there
            // are multiple x-values for this y-value.
            // If only one x-value exists, it means there is exactly
            // one point where the tchord was tangential with the
            // inner arc (scenario 4b(3))
            //

            $temp_y_keys = array_keys($unrotated_intersect_points);

            $num_x_intersections = sizeof(
                array_values(
                    $unrotated_intersect_points[$temp_y_keys[0]]
                )
            );
        }

        if (is_null($largest_negative)
            || is_null($largest_positive)
        ) {
            //
            // No intersection in the unrotated state; or the
            // intersection is tangential (scenario 3c)
            // We will draw the tchord line.
            //

            $draw_tchord_line = 1;
            $tchord_tangential = 0;
        } elseif (min($largest_positive, $largest_negative) < 2) {
            //
            // the intersection is tangential (scenario 3c)
            // We will draw the tchord line.
            //

            $draw_tchord_line = 1;
            $tchord_tangential = 1;
        } elseif (! sizeof($unrotated_intersect_points)) {
            //
            // No intersection in the unrotated state (scenario 4a)
            // We will draw the tchord line.
            //

            $draw_tchord_line = 1;
            $tchord_tangential = 0;
        } elseif (($num_y_intersections == 1)
            && ($num_x_intersections == 1)
        ) {
            //
            // It is tangential and only touches in one spot
            // Scenario 4b(3)
            //

            $draw_tchord_line = 1;

            $tchord_tangential = 1;
        } else {
            //
            // There were multiple intersections.
            // Assume that the tchord line will be drawn (scenario 4b(1)),
            // unless we can see that the points were not adjacent
            // ie scenario 4b(2), in which case we will not draw
            // the tchord line.
            //

            $draw_tchord_line = 1;
            $tchord_tangential = 1;

            $temp_u_y_array = array_keys($unrotated_intersect_points);
            sort($temp_u_y_array);

            $u_p_y = $temp_u_y_array[0];
            $u_p_x = $unrotated_intersect_points[$u_p_y][0];

            foreach ($temp_u_y_array as $next_u_p_y) {
                $temp_u_x_array
                    = $unrotated_intersect_points[$next_u_p_y];

                foreach ($temp_u_x_array as $next_u_p_x) {
                    $u_p_diff
                        = ($next_u_p_x - $u_p_x)
                        * ($next_u_p_x - $u_p_x)
                        + ($next_u_p_y - $u_p_y)
                        * ($next_u_p_y - $u_p_y);

                    if ($u_p_diff < 2) {
                        //
                        // The points are close enough to be adjacent.
                        //

                        $u_p_y = $next_u_p_y;
                        $u_p_x = $next_u_p_x;
                    } else {
                        //
                        // There is a clear break in the tchord.
                        // The tchord line will NOT be drawn
                        //

                        $draw_tchord_line = 0;
                        $tchord_tangential = 0;

                        break;
                    }
                }
            }
        }

        return array(
            $definite_t_inner_arc_intersect,
            $draw_tchord_line,
            $tchord_tangential,
            $t_line_points,
            $tchord_line_slope,
            $t_inner_arc_intersect,
            $smallest_t_x,
            $smallest_t_y,
            $largest_t_x,
            $largest_t_y,
            $p_x,
            $p_y,
            $q_x,
            $q_y,
            $p_x_neighbour,
            $p_y_neighbour,
            $q_x_neighbour,
            $q_y_neighbour,
            $inner_phi_start_angle,
            $inner_phi_end_angle,
            $tchord_excl_side,
        );
    }


    /**
     * Adjusts the input number to the nearest lower integer or
     * half-integer
     *
     * For an odd grid, the provided number should fit into the
     * nearest LOWER integer. The regular PHP (int) function achieves
     * this goal.
     *
     * For an even grid, the provided number should fit into the
     * nearest LOWER half-integer.
     *
     * The result must never be a higher (absolute) value than the
     * original number, the same way that (int) never returns a higher
     * absolute value than the supplied number.
     *
     * The following logic is used for even grids (as stated earlier,
     * odd grids just use the PHP (int)
     *
     * (1) Let a = the original number (2) Let b = int(a) (3) Let c =
     * a - b (4) Let d = _getSign(c) (5) Let e = abs(c) (6) If c <
     * 0.5, it means that the original number was definitely lower
     * than the upper limit, so deduct another 0.5 from "b" to get the
     * correct half-integer value. Therefore result = b - d*0.5 (7) If
     * c >= 0.5, it means that the original number was definitely
     * higher than the upper limit, so add another 0.5 to "b" to get
     * the correct half-integer value. Therefore result = b + d*0.5
     *
     * NOTES A "half-integer" is defined as the number half-way
     * between two integer values. For example, 12.5, 13.5, 22.5 are
     * all half-integers.
     *
     * A "grid" is defined by the number of pixels in the
     * [width]x[height] of a shape (1) a 20x20 circle has x and y
     * grids "even"; (2) a 31x31 circle has x and y grids "odd"; (3) a
     * 51x20 ellipse has x grid "odd" and y grid "even"; ("51" is an
     * odd number; "20" is an even number)
     *
     * Grids make it possible to detect the difference between a 20x20
     * circle and a 21x21 circle (the latter will be slightly larger,
     * and will probably have the pointy tips since the middle of the
     * circle is an explicit row/column in the circle's parameters.
     *
     * @param float $number      the number to process.
     * @param bool  $grid_is_odd a flag. It is true if the grid-type
     *                      is odd. ("true" also equals "1" which is
     *                      itself an odd number)
     *
     * @return  float $grid_result
     *
     * @access private
     *
     */
    private function _gridAdjust($number, $grid_is_odd)
    {
        //
        // Some key variables in this function:
        // float $grid_result the adjusted number.
        //

        if ($grid_is_odd) {
            //
            // For odd grids, you simply round
            // down to the nearest integer
            //

            $grid_result = (int) $number;
        } else {
            //
            // For even grids, you round down
            // down to the nearest ".5"
            // eg 13.8 -> 13.5
            //
            //

            $int_number = (int) $number;

            $diff = $number - $int_number;

            $sign_diff = $this->_getSign($diff);
            $abs_diff = abs($diff);

            if ($abs_diff < 0.5) {
                $grid_result = $int_number - $sign_diff * 0.5;
            } else {
                $grid_result = $int_number + $sign_diff * 0.5;
            }
        }

        return $grid_result;
    }


    /**
     * Determines where the tchord intersects the inner arc
     *
     * It inspects all the points on the tchord and sees which ones
     * give acceptable answers in the inner arc's ellipse equation. A
     * point only passes the test if the $ellipse_eqn value is
     * "0.95<$ellipse_eqn<1"
     *
     * It uses the following process
     *
     *     <ol>
     *       <li>
     * Find the distance of ($t_x,$t_y) from the origin. Store this as
     *      $t_o_length
     *       </li>
     *       <li>
     *
     * Find the distance of ($t_x,$t_y) from the major axis of the
     *      inner arc. It is perpendicular to the major axis. Store
     *      this as $unrotated_y
     *       </li>
     *       <li>
     *
     * Using Pythagoras, find the distance from the origin to the
     *      perpendicular intersection of $unrotated_y. Store this as
     *      $unrotated_x.
     *       </li>
     *       <li>
     *
     * $unrotated_x and $unrotated_y are the "unrotated" projection of
     *      ($t_x,$t_y) on the inner arc's ellipse; it is now as if
     *      the inner arc had not been rotated.
     *       </li>
     *       <li>
     *
     * Plug $unrotated_x and $unrotated_y into the ellipse equation
     *      for the inner arc. Store the result as $ellipse_eqn.
     *       </li>
     *       <li>
     *
     * If "0.95<$ellipse_eqn<1", then ($t_x,$t_y) lies on the inner
     *      ellipse.
     *       </li>
     *     </ol>
     *
     * @param float $t_x        the x-value of the tchord point being
     *                      checked
     * @param int   $t_y        the y-value of the tchord point being
     *                      checked. It is always an integer since it
     *                      is a key in the $t_line_points array
     * @param float $alpha      the axis rotation angle
     * @param float $ellipse_a  the semi-major axis of the unrotated
     *                      inner ellipse
     * @param float $ellipse_b  the semi-minor axis of the unrotated
     *                      inner ellipse
     * @param bool  $x_grid_odd a binary switch. If "1", then the
     *                      x-value from the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     * @param bool  $y_grid_odd a binary switch. If "1", then the
     *                      y-value from the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     *
     * To find the appropriate range for the $ellipse_eqn to be, this
     * function was tested on a 80x80 circle and 60x80 ellipse.
     *
     * Testing on a circle 80x80, with the tchord going from 45 to 225
     * degrees counterclockwise. DEBUG:
     * "test=ff0000=40=20=tchord,0,0,0,80,80,45,225|" The best
     * intersections were at (15,15) and (-15,-15)
     *  For BOTH intersection points:
     *    ------------------------------------------------------
     *      TChord Pixels     |  Value             Comment
     *    ------------------------------------------------------
     *      2 pixel(s) before    0.76643990929705  (too small)
     *      1 pixel(s) before    0.88888888888889  (too small)
     *      0 pixel(s)           1.0204081632653   (just right)
     *      1 pixel(s) after     1.1609977324263   (too large)
     *      2 pixel(s) after     1.3106575963719   (too large)
     *    ------------------------------------------------------
     *
     * DEBUG: "test=ff0000=40=20=tchord,0,0,0,80,80,45,225|" DEBUG:
     * "test=ff00ff=40=20=tchordflipvfliph,0,0,0,80,80,45,225|"
     *
     *
     * Testing on an ellipse 60x80, with the tchord going from -45 to
     * -125 degrees counterclockwise. DEBUG:
     * "myshape=0000ff=40=-10=tchord,30,0,0,60,80,-45,-125|" The best
     * intersections were (23,-14) and (5,-28)
     *  For (23,-14):
     *    ------------------------------------------------------
     *      TChord Pixels     |  Value             Comment
     *    ------------------------------------------------------
     *      2 pixel(s) before    0.85260544795973  (too small)
     *      1 pixel(s) before    0.90152935153667  (too small)
     *      0 pixel(s)           0.95919477865869  (just right)
     *      1 pixel(s) after     1.0364771235811   (too large)
     *      2 pixel(s) after     1.1078683315057   (too large)
     *    ------------------------------------------------------
     *
     *  For (5,-28):
     *    ------------------------------------------------------
     *      TChord Pixels     |  Value             Comment
     *    ------------------------------------------------------
     *      2 pixel(s) before    0.87745197230791  (too small)
     *      1 pixel(s) before    0.94837912304452  (too small)
     *      0 pixel(s)           0.95656298735608  (just right)
     *      1 pixel(s) after     1.0412159188952   (too large)
     *      2 pixel(s) after     1.1346103739795   (too large)
     *    ------------------------------------------------------
     *
     * Based on these results, a lower limit of "0.95" and an upper
     * limit of "1.05" will always catch the best pixel, and maybe the
     * pixel to its right or left. Even these right/left pixels should
     * be good enough for the shape.
     *
     * NOTE: This testing was done LONG BEFORE the "grid" system was
     * implemented. In many cases the grid system meant that pixels
     * could be selected with better precision. However the "0.95 to
     * 1.05" range still gave good results with the grids so this
     * range was not fine-tuned any further.
     *
     * @return  bool 1 if ($t_x,$t_y) lies on the inner arc's ellipse,
     *                      otherwise 0
     *
     * @access private
     *
     */
    private function _innerArcIntersectsT(
        $t_x, $t_y, $alpha,
        $ellipse_a, $ellipse_b,
        $x_grid_odd, $y_grid_odd
    ) {
        //
        // Some key variables in this function:
        // float $line_x_coeff the x coefficient of the line ax + by
        //                          + c = 0
        // float $line_y_coeff the y coefficient of the line ax + by
        //                          + c = 0
        // float $line_c_coeff the constant of the line ax + by + c =
        //                          0
        // float $t_o_length_sqr the square of the distance of
        //                          ($t_x,$t_y) from the origin.
        // float $unrotated_y_sqr the square of the distance of
        //                          ($t_x,$t_y) from the semi-major axis line.
        //                          If the ellipse had not been rotated, this
        //                          would also be the y-height of point
        //                          ($t_x,$t_y)
        // float $unrotated_x_sqr the square of the distance from the
        //                          origin to the intersection of ($t_x,$t_y)
        //                          and the semi-major axis line. If the
        //                          ellipse had not been rotated, this would
        //                          also be the x-width of point ($t_x,$t_y)
        // float $ellipse_eqn the result of putting
        //                          ($unrotated_x,$unrotated_y) into the
        //                          normal ellipse equation of the inner arc.
        //                          The value is tested to see if ($t_x,$t_y)
        //                          intersected the inner arc's ellipse.
        //

        $ellipse_a = ($x_grid_odd)
            ? (int)$ellipse_a : (int)($ellipse_a + 0.5);
        $ellipse_b = ($y_grid_odd)
            ? (int)$ellipse_b : (int)($ellipse_b + 0.5);

        if (($ellipse_a == 0) || ($ellipse_b == 0)) {
            //
            // Do not bother.  This shape is suspect and this problem
            // problem should have been picked up earlier in the code.
            //
            // It tends to happen if the thickness is larger than the
            // outer width/height
            //

            return 0;
        }

        list(
            $line_x_coeff,
            $line_y_coeff,
            $line_c_coeff
        ) = $this->_getLineEqnCoeffs(
            0, 0,
            $ellipse_a * cos(deg2rad($alpha)),
            $ellipse_a * sin(deg2rad($alpha))
        );

        if (abs($line_x_coeff) > 10000) {
            //
            // The tan is excessively large, so just approximate
            //

            $line_x_coeff = $this->_getSign($line_x_coeff) * 10000;
        }

        $unrotated_y_numerator = abs(
            $line_x_coeff * $t_x
            + $line_y_coeff * $t_y
            + $line_c_coeff
        );

        $unrotated_y_denom = sqrt(
            $line_x_coeff * $line_x_coeff
            + $line_y_coeff * $line_y_coeff
        );

        if ($unrotated_y_denom == 0) {
            // This is the origin itself.  The unrotated_y is zero
            $unrotated_y = 0;
        } else {
            $unrotated_y = $unrotated_y_numerator / $unrotated_y_denom;
        }

        $t_o_length_sqr = $t_x * $t_x + $t_y * $t_y;

        $t_o_length = sqrt($t_o_length_sqr);

        $unrotated_y_sqr = $unrotated_y * $unrotated_y;

        $unrotated_x_sqr = $t_o_length_sqr - $unrotated_y_sqr;
        $unrotated_x = sqrt($unrotated_x_sqr);

        $a_sqr = $ellipse_a * $ellipse_a;
        $b_sqr = $ellipse_b * $ellipse_b;

        $ellipse_eqn = ($unrotated_x_sqr / $a_sqr)
            + ($unrotated_y_sqr / $b_sqr);

        //
        // To find the appropriate range for the $ellipse_eqn to be,
        // I tested this function on a 80x80 circle and 60x80 ellipse.
        //
        // Testing on a circle 80x80, with the tchord going from
        //     45 to 225 degrees counterclockwise.
        // DEBUG: "myshape=ff0000=40=20=tchord,0,0,0,80,80,45,225|"
        // The best intersections were at (15,15) and (-15,-15)
        // For BOTH intersection points:
        // -------------------------------------
        //     TChord Pixels     |  Value             Comment
        //   ------------------------------------------------
        //     2 pixel(s) before    0.76643990929705  (too small)
        //     1 pixel(s) before    0.88888888888889  (too small)
        //     0 pixel(s)           1.0204081632653   (just right)
        //     1 pixel(s) after     1.1609977324263   (too large)
        //     2 pixel(s) after     1.3106575963719   (too large)
        // -------------------------------------
        //
        // DEBUG: "myshape=ff0000=40=20=tchord,0,0,0,80,80,45,225|"
        // DEBUG: "myshape=ff00ff=40=20=tchordflipvfliph,0,0,0,80,80,45,225|"
        //
        //
        // Testing on an ellipse 60x80, with the tchord going from
        //     -45 to -125 degrees counterclockwise.
        // DEBUG: "myshape=0000ff=40=-10=tchord,30,0,0,60,80,-45,-125|"
        // The best intersections were (23,-14) and (5,-28)
        // For (23,-14):
        // -------------------------------------
        //     TChord Pixels     |  Value             Comment
        //   ------------------------------------------------
        //     2 pixel(s) before    0.85260544795973  (too small)
        //     1 pixel(s) before    0.90152935153667  (too small)
        //     0 pixel(s)           0.95919477865869  (just right)
        //     1 pixel(s) after     1.0364771235811   (too large)
        //     2 pixel(s) after     1.1078683315057   (too large)
        // -------------------------------------
        //
        // For (5,-28):
        // -------------------------------------
        //     TChord Pixels     |  Value             Comment
        //   ------------------------------------------------
        //     2 pixel(s) before    0.87745197230791  (too small)
        //     1 pixel(s) before    0.94837912304452  (too small)
        //     0 pixel(s)           0.95656298735608  (just right)
        //     1 pixel(s) after     1.0412159188952   (too large)
        //     2 pixel(s) after     1.1346103739795   (too large)
        //
        //
        // Based on all these results, a lower limit of "0.95" and an
        // upper limit of "1.05" means that I will always catch the
        // best pixel, and maybe the pixel to its right or left.
        // Even these right/left pixels should be good enough
        // for the shape.
        //
        // NOTE: This testing was done LONG BEFORE I implemented the
        // "grid" system.  I found that in many cases the grid system
        // meant that pixels could be selected with better precision.
        // However I found that the "0.95 - 1.05" range still gave good
        // results with the grids so I have not bothered to fine-tune
        // this range any further.
        //

        return (($ellipse_eqn > 0.95) && ($ellipse_eqn < 1.05))
            ? 1 : 0;
    }


    /**
     * Finds the distance from a point to a given line
     *
     * It uses the following process
     *
     * For the distance from any point to a line, if the equation of
     * the line is of the form a*x + b*y + c=0, then the distance of
     * point (m,n) from the line is
     *
     * distance = (abs(a*m + b*n + c)) / sqrt(a^2 + b^2)
     *
     * @param float $point_x      the x-coordinate of the point
     * @param float $point_y      the y-coordinate of the point
     * @param float $line_x_coeff the x-coefficient of the line
     *                      equation.
     * @param float $line_y_coeff the y-coefficient of the line
     *                      equation.
     * @param float $line_c_coeff the constant of the line equation.
     *
     * @return  float the perpendicular distance from the point to the
     *                      line.
     *
     * @access private
     *
     */
    private function _pointDistanceToLine(
        $point_x, $point_y,
        $line_x_coeff, $line_y_coeff, $line_c_coeff
    ) {

        if (abs($line_x_coeff) > 10000) {
            //
            // The $line_x_coeff is excessively large, so just approximate
            //

            $line_x_coeff = $this->_getSign($line_x_coeff) * 10000;
        }

        $dist_numerator = abs(
            $line_x_coeff * $point_x
            + $line_y_coeff * $point_y
            + $line_c_coeff
        );

        $dist_denominator = sqrt(
            $line_x_coeff * $line_x_coeff
            + $line_y_coeff * $line_y_coeff
        );

        if ($dist_denominator == 0) {
            // This is the origin itself.  The distance is zero
            return 0;
        } else {
            return ($dist_numerator / $dist_denominator);
        }
    }


    /**
     * cleans up the supplied inputs and confirms that the desired
     * shape can be drawn successfully
     *
     * @param string $obj              the type of shape
     * @param float  $outer_x          the full width of the shape
     * @param float  $outer_y          the full height of the shape
     * @param int    $orig_thickness   the thickness. It can be either
     *                      a single number (for uniform thickness) or
     *                      a string containing two numbers separated
     *                      by a forward slash "/". The first number
     *                      is the thickness of the width and the
     *                      second number is the thickness of the
     *                      height. For either number, a negative
     *                      value means that the preference is for the
     *                      shape to be outlined instead of being
     *                      filled. The shape will only be outlined if
     *                      BOTH numbers are negative.
     * @param int    $height_thickness the thickness on the unrotated
     *                      y-axis. If supplied, overrides the height
     *                      that the $orig_thickness parameter would
     *                      have provided. It is a single number and
     *                      it only modifies the height.
     * @param float  $start_angle      the starting angle of the
     *                      unrotated shape
     * @param float  $end_angle        the ending angle of the
     *                      unrotated shape
     * @param float  $alpha            the axis rotation angle
     *
     * NOTE: For an unfilled (outlined) shape, both the
     * $orig_thickness and $height_thickness values must be negative
     * numbers.
     *

     *
     * NOTE: To make development easier, vertical shapes (shapes that
     * have their outer major axis along the y-axis) are internally
     * rotated through 90 degrees clockwise so the outer major axis is
     * always on the x-axis. The angles are also updated to reflect
     * this additional rotation.
     *
     * @return array  containing the following parameters:
     *        int    $_errors          the sum of the error codes.
     *        string $obj              the standardised type of shape
     *        float  $outer_x          the full revised width of the
     *                      shape
     *        float  $outer_y          the full revised height of the
     *                      shape
     *        int    $thickness_x      the width thickness
     *        int    $thickness_y      the height thickness
     *        float  $start_angle      the starting angle of the
     *                      revised shape
     *        float  $end_angle        the ending angle of the revised
     *                      shape
     *        float  $alpha            the axis of rotation
     *        bool   $outer_radii_swapped true if the width and height
     *                      were swapped
     *        bool   $filled           true if the shape should be
     *                      filled; false if it is outlined
     *        bool   $comp_check       true if the complement of the
     *                      shape should be drawn
     *        bool   $flipv_check      true if the shape is flipped
     *                      vertically
     *        bool   $fliph_check      true if the shape is flipped
     *                      horizontally
     *        bool   $trim_tips        true if the inner and outer
     *                      edges should be truncated.
     *        bool   $smooth_check     true if vertical isolated
     *                      pixels should be truncated
     *
     * @access private
     *
     */
    private function _sanitiseShapeInputs(
        $obj, $outer_x, $outer_y,
        $orig_thickness, $height_thickness,
        $start_angle, $end_angle, $alpha
    ) {
        //
        // Some key variables in this function:
        // int    $_errors          tracks any problems that were
        //                          encountered when processing the shape. An
        //                          error value of 8 or higher means that the
        //                          shape is invalid and will not be drawn.
        //

        //
        // Initialise some of the return parameters
        //

        $_errors = 0;
        $obj = strtolower(trim($obj));
        $thickness_x = null;
        $thickness_y = null;
        $outer_radii_swapped = 0;
        $filled = 0;
        $comp_check = 0;
        $flipv_check = 0;
        $fliph_check = 0;
        $trim_tips = 0;
        $smooth_check = 0;

        $temp_string = "zzsentinel" . $obj . "zzsentinel";

        if (strpos($obj, "trim") !== false) {
            //
            // Trim all edge pixels of the shape
            //

            $obj = str_replace("trim", "", $obj);
            $trim_tips = 1;
        }

        if (strpos($obj, "smooth") !== false) {
            //
            // Only trim y-pixels that are alone in the shape.
            //
            //

            $obj = str_replace("smooth", "", $obj);
            $smooth_check = 1;
        }

        if (strpos($obj, "flipv") !== false) {
            //
            // Flip the shape vertically
            //
            // An even number of "flipv" operations will cancel out
            //

            $temp_array = explode("flipv", $temp_string);
            $flipv_check = (sizeof($temp_array) + 1) % 2;
            $obj = str_replace("flipv", "", $obj);
        }

        if (strpos($obj, "fliph") !== false) {
            //
            // Flip the shape horizontally
            //
            // An even number of "fliph" operations will cancel out
            //

            $temp_array = explode("fliph", $temp_string);
            $fliph_check = (sizeof($temp_array) + 1) % 2;
            $obj = str_replace("fliph", "", $obj);
        }

        if (strpos($obj, "comp") !== false) {
            //
            // Draw the complement of a shape
            //
            // An even number of "comp" operations will cancel out
            //
            //

            $temp_array = explode("comp", $temp_string);
            $comp_check = (sizeof($temp_array) + 1) % 2;
            $obj = str_replace("comp", "", $obj);
        }

        //
        // Only sanitise approved shapes
        //
        // In each "case" below, the formal name of the shape is
        // always given first.  The other names under it (and before
        // the next formal name) are aliases for the shape.
        //

        switch ($obj) {
        case 'arc' :            // the formal name of an arc
        case 'arcchord' :        // the formal name of a chord.
        case 'arcc' :
        case 'chord' :
        case 'arcsegment' :        // the formal name of a segment.
        case 'arcs' :
        case 'pie' :
        case 'pieslice' :
        case 'segment' :
        case 'arctruechord' :    // the formal name of a tchord.
        case 'arct' :
        case 'arctchord' :
        case 'tchord' :
        case 'truechord' :
        case 'circle' :        // the formal name of a circle
        case 'ellipse' :    // the formal name of an ellipse

            //
            // Standardise the name of the shape
            //

            switch ($obj) {
            case 'arcchord' :
            case 'arcc' :
            case 'chord' :
                $obj = 'arcchord';
                break;

            case 'arcsegment' :
            case 'arcs' :
            case 'pie' :
            case 'pieslice' :
            case 'segment' :
                $obj = 'arcsegment';
                break;

            case 'arctruechord' :
            case 'arct' :
            case 'arctchord' :
            case 'tchord' :
            case 'truechord' :
                $obj = 'arctruechord';
                break;
            }

            //
            // Apply overrides for circles and ellipses
            //

            if (($obj == 'arc')
                && (!($end_angle - $start_angle) % 360)
            ) {
                //
                // This is a circle or ellipse.
                //

                $obj = ($outer_y - $outer_x) ? 'ellipse' : 'circle';
            }

            if (($obj == 'circle') || ($obj == 'ellipse')) {
                $outer_y = ($obj == 'ellipse')
                ? $outer_y
                : $outer_x;

                $start_angle = 0;
                $end_angle = 360;

                //
                // Note that $alpha is not set to zero, even if I
                // know that this is a circle.  This is because the
                // circle can have an ellipse as the inner arc.
                //
            }

            //
            // Get the outer lengths
            //

            $outer_x    = (int) abs($outer_x);
            $outer_y    = (int) abs($outer_y);

            //
            // Get the thicknesses
            //

            if (is_null($orig_thickness)) {
                $orig_thickness = "0";
            }
            if (! is_null($height_thickness)) {
                //
                // a specific height was defined.
                // Append it to the $orig_thickness
                // so it will be factored in.
                //

                $orig_thickness .= "/". $height_thickness;
            }

            $orig_thickness = trim($orig_thickness);
            $orig_thickness = preg_replace(
                "/^\/+/", "", $orig_thickness
            );
            $orig_thickness = preg_replace(
                "/\/+$/", "", $orig_thickness
            );

            $thickness_params = explode("/", $orig_thickness);

            $orig_thickness_x = (int)$thickness_params[0];
            $orig_thickness_y
                = (int)$thickness_params[sizeof($thickness_params) - 1];

            //
            // See if the shape is vertical or horizontal.
            // I find it too dizzying to keep track of what
            // a vertical ellipse does differently from a
            // normal ellipse, so I fix the parameters to
            // make the width always larger.
            //

            if ($outer_x < $outer_y) {
                //
                // This is a vertical ellipse.
                // Swap the width and height.
                //

                $outer_radii_swapped = 1;

                $temp_var = $outer_x;
                $outer_x = $outer_y;
                $outer_y = $temp_var;

                $start_angle -= 90;
                $end_angle -= 90;
                $alpha += 90;

                $temp_var = $orig_thickness_x;
                $orig_thickness_x = $orig_thickness_y;
                $orig_thickness_y = $temp_var;
            }

            $thickness_x = (int)abs($orig_thickness_x);
            $thickness_y = (int)abs($orig_thickness_y);

            //
            // Check if the shape should be filled
            //

            if ((($orig_thickness_x == 0) && ($orig_thickness_y == 0))
                || (($orig_thickness_x > 1) || ($orig_thickness_y > 1))
            ) {
                $filled = 1;
            }

            if (($thickness_x == 1) && ($thickness_y == 1)) {
                $filled = 0;
            }

            if (($thickness_x == 0) && ($thickness_y == 0)) {
                $filled = 1;
            }

            //
            // Check for bad input
            //

            if (($outer_x == 0) || ($outer_y == 0)) {
                //
                // The width or height is zero.
                // This shape is illegal.
                //

                $_errors += 8;
            }

            if (($outer_x <= ($thickness_x * 2))
                || ($outer_y <= ($thickness_y * 2))
            ) {
                //
                // The thickness is greater than the associated
                // width or height.
                // Draw the shape, but it is going to be completely
                // filled.
                //

                $thickness_x = 0;
                $thickness_y = 0;
                $_errors += 1;
            }

            if ($flipv_check) {
                // Flip the shape vertically

                $alpha = -$alpha;

                $temp_var = $start_angle;

                $start_angle = -$end_angle;
                $end_angle = -$temp_var;
            }
            if ($fliph_check) {
                // Flip the shape horizontally

                $alpha = 180 - $alpha;

                $temp_var = $start_angle;

                $start_angle = -$end_angle;
                $end_angle = -$temp_var;
            }
            if ($comp_check) {
                // Get the complement of the shape

                $temp_var = $start_angle;

                $start_angle = $end_angle;
                $end_angle = $temp_var;
            }

            //
            // Fix $start_angle and $start_angle so they are in
            // the 0 <= theta <= 360 range.
            // If necessary, increase the $end_angle to ensure
            // that it is always larger than $start_angle
            //

            $start_angle    = (int)($this->_angleIn360($start_angle));
            $end_angle    = (int)($this->_angleIn360($end_angle));

            while ($start_angle >= $end_angle) {
                $end_angle += 360;
            }

            $alpha    = (int)($this->_angleIn360($alpha));

            if ($obj == "arcsegment") {
                //
                // An arcsegment has a thickness equal to the outer
                // lengths.  Set the thicknesses to 0
                //

                $thickness_x = 0;
                $thickness_y = 0;
            }

            if (($orig_thickness_x == 0)
                && ($orig_thickness_y == 0)
            ) {
                //
                // The shape should be completely filled, so use the
                // origin as the inner arc's parameters
                //

                $thickness_x = 0;
                $thickness_y = 0;
            }

            break;

        default:
            $_errors += 128;
        }

        return array(
            $_errors,
            $obj,
            $outer_x,
            $outer_y,
            $thickness_x,
            $thickness_y,
            $start_angle,
            $end_angle,
            $alpha,
            $outer_radii_swapped,
            $filled,
            $comp_check,
            $flipv_check,
            $fliph_check,
            $trim_tips,
            $smooth_check,
        );
    }


    /**
     * Finds the next x-y point on the tchord which is just after the
     * point that was supplied as input
     *
     * Only tchords use this function
     *
     * The tchord line ALWAYS goes from the starting point to the
     * ending point of the outer arc. At the points where the tchord
     * intersects the inner arc (points "P" and "Q" respectively), the
     * pixel just after P ("P_neighbour") and the one just before Q
     * ("Q_neighbour") need to be known. P is always closer to the
     * starting point, and Q is always closer to the ending point.
     * Sometimes the direction of the tchord line means that the
     * positions of P and Q are swapped so the point before P and the
     * one after Q have to be found instead.
     *
     * _tChordPointAfter always finds the point AFTER whichever point
     * is supplied.
     *
     * In cases where the tchord line has to be drawn from P to Q, it
     * is visually better to take points from the tchord line instead
     * of trying to draw an approximate line between them by other
     * methods. This way the line looks smooth.
     *
     * @param float $t_x            the x-value of the supplied pixel
     * @param int   $t_y            the y-value of the supplied pixel
     * @param float $largest_t_y    the largest y-value of the tchord.
     *                      It may be for the starting point or the
     *                      ending point. The desired pixel's y-value
     *                      will never be greater than this value.
     * @param array &$t_line_points the array containing all the
     *                      points of the tchord.
     * @param bool  $x_grid_odd     a binary switch.
     * @param bool  $y_grid_odd     a binary switch. If "1", then the
     *                      y-value is used directly as the key in
     *                      $t_line_points. If "0", the y-value must
     *                      be incremented by "0.5" in order to match
     *                      the integer key in $t_line_points.
     *
     * @return array  containing the following parameters:
     *        int   $x_after
     *        int   $y_after
     *
     * @access private
     *
     */
    private function _tChordPointAfter(
        $t_x, $t_y, $largest_t_y, &$t_line_points,
        $x_grid_odd, $y_grid_odd
    ) {
        //
        // Some key variables in this function:
        // bool  $found          a binary switch. True when the
        //                          point AFTER ($t_x,$t_y) is found
        // int   $expecting_after a binary switch. True when
        //                          ($t_x,$t_y) has been found. It means that
        //                          very next pixel read from $t_line_points
        //                          will be the AFTER pixel.
        // float $x_after        the x-value of the AFTER pixel
        // int   $y_after        the y-value of the AFTER pixel
        //

        $found = 0;
        $expecting_after = 0;
        $x_after = $t_x;
        $y_after = $t_y;

        $y_grid_offset = ($y_grid_odd) ? 0 : 0.5;

        for ($y = $t_y; $y <= $largest_t_y; $y++) {
            if ($found) {
                break;
            } else {
                $row_y_keystr = (int)($y + $y_grid_offset);

                if (array_key_exists($row_y_keystr, $t_line_points)) {
                    foreach ($t_line_points[$row_y_keystr] as $x) {
                        if ($x == $t_x) {
                            $expecting_after = 1;
                        } elseif ($expecting_after) {
                            $x_after = $x;
                            $y_after = $y;
                            $expecting_p_after = 0;
                            $found = 1;
                            break;
                        }
                    }
                }
            }
        }

        return array($x_after, $y_after);
    }


    /**
     * Finds the next x-y point on the tchord which is just before the
     * point that was supplied as input
     *
     * Only tchords use this function
     *
     * The tchord line ALWAYS goes from the starting point to the
     * ending point of the outer arc. At the points where the tchord
     * intersects the inner arc (points "P" and "Q" respectively), the
     * pixel just after P ("P_neighbour") and the one just before Q
     * ("Q_neighbour") need to be known. P is always closer to the
     * starting point, and Q is always closer to the ending point.
     * Sometimes the direction of the tchord line means that the
     * positions of P and Q are swapped so the point before P and the
     * one after Q have to be found instead.
     *
     * _tChordPointBefore always finds the point BEFORE whichever
     * point is supplied.
     *
     * In cases where the tchord line has to be drawn from P to Q, it
     * is visually better to take points from the tchord line instead
     * of trying to draw an approximate line between them by other
     * methods. This way the line looks smooth.
     *
     * @param float $t_x            the x-value of the supplied pixel
     * @param int   $t_y            the y-value of the supplied pixel.
     *                      It is one of the integer keys of
     *                      $t_line_points
     * @param float $smallest_t_y   the smallest y-value of the
     *                      tchord. It may be for the starting point
     *                      or the ending point. The desired pixel's
     *                      y-value will never be less than this
     *                      value.
     * @param array &$t_line_points the array containing all the
     *                      points of the tchord.
     * @param bool  $x_grid_odd     a binary switch. If "1", then the
     *                      x-value from the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     * @param bool  $y_grid_odd     a binary switch. If "1", then the
     *                      y-value from the calculations will be
     *                      rounded down to the nearest integer. If
     *                      "0", it will be rounded to the nearest
     *                      half-integer. For example "0.5", "12.5",
     *                      "-6.5"
     *
     * @return array  containing the following parameters:
     *        int   $x_before
     *        int   $y_before
     *
     * @access private
     *
     */
    private function _tChordPointBefore(
        $t_x, $t_y, $smallest_t_y, &$t_line_points,
        $x_grid_odd, $y_grid_odd
    ) {
        //
        // Some key variables in this function:
        // bool  $found          a binary switch. True when the
        //                          point BEFORE ($t_x,$t_y) is found
        // float $x_before       the x-value of the BEFORE pixel
        // int   $y_before       the y-value of the BEFORE pixel
        //

        $found = 0;
        $x_before = $t_x;
        $y_before = $t_y;

        $y_grid_offset = ($y_grid_odd) ? 0 : 0.5;

        for ($y = $smallest_t_y; $y <= $t_y; $y++) {
            if ($found) {
                break;
            } else {

                $row_y_keystr = (int)($y + $y_grid_offset);

                if (array_key_exists($row_y_keystr, $t_line_points)) {
                    foreach ($t_line_points[$row_y_keystr] as $x) {
                        if (($y == $t_y) && ($x == $t_x)) {
                            $found = 1;
                            break;
                        }
                        $x_before = $x;
                    }
                }
                $y_before = $y;
            }
        }

        return array($x_before, $y_before);
    }


    /**
     * Returns the $_errors and $_shape parameters of the EllipseArc
     * object
     *
     * The report also includes a brief description of the types of
     * errors that were found in the shape and a conclusion of whether
     * or not the shape was drawn.
     *
     * NOTE: this function can only be used if the user manually
     * creates the EllipseArc object using the "new" operator since
     * the object is now persistent.
     *
     * If the shape was created by Image_EllipseArc_wrapper() then the
     * object is automatically deleted after creation so this function
     * can not be used.
     *
     * @return  string $obj_errors_text containing the following
     *                      parameters separated by the "|" character
     *
     *     <ul>
     *       <li>
     *     string $this->_shape
     *       </li>
     *       <li>
     *     int    $this->_errors
     *       </li>
     *       <li>
     *     string <descriptions of the error-types that were found>
     *       </li>
     *     </ul>
     *
     * @access public
     *
     */
    public function reportStatus()
    {
        $obj_errors_array = array(
            $this->_shape,
            $this->_errors,
        );

        $obj_errors = $this->_errors;

        $obj_errors_text = '';

        if (! $obj_errors) {
            $obj_errors_array[] = 'No errors - the shape is good.';
        } else {
            if ($obj_errors >= 128) {
                $obj_errors_array[]
                    = 'Not a valid shape; will not be drawn';
                $obj_errors -= 128;
            }
            if ($obj_errors >= 8) {
                $obj_errors_array[]
                    = 'The outer width and height must be non-zero';
                $obj_errors -= 8;
            }
            if ($obj_errors >= 1) {
                $obj_errors_array[]
                    = 'The thickness can not exceed the width or height';
            }
        }

        $obj_errors_text = implode('|', $obj_errors_array);

        return $obj_errors_text;
    }
}


/*
 * Local variables:
 * tab-width: 4
 * c-basic-offset: 4
 * c-hanging-comment-ender-p: nil
 * End:
 */

?>
