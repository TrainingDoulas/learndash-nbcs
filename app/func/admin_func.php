<?php 

namespace Doula_Course\App\Func;

//To enable namespacing, add_actions need attention.

use Doula_Course\App\Clss\Admin_Page\Admin_Menu_Builder as Admin_Menu_Builder;
use Doula_Course\App\Clss\Admin_Page\Admin_Page_Director as Admin_Page_Director;
use Doula_Course\App\Clss\Admin_Page\Admin_Page_Builder as Admin_Page_Builder;

if ( !defined( 'ABSPATH' ) ) { exit; }

/**
 * These functions are only loaded on the admin pages. A check is in place to call to see if admin pages are being loaded. 
 */


/**
 * //DISABLED FOR BUG - 16 Feb 2023
 * Not Admin  - THIS CREATES A BUG or CONFLICT with RCP plugin registration page. Maybe I don't need this now?!
 * 
 * This filters out users that are not admins and sends them out of the admin area. 
 * 
 * return false
 */
 
function not_admin()
{
	if( is_user_logged_in() && !current_user_can( 'edit_posts' ) )
	{
			$site_url = site_url();
			wp_redirect($site_url); 
			exit; 
	}
	return false;
}

//add_action('init', 'Doula_Course\App\Func\not_admin'); 


/**
 * Build Admin Pages
 *
 * each subset features 4 or 5 variables as follows: 
 * string $title, string $slug, string $icon, int $position, string $cap = 'edit_users' , [submenu]
 *
 * return void
 */

function build_admin_menus(){
	global $menu, $submenu;
	
	$admin_menus = [
	
	
		[ 'Students Overview', 'students', 'heart', 34, 	// Student Overview Table 
			[
				['Add Student', 'new_student'],				// Add New Students
				['Location Search', 'student_location']		// Student Search By Location
			]
		],
		
		[ NULL, 											// Subpages that don't show up in main admin menu.
			[
				['Email Student', ''],						// Email an individual student
				['Edit Student', ''],						// Edit an individual student profile
				['Add New Transaction', 'add_transaction'],	// Add a trannsaction to a student profile
				['Edit Transaction', ''],					// Edit an existing transaction
				['Edit Grades', '']							// Grades Editor for an individual student. 
			]
		], 
		
		[ 'Code Sandbox', 'sandbox', 'hammer', 95, 	// Coding Sandbox Page. 
			[]												// Space for subpages if needed. 
		]
		/*, 
		[ '', '', '', '', 						//
			['', ''],							//
			['', ''],							//
		], */
		
	];
	
	$builder = new Admin_Menu_Builder();
	$builder->build( $admin_menus );
	
	/*--- NEXT SECTION ---*/
	
	//print_pre( $submenu, 'The Admin Sub Menu Before' );
	
	//If is trainer role: 
	$roles = nb_get_current_user_roles(); 
	if( in_array( 'trainer', $roles ) ){
		
		$trainer_id = get_current_user_id(); 

		$asmt_base_url = 'edit.php?post_type=assignment';
		
		$submenu[ $asmt_base_url ][ 5 ][ 0 ] = 'All Assignments'; 
		
		
		//second, intentionally out of order for use in the array_unshift foreach loop action. 
		$trainer_sub_menu[ 4 ] = [
			0 => 'My Graded Asmts',
			1 => 'edit_assignments',
			2 => $asmt_base_url .'&view=all_my_graded&trainer='.$trainer_id	,
		]; 
		
		$trainer_sub_menu[ 3 ] = [
			0 => 'All Pending',
			1 => 'edit_assignments',
			2 => $asmt_base_url .'&view=all_pending&trainer=0',
		]; 
		
		//first
		$trainer_sub_menu[ 2 ] = [
			0 => 'My Assignments',
			1 => 'edit_assignments',
			2 => $asmt_base_url .'&view=all_my_pending&trainer='.$trainer_id,
		]; 
		
		//note referenced sub_array. 
		$asmts_arr = &$submenu[ $asmt_base_url ];
		
		foreach( $trainer_sub_menu as $tsm )
			array_unshift( $asmts_arr, $tsm ); 
		
		//unset 'Add New' assignment link for trainers. 
		unset( $asmts_arr[ 4 ] );
		
		//unset menues for trainers
		
		$unset_menues = [ 'learndash-lms', 'edit.php', 'edit.php?post_type=portfolio' ]; 
		foreach( $unset_menues as $unset_string ){
			foreach( $menu as $menu_key => $menu_item ){
				if( array_search( $unset_string, $menu_item ) ) //Hide dashboard and Learndash from trainers?
					unset( $menu[ $menu_key ] );
			}
		}		
		
		//A little more clean up.
		//unset( $menu[ 4 ] ); //spacer
		unset( $menu[ 25 ] ); //comments
		
	}

	//print_pre( $submenu, 'The Admin Sub Menu After filtering, on line'.__LINE__.' in file '.__FILE__ );
}
 
 
add_action( 'admin_menu', 'Doula_Course\App\Func\build_admin_menus', 90 );

 
/**
 * filter_selected_asmt_submenu
 * 
 * This filters the submenus for the assignment CPT to highlight (class=current) for "my assignment" views. 
 * 
 * return $parent_file
 */


