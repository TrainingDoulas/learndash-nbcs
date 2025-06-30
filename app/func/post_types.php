<?php 

namespace Doula_Course\App\Func;

use Doula_Course\App\Clss\Post_Types; 
use Doula_Course\App\Clss\Message; 

/*
	This is the file where we register our custom post types and set related parameters (such as post status) that we've created for the doula course. 
	
	Tracks and Materials are disabled in the LearnDash NBCS Plugin.
	
*/

if ( !defined( 'ABSPATH' ) ) { exit; }


/**
 *  Title: register_post_types
 *	
 *	Description: Using the Post_Types class this function creates custom post types (CPT) for our doula course plugin. 
 * 	
 *
 *	Returns NULL
 */	

function register_post_types()
{

	$assignment_args = [
		'post_type' => 'assignment',//
		'description' => 'Student submitted content.', 		//
		'hierarchical' => false,		//
		'exclude_from_search' => true, 	//
		'show_in_menu' => true,	//
		'menu_pos' => 35,			//
		'menu_icon' => 'edit-page',	//
		'supports' => array( 'title', 'editor', 'page-attributes', 'comments', 'revisions' ),		//
		'has_archive' => true,		//
	];

	$certificate_args = [
		'post_type' => 'certificate', 			//
		'description' => 'The certification document for students.', 		//
		'hierarchical' => false,		//
		'exclude_from_search' => false, 	//
		'show_in_menu' => true,	//
		'menu_pos' => 36,			//
		'menu_icon' => 'awards',	//
		'supports' => array( 'title', 'editor', 'page-attributes', 'comments', 'revisions' ),		//
		'has_archive' => false,		//
	];
	
	//Notifications CPT have been moved to the Nb_Notes plugin but is still dependent upon the PostTypes class defined in this plugin. 

	
	$post_types = [
		/* 'track' => $track_args,
		'material' => $material_args,
		'notification' => $notification_args, */
		'assignment' => $assignment_args,
		/*'certificate' => $certificate_args */
	];
	
	$results = [];
	foreach( $post_types as $type_key => $type_arg ){
		$type = new Post_Types( $type_arg );
		$results[] = register_post_type( $type->get_name(), $type->get_args() );
	}
		
}

add_action( 'init', 'Doula_Course\App\Func\register_post_types' ); 



/**
 *  revision_limit
 *	
 *	Limit the number of revisions for a given post type
 *	Presently, this function only limits assignemts to 5 revisions, 
 * 	but the code to easily be expanded to restrict other post types if needed. 
 * 	
 *
 *	Returns INT
 */	
 
function revision_limit( $num, $post ): INT
{ 
    
	return ( 'assignment' == $post->post_type )? 5 : $num;
    
}

add_filter( 'wp_revisions_to_keep', 'Doula_Course\App\Func\revision_limit', 10, 2 );




/**
 *  Title: cpt_shortlinks
 *	
 *	Description: Allow shortlinks to be retrieved for pages and custom post types
 * 	
 * 	ex: ?p=11234 which is the ID for the post type in the URL query parameter. 
 *
 *	Returns STRING
 */	
 
if( !function_exists( 'cpt_shortlinks' ) ) {

	function cpt_shortlinks( $shortlink, $id, $context, $allow_slugs=true ) {
		
		 //If query is the context, we probably shouldn't do anything
		if( 'query' == $context )
			return $shortlink;
		
		// If this is a standard post, return the shortlink that was already built
		$post = get_post( $id );
		if( 'post' == $post->post_type )
			return $shortlink;

		// Retrieve the array of publicly_queryable, non-built-in post types
		$post_types = get_post_types( array( 'public'   => true, '_builtin' => false ) );
		if( in_array( $post->post_type, $post_types ) || 'page' == $post->post_type )
			$shortlink = home_url('?p=' . $post->ID);

		return $shortlink;
	}
}
add_filter( 'get_shortlink', 'Doula_Course\App\Func\cpt_shortlinks', 10, 4 );
 



/**
 *  Title: asmt_comments
 *	
 *	Description: Messaging actions on comments submitted for assignments
 * 	
 *	Probably should NOT be in this file.
 *
 *	Returns 
 */	
 

function asmt_comments( $comment_id, $comment_obj ){	
	
	if( strcmp( get_post_type( $comment_obj->comment_post_ID ), 'assignment' ) == 0 ){
	
		//$msg = new Message();
		
		//$msg->comment_notify( $comment_obj );
		
	}
}

