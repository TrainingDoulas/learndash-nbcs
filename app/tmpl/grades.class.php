<?php 

namespace Doula_Course\App\Clss\Grades;

//use Doula_Course\App\Clss\Interfaces\Assignment;


/*
 *  
 *	Updated 3 Feb 2022
 *
 *	Grades (class)
 *	
 *	This is a master class that handles all the assignment and grade related activities. 
 *	This can be used to generate reports about a students progress. 
 *
 *	UPGRADE: This is going to require a database update of the 'student_grades' metadata. New metadata for
 *	'student_grades' will be decoupled from the certificate, as completed assignments may be credited
 *	to multiple certificate tracks. 
 *	
 *
 */
 

if ( ! defined( 'ABSPATH' ) ) { exit; }
 
 
if( !class_exists( 'Grades' ) ){ 
	class Grades { 

		
		private $student_id = 0; 
		
		private $raw_data; //Storage for incoming meta_data. 
		
		private $assignments_map; //This is a object/class that loads the structure of course assignments. 
		
		private $tracks = []; //An array of all Track IDs (both active and inactive). 
		
		private $inactive_tracks = []; //An array of inactive tracks. 

		private $grades = []; //an array of "grade" objects. 
		
		//Then Methods

		/**
		 * _construct
		 *
		 * @since 0.1
		 **/
		public function __construct( ){
			
			
			
		}
		
		


		/**
		 * 	build
		 *
		 * @since 0.1
		 **/
		 
		public function build( int $student_id = 0 ):VOID
		{
			
			//Must set Student ID first, as assignments class will look for it before returning all.
			$this->student_id = $student_id;
			
			$this->set_assignments_map( );
			
			//Student ID is required for us to find the student's grades...
			if( $this->student_id > 0 ){
							
				$this->set_tracks();
				
				$this->set_assignments();
				
				$this->set_grades();
			}
				
			
		}

		
		
		
		/**
		 * 	set_assignments_map
		 *
		 *	(descrip)
		 *
		 *	returns VOID
		 **/
		 
		private function set_assignments_map( ): VOID
		{
			
			$this->assignments_map = new Assignments_Map();
			
			//load assignments object properties. 
			$this->assignments_map->build();
		}
			

		
		
		/**
		 * 	set_tracks
		 *
		 *	Set tracks to be used in building this assignmets_map object. 
		 *	Varies based on user. Default is no user: student_id = 0. 
		 *
		 *	returns VOID
		 **/
		 
		private function set_tracks(  ): VOID
		{
			if( $this->student_id > 0 ){
				$active_tracks = get_user_meta( $this->student_id, 'student_tracks', true );
			
				if( !empty( $active_tracks ) ){
					
					//Invert the structure of the array to move values to key positions for array_intersect_key filtering. 
					foreach( $active_tracks as $track )
						$this->tracks[ $track ] = 0; 
						
					//Find and filter out inactive tracks: 
					$ld_tracks = []; 
					$ld_progress = get_user_meta( $this->student_id, '_sfwd-course_progress', true ); 
					
					//List all course with student course progress. 
					if( !empty( $ld_progress ) ){
						foreach( $ld_progress as $course_id => $course_arr )
							$ld_tracks[ $course_id ] = 0;
					}
					

					//Filter out inactive courses: 
					$this->inactive_tracks = array_diff_key( $ld_tracks, $this->tracks ); 
					
					//Then combine all courses, both active and inactive. 
					$this->tracks = $this->tracks + $this->inactive_tracks; 
					
				}
			}
		}

			
		
		/**
		 * 	set_assignments
		 *
		 *	pulls assignments and adds them to the assignment_map property. 
		 *
		 *	returns VOID
		 **/
		 
		private function set_assignments(  ): VOID
		{
			//Get active tracks
			$this->assignments_map->set_assignments( $this->tracks );
			
		}
			
			
		
		/**
		 * 	set_grades
		 *
		 *	pulls grades from database. The database only has 4 values for each grade: 
		 *  Parent_ID, ID, Status, and Date_submitted. 
		 *
		 *	returns VOID
		 **/
		 
		private function set_grades(  ): VOID
		{
			
			if( metadata_exists( 'user', $this->student_id, 'student_grades' ) ) {	
				$this->raw_data = get_user_meta( $this->student_id, 'student_grades', true ); 
				$data = $this->parse_meta( $this->raw_data );
			
				foreach( $data as $arr ){
					$grade = new Grade();
					$grade->build( $arr );
					$this->grades[ $grade->get_material_id() ] = $grade;
				} 
			}
		}
			

		
		
		/**
		 * 	parse_meta
		 *
		 *	Reformat data stored in user_meta table 'student_grades' for use in the Grade class. 
		 *
		 *	returns ARRAY
		 **/
		 
		private function parse_meta( $data ): ARRAY
		{
			$array = maybe_unserialize( $data );
			$meta_array = [];
			
			foreach( $array as $id => $asmt ){
				
				$meta_array[] = [ 
					'material_id' 		=> $id,
					'assignment_id'		=> $asmt[ 0 ], 	//parent_id or material_id
					'status' 			=> $asmt[ 1 ],	//assigment_status
					'instructor_status'	=> $asmt[ 2 ],	//instructor_status of assignment
					'submission_date'	=> $asmt[ 3 ],	//original submission date
					'last_updated'		=> $asmt[ 4 ],	//last_updated	
				];
			}
			
			//print_pre( $meta_array, "PARSE_META, LINE: ".__LINE__ ); 
			return $meta_array;
		}

		
		
		
		
		/**
		 * 	add_grade
		 *
		 * 
		 * 
		 * 
		 **/
		 
		public function add_grade(  array $args, int $assignment_id = 0 ):VOID
		{
			$material_id = $args[ 'post_parent' ];
			
			$grade_args = [
				'material_id' 		=> $material_id, 	//Course Work ID
				'assignment_id' 	=> $assignment_id,			//Student Created Assignment
				'status' 			=> $args[ 'post_status' ],	//Status of student submitted assignment.
				'instructor_status' => isset( $args[ 'post_meta' ][ 'instr_status' ] ) ? $args[ 'post_meta' ][ 'instr_status' ] : 0,	//NOW
				'submission_date' 	=> isset( $args[ 'submission_date' ] ) ?  $args[ 'submission_date' ] : date( 'Y-m-d H:i:s' ) ,	//Original Submission Date
				'last_updated' 		=>  isset( $args[ 'last_updated' ] ) ?  $args[ 'last_updated' ] : NULL	//NOW
			];
			
			
			if( isset( $this->grades[ $material_id ] ) ){	
				
				//Check if grade is different: status or instructor status.
				if( ( strcmp( $this->grades[ $material_id ]->get_status(), $grade_args[ 'status' ] ) == 0 ) &&
					( strcmp( $this->grades[ $material_id ]->get_instr_status(), $grade_args[ 'instructor_status' ]) == 0 )  )
					return; 
					
				//Check date for already existing submissions. Retain if already exists. 
				if( !empty( $submission_date = $this->grades[ $material_id ]->get_submission_date() ) )
					$grade_args[ 'submission_date' ] = $submission_date; 
			}
			
			$grade = ( !isset( $this->grades[ $material_id ] ) )? new Grade() : $this->grades[ $material_id ] ;
		
			$grade->build( $grade_args );
				
			$this->grades[ $material_id ] = $grade; 
			
		}		
		
		
		/**
		 * 	delete_grade
		 *
		 * 
		 * 
		 * 
		 **/
		 
		public function delete_grade( int $material_id ):VOID
		{
	
			if( isset( $this->grades[ $material_id ] ) )
				unset( $this->grades[ $material_id ] );
			
		}		

			
		/**
		 * 	get_map
		 *
		 *	(descrip)
		 *
		 *	returns OBJECT
		 *
		 */
		 
		public function get_map(  ): OBJECT
		{
			return $this->assignments_map;
			
		}
		
		
		/**
		 * 	get_tracks
		 *
		 *	Get tracks assigned to this class. This will be relative to the user or all available tracks. 
		 *
		 *	returns array
		 **/	
		 
		public function get_tracks(  ): array
		{
			return $this->tracks; 
		}
		
		
		
		/**
		 * 	get_grades
		 *
		 *	(descrip)
		 *
		 *	returns array
		 *
		 */
		 
		public function get_grades(  ): ARRAY
		{
			return $this->grades;
			
		}	
		
		/**
		 * 	get_grade_by_id
		 *
		 *	(descrip)
		 *
		 *	returns OBJECT|FALSE
		 *
		 */
		 
		public function get_grade_by_id( $id )
		{
			
			return ( !empty( $this->grades[ $id ] ) )?  $this->grades[ $id ] : false ;
			
		}	
		
		/**
		 * 	get_course_id_from_topic_id
		 *
		 *	(descrip)
		 *
		 *	returns INT|0
		 *
		 */
		 
		public function get_course_id_from_topic_id( INT $id ): INT
		{
			return $this->assignments_map->get_course_id_from_assignment_id( $id ); 
			
		}		
		
		/**
		 * 	assignment_exists
		 *
		 *	For those times when a trainer assigns a grade status without the assignment actually existing. 
		 *
		 *	returns OBJECT|FALSE
		 *
		 */
		 
		public function assignment_exists( $id )
		{
			$grade = $this->grades[ $id ]; 			
			return ( $grade )? $grade->get_material_id() : false ;
			
		}			
		
		/**
		 * 	get_grade_status
		 *
		 *	(descrip)
		 *
		 *	returns OBJECT|FALSE
		 *
		 */
		 
		public function get_grade_status( $id )
		{
			$grade = $this->grades[ $id ]; 		
			
			return ( $grade->get_status() ) ?: false ;
			
		}		
				


			
		/**
		 * 	get_children
		 *
		 *	(descrip)
		 *
		 *	returns ARRAY
		 *
		 */
		 
		public function get_children ( $parent_id ): ARRAY
		{
			$units = $this->assignments_map->get_children( $parent_id ); 
			
			
			
		}
				

			
		/** //
		 * 	update_student
		 *
		 *	Updates the student's metadate entry for their course grades. 
		 *
		 *	returns BOOL
		 **/
		 
		private function update_student_meta(  ): BOOL
		{

			$grades_meta = [];
			foreach( $this->grades as $grade ){
				$grades_meta[ $grade->get_material_id() ] =  [
					$grade->get_assignment_id(), 		//parent_id or material_id
					$grade->get_status(),			//assigment_status
					$grade->get_instr_status(), 	//instructor_status of assignment
					$grade->get_submission_date(), 	//original submission date
					$grade->get_last_updated()		//last_updated
				]; 
			}
			//student_grades meta data.
			$updated = update_user_meta( $this->student_id, 'student_grades', $grades_meta, $this->raw_data );

			return ( !empty( $updated ) )? TRUE : FALSE ; 
		}
		

/**
		 * 	update
		 *
		 *	updates one or more grades in the database. Also good for deleting a grade from the list of grades. 
		 *
		 *	returns BOOL
		 *
		 */
		 
		public function update_grades( ): BOOL
		{
			//print_pre( $this->grades, 'Line '.__LINE__ . ", Method: ".__METHOD__ ); 
			foreach( $this->grades as $grade ){
				
				$grade->update_student_meta(); 
				
			}
			
			return $this->update_student_meta(); //Returns true or false. 
		
		}
					
		
		/**
		 * 	update_student_meta
		 *
		 *	updates only one grade in the database. 
		 *
		 *	USED in admin_metaboxes.php::467
		 *
		 *	returns BOOL
		 *
		 */
		 
		public function update_student( ): BOOL
		{
			
			return $this->update_student_meta(); //Returns true or false. 
		
		}

		
		/**
		 * 	(title)
		 *
		 *	(descrip)
		 *
		 *	returns VOID
		 *
		 
		public function (  ): VOID
		{}
			*/

	}
}
?>