function filter_selected_asmt_submenu( $parent_file ){
    global $submenu_file;
	
    $roles = nb_get_current_user_roles(); 
	
	if( in_array( 'trainer', $roles ) ){
		if (isset($_GET['trainer']) && isset($_GET['view'])) 
			$submenu_file = 'edit.php?post_type=assignment&view=' . $_GET['view'] . '&trainer=' . $_GET['trainer'];
	}
	
    return $parent_file;
}

add_filter('parent_file', 'Doula_Course\App\Func\filter_selected_asmt_submenu');
 
/**
 * Render Admin Page
 * 
 * Builds the objects for admin page menu setup. 
 * 
 * return void
 */
 
function render_admin_page( string $slug, string $title ){
	
	$director = new Admin_Page_Director( $slug );
	$builder = new Admin_Page_Builder( $title);
	$director->set_builder( $builder );

	$director->build();	
	$builder->get_page()->render();
	
}

 
/**
 * Add Admin Menu Separator
 * 
 * This sets the code that will insert a new menu spacer in the admin menu. 
 * 
 * return void
 */

function add_admin_menu_separator( int $position ): VOID
{

	global $menu;
	$index = 0;

	foreach($menu as $offset => $section) {
		if ( substr( $section[ 2 ], 0, 9 ) == 'separator' )
			$index++;
		if ( $offset >= $position ) {
			$menu[ $position ] = array( '', 'read' ,"separator{$index}", '', 'wp-menu-separator' );
			break;
		}
	}

	ksort( $menu );
}



 
/**
 * Add Menu Spacers
 * 
 * This allows for the setting of multiple spacers in the admin menu. 
 *
 * NOTE: if the separator position is the same as the as an existing menu item, 
 * the existing menu item will be overridden. 
 * 
 * return void
 */

function admin_menu_spacers(): VOID
{

	add_admin_menu_separator( 29 );
	add_admin_menu_separator( 31 );
	add_admin_menu_separator( 41 );
	
}

add_action( 'admin_menu', 'Doula_Course\App\Func\admin_menu_spacers' );

/**
 * Add_Admin_Styles
 * 
 * This allows for the admin CSS to be added. 
 *
 * 
 * 
 * return void
 */