//add_action( 'wp_insert_comment', 'Doula_Course\App\Func\asmt_comments' ,10 ,2 );


/**
 *  Title: nb_assignment_comments_on
 *	
 *	Description: enables commenting status by default for assignment post types. 
 * 	
 *	Returns $data array
 */	
 


function nb_assignment_comments_on( $data ) {
    if( $data['post_type'] == 'assignment' )
        $data['comment_status'] = 'open';
 
    return $data;
}

add_filter( 'wp_insert_post_data', 'Doula_Course\App\Func\nb_assignment_comments_on' );

 
 
/**
 * 	edit_assignments_views
 *
 *	Filters the assignment views displayed to trainers from the assignments admin screen.
 *
 *	returns array
 **/
 


function edit_assignments_views( $views ) 
{
	$current_user = wp_get_current_user();
	$trainer = 	nb_get_current_trainer_id();

	$trainer_views = []; //final output of trainer views. 

	//First check if we are in another trainer's view and display notice if true. 
	if( ( strcmp( $current_user->ID, $trainer ) !== 0 ) && ( $trainer !== 0 ) ){

		$other_trainer = get_user_by( 'id', $trainer ); 
		$other_trainer_name = $other_trainer->first_name .' '. $other_trainer->last_name;
		$view_name =  "{$other_trainer_name}'s Students";
		$trainer_views[ 'notice' ] ="<span style='color: red; font-weight: bold;'>{$view_name}</span> <a href='edit.php?post_type=assignment&trainer=".$current_user->ID."&view=all_my_pending' title='Return to my students' >x</a>";
	}
		
	//If view all pending screen is loded with &trainer=0, revert back to current user for stats. 
	if( $trainer == 0 )//View all pending. 
		$trainer = $current_user->ID; 
	
	//Then proceed to build additional views depending on user.
	$my_asmts = nb_num_trainer_astms_stati( $trainer );
	
	$count_all_my_pending = $my_asmts[ 'submitted' ] + $my_asmts[ 'resubmitted' ];
	$count_all_my_graded = $my_asmts[ 'incomplete' ] + $my_asmts[ 'completed' ];

	$my_views = [ 'all_my_pending', 'my_submitted', 'my_resubmitted', 'all_my_graded' ]; 
	

	foreach( $my_views as $view ){
		
		$class = ( isset( $_GET[ 'view' ] ) )? 
			( isset( $_GET[ 'trainer' ] ) && ( $_GET[ 'trainer' ] == $trainer ) && ( $_GET[ 'view' ] == $view ) )?
				 'current' : 
				 '':
			''; 
		
		$count_name = "count_".$view;
		$count = $$count_name ?? $my_asmts[ str_replace( 'my_', '', $view) ];
		
		$trainer_views[ $view ] = '<a href="edit.php?post_type=assignment&trainer='.$trainer.'&view='.$view.'"  class="'.$class.'" >'.ucwords( str_replace( '_', ' ', $view ) ). " <span class='count'>($count)</span></a>"; 
	}
	
	//Adding an all pending view. 
	$all_asmts = nb_num_trainer_astms_stati( 0 );
	$count_all_pending = $all_asmts[ 'submitted' ] + $all_asmts[ 'resubmitted' ];
	$class_all_pending = ( isset( $_GET[ 'view' ] ) )? 
		( isset( $_GET[ 'trainer' ] ) && ( $_GET[ 'view' ] == 'all_pending' ) )? 
			'current' : 
			'' :
		'';

	$trainer_views[ 'all_pending' ] = "<a href='edit.php?post_type=assignment&trainer=0&view=all_pending' class='{$class_all_pending}' >All Pending <span class='count'>({$count_all_pending})</span></a>";


	$name_filters = [
		/* 'draft' => 'All Drafts', 
		'submitted' => 'All Submitted', 
		'incomplete' => 'All Incompletes', 
		'resubmitted' => 'All Resubmitted', 
		'completed' => 'All Completed',  */
		'all' => 'View All', 
		'trash' => 'Trashed', 
	];
	
	$views = nb_filter_assignment_view_names( $views, $name_filters ); 
	
	$views = array_merge( $trainer_views, $views ); 
	
    return $views;
}
 

add_filter( 'views_edit-assignment', 'Doula_Course\App\Func\edit_assignments_views', 10, 1 );



