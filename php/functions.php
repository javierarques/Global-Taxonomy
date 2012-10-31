<?php
/**
 * Auxiliary functions
 */

/** Function : dump()
 * Arguments : $data - the variable that must be displayed
 * Prints a array, an object or a scalar variable in an easy to view format.
 */

function cmp_post_date ( $p1, $p2 ) {
	return ($p1->post_date < $p2->post_date);
}