function nb_add_admin_style() {
    global $pagenow;     
		
	wp_register_style( 'nb_admin', plugin_dir_url( __DIR__ ) . 'tmpl/admin-styles.css', false, '1.0.0' );
	wp_enqueue_style( 'nb_admin' );

	if( strcmp( $pagenow, 'admin.php' ) === 0 
		&& isset( $_GET[ 'page' ] )  
		&& strcmp( $_REQUEST[ 'page' ], 'edit_student' ) === 0 )
		{
				
			wp_register_script( 'nb_admin_notes_script', plugin_dir_url( __DIR__ ) . 'tmpl/nb_admin_notes_script.js', array('jquery'), 1.0, true );
			wp_enqueue_script( 'nb_admin_notes_script' ); 
			
		}
		
}
add_action( 'admin_enqueue_scripts', 'Doula_Course\App\Func\nb_add_admin_style' );

/**
 * Render the editable trainer course quota table for admins.
 *
 * @param array $trainer_quotas  Array of course_id => quota for the trainer.
 * @param array $available_memberships  Array of all available memberships (membership_id => membership_name).
 */
function nbcs_render_trainer_quota_table_admin( $trainer_quotas, $available_memberships ) {
    // Build a lookup: membership_id => membership_title
    $id_to_title = [];
    foreach ($available_memberships as $membership) {
        $id_to_title[$membership['id']] = $membership['name'];
    }

    // Build trainer_memberships: each assigned membership with id, title, and quota
    $trainer_memberships = [];
    foreach ($trainer_quotas as $membership_id => $quota) {
        if (isset($id_to_title[$membership_id])) {
            $trainer_memberships[] = [
                'id'    => $membership_id,
                'title' => $id_to_title[$membership_id],
                'quota' => $quota,
            ];
        }
    }

    // Remove assigned memberships from available_memberships
    $assigned_membership_ids = array_keys($trainer_quotas);
    $unassigned_memberships = array_filter(
        $available_memberships,
        function($membership) use ($assigned_membership_ids) {
            return !in_array($membership['id'], $assigned_membership_ids);
        }
    );

    // Now use $trainer_memberships and $unassigned_memberships in your table rendering
    ?>
    <table class="widefat" style="max-width:600px;">
        <thead>
            <tr>
                <th>Membership</th>
                <th>Student Quota</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $trainer_memberships as $membership ) : ?>
                <tr>
                    <td>
                        <?php echo esc_html( $membership['title'] ); ?>
                        <input type="hidden" name="nb_trainer_course_quotas_courses[]" value="<?php echo esc_attr( $membership['id'] ); ?>">
                    </td>
                    <td>
                        <input type="number" name="nb_trainer_course_quotas_values[]" value="<?php echo esc_attr( $membership['quota'] ); ?>" min="0" />
                    </td>
                    <td>
                        <button type="button" class="button button-secondary nbcs-remove-quota-row">Remove</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            <!-- Row for adding a new membership/quota -->
            <tr>
                <td>
                    <select name="nbcs_new_course">
                        <option value="">-- Select Membership --</option>
                        <?php foreach ( $unassigned_memberships as $membership ) : ?>
                            <option value="<?php echo esc_attr( $membership['id'] ); ?>"><?php echo esc_html( $membership['title'] ); ?></option>
                        <?php endforeach; ?>
                    </select>*
                </td>
                <td>
                    <input type="number" name="nbcs_new_quota" value="" min="0" placeholder="Quota" />
                </td>
                <td>
                    <button type="button" class="button button-primary nbcs-add-quota-row">Add</button>
                </td>
            </tr>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3">
                    <sub>0 = no limit on number of students, * = required field</sub>
                </td>
            </tr>
        </tfoot>
    </table>
    <?php
}

// Get a list of LearnDash courses
/*function get_learndash_courses_list() {
    $args = array(
        'post_type'      => 'sfwd-courses',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => 'title',
        'order'          => 'ASC',
    );
    $courses = get_posts($args);

    $course_list = array();
    foreach ($courses as $course) {
        $course_list[] = array(
            'ID'    => $course->ID,
            'title' => get_the_title($course->ID),
            'link'  => get_permalink($course->ID),
        );
    }
    return $course_list;
}*/