/*
* nb_filter_assignment_view_names
*
* This filters the names of the views displayed on the wp_admin/edit.php?post_type=assignment page. 
* It also stripes out default views if not included in the $names_filter list. 
*
* 
*/

function nb_filter_assignment_view_names( $views, $name_filters ){
	
	$filtered = [];

	foreach( $name_filters as $name => $filter ){
		if( isset( $views[ $name ] ) )
			$filtered[ $name ] = str_replace( ucfirst( $name ), $filter , $views[ $name ] ); 
	}
	
	return $filtered; 
}


/*
*  nb_get_trainers_students
*
*  Returns a numeric array of Student IDs assigned to trainer. User must have role of student to be displayed in the list. 
*
*
*	@param $trainer - (int) trainer ID
*/
function nb_get_trainers_students( int $trainer = 0 ){	

	//Get all users where role is student and user_meta is student_trainer = $trainer
	return ( $trainer !== 0 )?  
		get_users([
			'fields' => 'ID', 
			'role' => 'student', 
			'meta_key' => 'student_trainer',
			'meta_value' => $trainer
		]):
		get_users([
			'fields' => 'ID', 
			'role' => 'student'
		]); 
	
	
}

/*
*  nb_num_trainer_astms_stati
*
*  
*
*
*	@param $trainer - (int) trainer ID
*/
function nb_num_trainer_astms_stati( int $trainer = 0 ){	
	
	
	$students = nb_get_trainers_students( $trainer );  
	 
	//Then search through assignments by authors that pertain to this trainer. 
	$stati = [ 'submitted', 'resubmitted', 'incomplete', 'completed' ];
	$nums = []; 
	
	foreach( $stati as $status ){
		
		$asmts = get_posts( [ 
			'fields' => 'ids', 
			'post_type' => 'assignment', 
			'post_status' => $status, 
			'author__in' => $students, 
			'numberposts' => -1
		] );
			
		$nums[ $status ] = count( $asmts ); 
	}
	
	return $nums; 
	
}
	
/*
*	list_assignments_query_args
*	
*	
*
*/

function list_assignments_query_args( $query ){
	
	global $pagenow; 
	
	if( !is_admin() || !$query->is_main_query() )
		return; 
		
	if( ( strcmp( 'assignment', $query->get( 'post_type' ) ) == 0 )  && ( strcmp( 'edit.php', $pagenow ) == 0 )  ){

		$trainer = $_GET[ 'trainer' ] ?? 0;
		$view = $_GET[ 'view' ] ?? 'default'; 
		$student_id = $_GET[ 'student_id' ]?? 0;
		
		if( !empty( $student_id ) ){
			$query->set( 'author', $student_id );
			
		} else {
		
			//$my_views = [ 'all_my_pending', 'my_submitted', 'my_resubmitted', 'all_my_graded' ]; 
			switch( $view ){
				
				case 'all_my_pending':
				case 'all_pending':
					$status = [ 'submitted', 'resubmitted' ];
					break;
				case 'my_submitted':
					$status = 'submitted';
					break;
				case 'my_resubmitted':
					$status = 'resubmitted';
					break;
				case 'all_my_graded':
					$status = [ 'incomplete', 'completed' ];
					break;
					
			} 
			
			$students = nb_get_trainers_students( $trainer );  
			$query->set( 'author__in', $students );
			if( isset( $status ) )
				$query->set( 'post_status', $status );
			
			//print_pre( $query, "The main query" ); 

		}
	}
}

add_action( 'pre_get_posts', 'Doula_Course\App\Func\list_assignments_query_args', 10 ); 


/*
*	filter assignments by trainer
*	
*	
*
*/

function nb_assignment_trainers_dropdown( $post_type ){
    global $pagenow;

    if( ( strcmp( 'assignment', $post_type ) == 0 )  && ( strcmp( 'edit.php', $pagenow ) == 0 ) )  {
        $my_args = array(
            'show_option_all'   => 'All Trainers',
            'orderby'           => 'display_name',
            'order'             => 'ASC',
            'name'              => 'trainer',
            'role'              => 'trainer',
            'include_selected'  => true
        );

        if( isset( $_GET[ 'trainer' ] ) )
            $my_args[ 'selected' ] = (int) sanitize_text_field( $_GET[ 'trainer' ] );
        
        wp_dropdown_users( $my_args );
    }
}
add_action( 'restrict_manage_posts', 'Doula_Course\App\Func\nb_assignment_trainers_dropdown' ); 

?>