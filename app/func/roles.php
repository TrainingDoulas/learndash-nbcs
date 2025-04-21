<?php

namespace Doula_Course\App\Func;

use Doula_Course\App\Clss\Roles;
use Doula_Course\App\Clss\Capabilities;

if ( !defined( 'ABSPATH' ) ) { exit; }


function add_roles( ){
	
	$roles = new Roles(
		[
			'trainer' => [
				'edit_posts',
				'edit_users',
				'list_users',
				'read',
				'student',
				'upload_files',
				'read_assignment',
				'edit_assignment',
				'delete_assignment',
				'publish_assignment',
				'edit_published_assignment',
				'read_assignments',
				'edit_assignments',
				'edit_others_assignments',
				'publish_assignments',
				'edit_published_assignments',
				'edit_private_assignments',
				'delete_assignments',
				'read_certificates',
				'edit_certificates',
				'edit_others_certificates',
				'publish_certificates',
				'edit_published_certificates',
				'edit_private_certificates',
				'delete_certificates',
				'read_notifications',
				'edit_notifications',
				'edit_others_notifications',
				'publish_notifications',
				'edit_published_notifications',
				'edit_private_notifications',
				'delete_notifications',
			],
			'student' => [
				'read',
				'student',
				'upload_files',
				'edit_assignments',
				'delete_assignments',
				'publish_assignments',
				'edit_published_assignments',
			],
			'alumnus' => [
				'read',	
				'upload_files',
				'edit_assignments',
				'delete_assignments',
				'publish_assignments',
				'edit_published_assignments',
			],
			'reader' => [
				'read',
				
			],
			'inactive' => [
				'inactive',
			],
		]
	);

	$roles->build();
	
	foreach( $roles->get_roles() as $role ){
		
		add_role( $role['name'], $role['u_name'], $role['caps'] );
	}
	
	//Move all WP functionality into this function, keep out of class. 
}

function add_caps( ){
	
	//this is a little lazy, sending the whole args array when you only need to two values. 
	$caps = new Capabilities([
		'assignment',	
		'certificate',
		'message',
		
	]);

	$admin = get_role( 'administrator' );

	foreach( $caps->get_caps() as $cap ){
		if( !$admin->has_cap( $cap ) )
			$admin->add_cap( $cap );
	}	
	
}


function remove_roles( ){
	
	$roles = [
		'trainer',
		'student',
		'alumnus',
		'reader',
		'inactive',
	];
	
	foreach( $roles as $role ){
		
		remove_role( $role );
	}
	
	//exit( print_pre( $roles ) );
}


function remove_caps( ){
	
	
	$caps = new Capabilities([
		'assignment',	
		'certificate',
		'message',
	]);

	$admin = get_role( 'administrator' );
	
	$results = [];
	
	foreach( $caps->get_caps() as $cap ){
		if( $admin->has_cap( $cap ) ){
			$admin->remove_cap( $cap );
			$results[] = $cap.' removed.';
		}
			
	}	
	
	//exit( print_pre( $results ) );
	
}

add_action( 'doula_course_activate',  'Doula_Course\App\Func\add_roles' );
//add_action( 'doula_course_activate',  'Doula_Course\App\Func\add_caps' );
add_action( 'doula_course_deactivate',  'Doula_Course\App\Func\remove_roles' );
//add_action( 'doula_course_deactivate',  'Doula_Course\App\Func\remove_caps' );




/*
*  Sets the default role to inactive when all roles are removed by Restrict Content Pro
*
*/

function nb_set_inactive_when_role_not_set( $old_status, $new_status, $membership_id ) {
    // Get the membership
	$membership = rcp_get_membership( $membership_id );

	// Get the user
    $user = new \WP_User($membership->get_user_id());
	rcp_log( sprintf( 'Setting role to inactive for user #%d', $user->ID )); 
	
    // Check if the user has no roles
    if( empty( $user->roles ) ){
		rcp_log( "User's roles are empty! Proceeding to update." ); 
		
		// Add the 'Inactive' role
       	$user->add_role( 'inactive' );  // replace 'Inactive' with the role you want to assign

    } else {
		rcp_log( "The user's role was not empty, so it was not updated." );

	}
}

/**
 * Action "rcp_transition_membership_status" will run.
 *
 * @see   \RCP\Database\Query::transition_item()
 *
 * @param string $old_status    Old membership status.
 * @param string $new_status    New membership status.
 * @param int    $membership_id ID of the membership.
 *
 * @since 3.0
 */

add_action( 'rcp_transition_membership_status', 'Doula_Course\App\Func\nb_set_inactive_when_role_not_set', 20, 3 );


/*
*  Sends Office a notice when student role goes from "inactive" to "student" 
*
*/

function nb_reactivated_status( $old_status, $new_status, $membership_id ) {
    // Get the membership
	$membership = rcp_get_membership( $membership_id );

	// Get the user
    $user = new \WP_User($membership->get_user_id());

	//check to make sure this is not a new user registration. 
	if( date( 'Y-m-d', strtotime( $user->user_registered ) ) == date( 'Y-m-d' ) )
		return; 

	$old_stati = ['expired', 'cancelled', 'pending'];
	
	if( in_array( $old_status, $old_stati )  && ( strcmp( $new_status, 'active' ) == 0 ) ){
		
		rcp_log( "The users's role for {$user->first_name} {$user->last_name} (id:{$user->ID}) has been reactivated so we need to send an admin notice." );
		// The status has changed to 'active', send an email
        $to = 'office@trainingdoulas.com';
        $subject = "Student Account Reactivated for {$user->first_name} {$user->last_name} (ID:{$user->ID})";
        $message = "The subscription for {$user->first_name} {$user->last_name} (id:{$user->ID}) has been REACTIVATED! (Please make any adjustments to other student records as needed.)";
        wp_mail( $to, $subject, $message );

	}

}


add_action( 'rcp_transition_membership_status', 'Doula_Course\App\Func\nb_reactivated_status', 20, 3 );

//restrict student access to media gallery.
add_filter( 'ajax_query_attachments_args', 'Doula_Course\App\Func\nb_user_show_attachments' );
 
function nb_user_show_attachments( $query ) {
    $user_id = get_current_user_id();
    if ( $user_id && !current_user_can('activate_plugins') && !current_user_can('edit_others_posts
') ) {
        $query['author'] = $user_id;
    }
    return $query;
} 

?>