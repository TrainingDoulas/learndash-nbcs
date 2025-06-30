<?php

namespace Doula_Course\App\Func;

if ( !defined( 'ABSPATH' ) ) { exit; }


/**
 *	Updated on 22 Aug 2022	
 *
 *	This file action hooks to interface with learndash course actions
 *
 * 	NOTE:
 *
 **/

 

/*-----------------*/
//A function to get all the meta data for the student to display and manipulate. 
function get_student_meta(){
	global $current_user;
	return ( $current_user->ID != null)? get_userdata( $current_user->ID ):NULL; 
	
}
 



/**
 *	assign_student_trainer
 *
 *  This plugin may require additional filtering based on the type of membership being created.
 *	For example, no trainer should be assigned to reader accounts. 
 *
 *	do_action: 'rcp_membership_post_activate' in plugins\restrict-content-pro\core\includes\memberships\membership-functions.php
 *	
 * @param int          $membership_id      Membership ID, not needed. 
 * @param arr          $data		       Data, $data[ 'user_id' ] is needed value. 
 *	
	Membership Data:

		array (
		  'customer_id' => '1',
		  'user_id' => '15',
		  'object_id' => 1,
		  'object_type' => 'membership',
		  'currency' => 'USD',
		  'initial_amount' => '0.00',
		  'recurring_amount' => '0.00',
		  'created_date' => '2022-10-07 00:00:00',
		  'expiration_date' => '2022-10-08 23:59:59',
		  'auto_renew' => 0,
		  'times_billed' => 0,
		  'maximum_renewals' => 0,
		  'status' => 'active',
		  'signup_method' => 'manual',
		  'disabled' => 0,
		  'gateway_customer_id' => '',
		  'gateway_subscription_id' => '',
		  'gateway' => 'manual',
		  'trial_end_date' => '2022-10-08 23:59:59',
		)

 *	
 * 	return 
 */

function assign_student_trainer( $membership_id, $data ){
	
	//Check if membership object. 
	if( strcmp( $data->get_object_type(), 'membership' ) !== 0 ) return;

	$student_id = $data->get_user_id();

	//If the user is not a student, then we do not need to assign them a trainer. 
	$user = get_user_by( 'id', $student_id ); 
	if( !in_array( 'student', (array) $user->roles ) ) return; 

	$trainers = nb_get_trainers();
	
	$last_trainer_id = get_option( 'last_trainer_assigned' );
	$trainer_ids = array_keys( $trainers ); 
	$next = array_search( $last_trainer_id ,$trainer_ids ) + 1;
	
	//0 index is no trainer ID, so reset goes to 1. 
	$next_trainer_id = ( count( $trainer_ids ) <= $next ) ? $trainer_ids[ 1 ] : $trainer_ids[ $next ];

	$updated = ( empty( get_user_meta( $student_id, 'student_trainer', true ) ) ) ?
					add_user_meta( $student_id, 'student_trainer', $next_trainer_id ):
					false ; 
	
	if( $updated !== false )
	{
		update_option( 'last_trainer_assigned', $next_trainer_id ); 
		do_action( 'nb_trainer_new_student', $student_id,  $next_trainer_id ); 
	}
}

add_action( 'rcp_membership_post_activate', 'Doula_Course\App\Func\assign_student_trainer', 10 , 2 );  




/**
 *  This adds an admin note to the student's file. 
 *	
 * @param 	int   	$student_id     User ID 
 * @param 	string  $note			Message to be recorded in the admin notes. 	     
 * @param 	string  $soure			ID of user/trainer who made the note. Default is 0 for system generated. 		
 *	
 * @return 	boolean
 */

function add_admin_student_note( $student_id, $note, $source = 0 )
{

	//Calls the classes that have be created to handle and process Admin_Notes. 

	//Returns true or false based on successful insertion into Admin Notes table. 

	return false; 
}



?>