/**
 * Show the trainer quota table on the user profile page (admin only).
 */
function nbcs_show_trainer_quota_table_on_profile( $user ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Get all available memberships
    $available_memberships = get_memberships_by_role( 'student');
    if ( ! is_array( $available_memberships ) ) {
        $available_memberships = [];
    }

    // Ensure available memberships are in the format id => title
    $available_memberships = array_map( function( $membership ) {
        return [
            'id'    => $membership['id'],
            'title' => $membership['name'],
        ];
    }, $available_memberships );

    // Filter out any memberships missing id or title
    $available_memberships = array_filter( $available_memberships, function( $membership ) {
        return ! empty( $membership['id'] ) && ! empty( $membership['title'] );
    } );
    
    // Ensure we have a valid format for the available memberships
    if ( empty( $available_memberships ) ) {
        echo '<p>No available memberships found.</p>';
        return;
    }
    // Get current quotas from user meta
    $trainer_quotas = get_user_meta( $user->ID, 'nb_trainer_course_quotas', true );
    if ( ! is_array( $trainer_quotas ) ) {
        $trainer_quotas = [];
    }

    echo '<h2>Trainer Membership Student Quotas</h2>';
    nbcs_render_trainer_quota_table_admin( $trainer_quotas, $available_memberships );
}
add_action( 'show_user_profile', 'Doula_Course\App\Func\nbcs_show_trainer_quota_table_on_profile' );
add_action( 'edit_user_profile', 'Doula_Course\App\Func\nbcs_show_trainer_quota_table_on_profile' );

/**
 * Save the trainer quotas when the user profile is updated.
 */
function nbcs_save_trainer_quota_table_on_profile( $user_id ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Prepare new quotas array
    $quotas = [];
    if ( isset( $_POST['nb_trainer_course_quotas_courses'], $_POST['nb_trainer_course_quotas_values'] ) ) {
        $courses = (array) $_POST['nb_trainer_course_quotas_courses'];
        $values  = (array) $_POST['nb_trainer_course_quotas_values'];
        foreach ( $courses as $i => $course_id ) {
            $quota = isset( $values[$i] ) ? intval( $values[$i] ) : 0;
            $quotas[ sanitize_text_field( $course_id ) ] = $quota;
        }
    }

    // Handle new course/quota addition
    if ( ! empty( $_POST['nbcs_new_course'] ) && isset( $_POST['nbcs_new_quota'] ) ) {
        $new_course = sanitize_text_field( $_POST['nbcs_new_course'] );
        $new_quota  = intval( $_POST['nbcs_new_quota'] );
        $quotas[ $new_course ] = $new_quota;
    }

    update_user_meta( $user_id, 'nb_trainer_course_quotas', $quotas );
}
add_action( 'personal_options_update', 'Doula_Course\App\Func\nbcs_save_trainer_quota_table_on_profile' );
add_action( 'edit_user_profile_update', 'Doula_Course\App\Func\nbcs_save_trainer_quota_table_on_profile' );

/**
 * Get memberships by user role
 *
 * This function retrieves all active membership levels and filters them by a specific user role if provided.
 *
 * @param string|null $target_role The user role to filter memberships by. If null, all memberships are returned.
 * @return array An array of membership details.
 */
function get_memberships_by_role($target_role = null) {
    // Get all active membership levels
    $memberships = rcp_get_membership_levels(array(
        'status' => 'active'
    ));
    
    $membership_array = array();
    
    foreach ($memberships as $membership) {
        $membership_role = $membership->role;
        
        // If a specific role is requested, filter by it
        if ($target_role && $membership_role !== $target_role) {
            continue;
        }
        
        $membership_array[] = array(
            'id' => $membership->id,
            'name' => $membership->name,
            'role' => $membership_role,
        );
    }
    
    return $membership_array;
}

?>
