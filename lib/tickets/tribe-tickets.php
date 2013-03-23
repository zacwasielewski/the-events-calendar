<?php

if ( ! class_exists( 'TribeEventsTickets' ) ) {
	abstract class TribeEventsTickets {

		// All TribeEventsTickets api consumers. It's static, so it's shared across childs.
		protected static $active_modules = array();
		public static $active = false;

		public $className;
		private $parentPath;
		private $parentUrl;
		private $attendees_slug = 'tickets-attendees';
		private $attendees_page;

		protected $image_header_field = '_tribe_ticket_header';
		/**
		 * @var TribeEventsTicketsAttendeesTable
		 */
		private $attendees_table;

		// prevent re-doing the metabox by different childs
		private static $done_metabox = false;
		private static $done_attendees_admin_page = false;

		// start API Definitions
		// Child classes must implement all these functions / properties

		public $pluginName;
		protected $pluginPath;
		protected $pluginUrl;

		abstract function get_event_reports_link( $event_id );

		abstract function get_ticket_reports_link( $event_id, $ticket_id );

		abstract function save_ticket( $event_id, $ticket, $raw_data = array() );

		abstract protected function get_tickets( $event_id );

		abstract protected function get_attendees( $event_id );

		abstract public function checkin( $attendee_id );

		abstract public function uncheckin( $attendee_id );

		abstract function get_ticket( $event_id, $ticket_id );

		abstract function delete_ticket( $event_id, $ticket_id );

		abstract function do_metabox_advanced_options( $event_id, $ticket_id );

		abstract function front_end_tickets_form( $content );

		abstract static function get_instance();

		// end API Definitions

		function __construct() {

			// As this is an abstract class, we want to know which child
			// instantiated it
			$this->className = get_class( $this );

			$this->parentPath = trailingslashit( dirname( dirname( dirname( __FILE__ ) ) ) );
			$this->parentUrl  = trailingslashit( plugins_url( '', $this->parentPath ) );

			// Register all TribeEventsTickets api consumers
			self::$active_modules[$this->className] = $this->pluginName;

			self::$active = true;

			if ( is_admin() ) {
				//add_action( 'tribe_events_event_save', array( $this, 'save_tickets' ), 10, 1 );
				add_action( 'tribe_events_tickets_metabox_advanced', array( $this, 'do_metabox_advanced_options' ), 10, 2 );
				// Attendees list
				add_filter( 'post_row_actions', array( $this, 'attendees_row_action' ) );
				add_action( 'admin_menu', array( $this, 'attendees_page_register' ) );
				add_action( 'save_post', array( $this, 'save_image_header' ), 20, 2 );
			}


			add_filter( 'tribe_events_tickets_modules', array( $this, 'modules' ) );

			// Admin AJAX actions
			add_action( 'wp_ajax_tribe-ticket-add-' . $this->className, array( $this, 'ajax_handler_ticket_add' ) );
			add_action( 'wp_ajax_tribe-ticket-delete-' . $this->className, array( $this, 'ajax_handler_ticket_delete' ) );
			add_action( 'wp_ajax_tribe-ticket-edit-' . $this->className, array( $this, 'ajax_handler_ticket_edit' ) );
			add_action( 'wp_ajax_tribe-ticket-checkin-' . $this->className, array( $this, 'ajax_handler_attendee_checkin' ) );
			add_action( 'wp_ajax_tribe-ticket-uncheckin-' . $this->className, array( $this, 'ajax_handler_attendee_uncheckin' ) );

			add_action( 'wp_ajax_tribe-ticket-email-attendee-list', array( $this, 'ajax_handler_attendee_mail_list' ) );


			// Front end
			add_filter( 'tribe_get_ticket_form', array( $this, 'front_end_tickets_form' ) );

		}

		public final function do_meta_box( $post_id ) {

			if ( self::$done_metabox )
				return;

			$startMinuteOptions   = TribeEventsViewHelpers::getMinuteOptions( null );
			$endMinuteOptions     = TribeEventsViewHelpers::getMinuteOptions( null );
			$startHourOptions     = TribeEventsViewHelpers::getHourOptions( null, true );
			$endHourOptions       = TribeEventsViewHelpers::getHourOptions( null, false );
			$startMeridianOptions = TribeEventsViewHelpers::getMeridianOptions( null, true );
			$endMeridianOptions   = TribeEventsViewHelpers::getMeridianOptions( null );

			$tickets = self::get_event_tickets( $post_id );
			include $this->parentPath . 'admin-views/tickets-meta-box.php';
			self::$done_metabox = true;

		}

		public function generate_tickets_email_content( $tickets ) {

			ob_start();
			include TribeEventsTemplates::getTemplateHierarchy( 'tickets-email.php' );
			return ob_get_clean();
		}

		/* AJAX Handlers */

		public final function ajax_handler_ticket_add() {

			if ( ! isset( $_POST["formdata"] ) ) $this->ajax_error( 'Bad post' );
			if ( ! isset( $_POST["post_ID"] ) ) $this->ajax_error( 'Bad post' );

			$data    = wp_parse_args( $_POST["formdata"] );
			$post_id = $_POST["post_ID"];

			if ( ! isset( $data["ticket_provider"] ) || ! $this->module_is_valid( $data["ticket_provider"] ) ) $this->ajax_error( 'Bad module' );

			$ticket = new TribeEventsTicketObject();

			$ticket->ID          = isset( $data["ticket_id"] ) ? $data["ticket_id"] : null;
			$ticket->name        = isset( $data["ticket_name"] ) ? $data["ticket_name"] : null;
			$ticket->description = isset( $data["ticket_description"] ) ? $data["ticket_description"] : null;
			$ticket->price       = isset( $data["ticket_price"] ) ? trim( $data["ticket_price"] ) : 0;

			if ( empty( $ticket->price ) ) {
				$ticket->price = 0;
			} else {
				//remove non-money characters
				$ticket->price = preg_replace( '/[^0-9\.]/Uis', '', $ticket->price );
			}

			if ( ! empty( $data['ticket_start_date'] ) ) {
				$meridian           = ! empty( $data['ticket_start_meridian'] ) ? " " . $data['ticket_start_meridian'] : "";
				$ticket->start_date = date( TribeDateUtils::DBDATETIMEFORMAT, strtotime( $data['ticket_start_date'] . " " . $data['ticket_start_hour'] . ":" . $data['ticket_start_minute'] . ":00" . $meridian ) );
			}

			if ( ! empty( $data['ticket_end_date'] ) ) {
				$meridian         = ! empty( $data['ticket_end_meridian'] ) ? " " . $data['ticket_end_meridian'] : "";
				$ticket->end_date = date( TribeDateUtils::DBDATETIMEFORMAT, strtotime( $data['ticket_end_date'] . " " . $data['ticket_end_hour'] . ":" . $data['ticket_end_minute'] . ":00" . $meridian ) );
			}

			$ticket->provider_class = $this->className;

			// Pass the control to the child object
			$return = $this->save_ticket( $post_id, $ticket, $data );

			// If saved OK, let's create a tickets list markup to return
			if ( $return ) {
				$tickets = $this->get_event_tickets( $post_id );
				$return  = $this->get_ticket_list_markup( $tickets );

				$return = $this->notice( __( 'Your ticket has been saved.', 'tribe-events-calendar' ) ) . $return;
			}

			$this->ajax_ok( $return );
		}


		public function save_image_header( $post_id, $post ) {

			// only continue if it's an event post
			if ( $post->post_type != TribeEvents::POSTTYPE )
				return;
			// don't do anything on autosave or auto-draft either or massupdates
			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) )
				return;

			if ( empty( $_POST['tribe_ticket_header_image_id'] ) )
				delete_post_meta( $post_id, $this->image_header_field );
			else
				update_post_meta( $post_id, $this->image_header_field, $_POST['tribe_ticket_header_image_id'] );

			return;
		}

		public final function ajax_handler_attendee_checkin() {

			if ( ! isset( $_POST["order_ID"] ) || intval( $_POST["order_ID"] ) == 0 )
				$this->ajax_error( 'Bad post' );
			if ( ! isset( $_POST["provider"] ) || ! $this->module_is_valid( $_POST["provider"] ) )
				$this->ajax_error( 'Bad module' );

			$order_id = $_POST["order_ID"];

			// Pass the control to the child object
			$return = $this->checkin( $order_id );

			$this->ajax_ok( $return );
		}

		public final function ajax_handler_attendee_uncheckin() {

			if ( ! isset( $_POST["order_ID"] ) || intval( $_POST["order_ID"] ) == 0 )
				$this->ajax_error( 'Bad post' );
			if ( ! isset( $_POST["provider"] ) || ! $this->module_is_valid( $_POST["provider"] ) )
				$this->ajax_error( 'Bad module' );

			$order_id = $_POST["order_ID"];

			// Pass the control to the child object
			$return = $this->uncheckin( $order_id );

			$this->ajax_ok( $return );
		}

		public final function ajax_handler_ticket_delete() {

			if ( ! isset( $_POST["post_ID"] ) )
				$this->ajax_error( 'Bad post' );
			if ( ! isset( $_POST["ticket_id"] ) )
				$this->ajax_error( 'Bad post' );

			$post_id   = $_POST["post_ID"];
			$ticket_id = $_POST["ticket_id"];

			// Pass the control to the child object
			$return = $this->delete_ticket( $post_id, $ticket_id );

			// If deleted OK, let's create a tickets list markup to return
			if ( $return ) {
				$tickets = $this->get_event_tickets( $post_id );
				$return  = $this->get_ticket_list_markup( $tickets );

				$return = $this->notice( __( 'Your ticket has been deleted.', 'tribe-events-calendar' ) ) . $return;
			}

			$this->ajax_ok( $return );
		}

		public final function ajax_handler_ticket_edit() {

			if ( ! isset( $_POST["post_ID"] ) )
				$this->ajax_error( 'Bad post' );
			if ( ! isset( $_POST["ticket_id"] ) )
				$this->ajax_error( 'Bad post' );

			$post_id   = $_POST["post_ID"];
			$ticket_id = $_POST["ticket_id"];

			$return = get_object_vars( $this->get_ticket( $post_id, $ticket_id ) );

			ob_start();
			$this->do_metabox_advanced_options( $post_id, $ticket_id );
			$extra = ob_get_contents();
			ob_end_clean();

			$return["advanced_fields"] = $extra;

			$this->ajax_ok( $return );
		}

		public function ajax_handler_attendee_mail_list() {

			if ( ! isset( $_POST["event_id"] ) || ! isset( $_POST["email"] ) || ! ( is_numeric( $_POST["email"] ) || is_email( $_POST["email"] ) ) )
				$this->ajax_error( 'Bad post' );
			if ( empty( $_POST["nonce"] ) || ! wp_verify_nonce( $_POST["nonce"], 'email-attendee-list' ) )
				$this->ajax_error( 'Bad post' );

			if ( is_email( $_POST["email"] ) ) {
				$email = $_POST["email"];
			} else {
				$user  = get_user_by( 'id', $_POST["email"] );
				$email = $user->data->user_email;
			}

			if ( empty( $GLOBALS['hook_suffix'] ) )
				$GLOBALS['hook_suffix'] = 'tribe_ajax';

			$this->attendees_page_screen_setup();

			$items = $this->_generate_filtered_attendees_list( $_POST["event_id"] );

			$event = get_post( $_POST["event_id"] );

			ob_start();
			include $this->parentPath . 'views/tickets-attendees-email.php';
			$content = ob_get_clean();

			add_filter( 'wp_mail_content_type', array( $this, 'set_contenttype' ) );
			if ( ! wp_mail( $email, sprintf( __( 'Attendee List for: %s', 'tribe-events-calendar' ), $event->post_title ), $content ) )
				$this->ajax_error( 'Error sending email' );

			$this->ajax_ok( array() );
		}

		public function set_contenttype( $content_type ) {
			return 'text/html';
		}

		protected function notice( $msg ) {
			return sprintf( '<div class="wrap"><div class="updated"><p>%s</p></div></div>', $msg );
		}

		protected final function ajax_error( $message = "" ) {
			header( 'Content-type: application/json' );

			echo json_encode( array( "success" => false,
									 "message" => $message ) );
			exit;
		}

		protected final function ajax_ok( $data ) {
			$return = array();
			if ( is_object( $data ) ) {
				$return = get_object_vars( $data );
			} elseif ( is_array( $data ) || is_string( $data ) ) {
				$return = $data;
			} elseif ( is_bool( $data ) && ! $data ) {
				$this->ajax_error( "Something went wrong" );
			}

			header( 'Content-type: application/json' );
			echo json_encode( array( "success" => true,
									 "data"    => $return ) );
			exit;
		}

		// end AJAX Handlers

		// start Attendees

		public function attendees_row_action( $actions ) {
			global $post;

			if ( $post->post_type == TribeEvents::POSTTYPE ) {


				$url = add_query_arg( array( 'post_type' => TribeEvents::POSTTYPE,
											 'page'      => $this->attendees_slug,
											 'event_id'  => $post->ID ), admin_url( 'edit.php' ) );

				$actions['tickets_attendees'] = sprintf( '<a title="%s" href="%s">%s</a>', __( 'See who purchased tickets to this event', 'tribe-events-calendar' ), esc_url( $url ), __( 'Attendees', 'tribe-events-calendar' ) );
			}
			return $actions;
		}

		public function attendees_page_register() {
			if ( self::$done_attendees_admin_page )
				return;

			$this->attendees_page = add_submenu_page( null, 'Attendee list', 'Attendee list', 'edit_posts', $this->attendees_slug, array( $this, 'attendees_page_inside' ) );

			add_action( 'admin_enqueue_scripts', array( $this, 'attendees_page_load_css_js' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'attendees_page_load_pointers' ) );
			add_action( "load-$this->attendees_page", array( $this, "attendees_page_screen_setup" ) );


			self::$done_attendees_admin_page = true;
		}

		public function attendees_page_load_css_js( $hook ) {
			if ( $hook != $this->attendees_page )
				return;

			$ecp = TribeEvents::instance();

			wp_enqueue_style( $this->attendees_slug, trailingslashit( $ecp->pluginUrl ) . 'resources/tickets-attendees.css' );
			wp_enqueue_style( $this->attendees_slug . '-print', trailingslashit( $ecp->pluginUrl ) . 'resources/tickets-attendees-print.css', array(), false, 'print' );
			wp_enqueue_script( $this->attendees_slug, trailingslashit( $ecp->pluginUrl ) . 'resources/tickets-attendees.js', array( 'jquery' ) );


			$mail_data = array(
				'nonce'    => wp_create_nonce( 'email-attendee-list' ),
				'required' => __( 'You need to select an user or type a valid email address', 'tribe-events-calendar' ),
				'sending'  => __( 'Sending...', 'tribe-events-calendar' )
			);

			wp_localize_script( $this->attendees_slug, 'AttendeesMail', $mail_data );
		}

		public function attendees_page_load_pointers( $hook ) {
			if ( $hook != $this->attendees_page )
				return;


			$dismissed = explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) );
			$pointer   = null;


			if ( version_compare( get_bloginfo( 'version' ), '3.3', '>' ) && ! in_array( 'attendees_filters', $dismissed ) ) {
				$pointer = array(
					'pointer_id' => 'attendees_filters',
					'target'     => '#screen-options-link-wrap',
					'options'    => array(
						'content'  => sprintf( '<h3> %s </h3> <p> %s </p>',
							__( 'Columns', 'tribe-events-calendar' ),
							__( 'You can use Screen Options to select which columns you want to see. The selection works in the table below, in the email, for print and for the CSV export.', 'tribe-events-calendar' )
						),
						'position' => array( 'edge' => 'top', 'align' => 'center' )
					)
				);
				wp_enqueue_script( 'wp-pointer' );
				wp_enqueue_style( 'wp-pointer' );
			}

			wp_localize_script( $this->attendees_slug, 'AttendeesPointer', $pointer );

		}

		public function attendees_page_screen_setup() {

			require_once 'tribe-tickets-attendees.php';
			$this->attendees_table = new TribeEventsTicketsAttendeesTable();

			$this->maybe_generate_attendees_cvs();

			wp_enqueue_script( 'jquery-ui-dialog' );

		}

		public function attendees_page_inside() {
			include $this->parentPath . 'admin-views/tickets-attendees.php';
		}

		private function _generate_filtered_attendees_list( $event_id ) {

			if ( empty( $this->attendees_page ) )
				$this->attendees_page = 'tribe_events_page_tickets-attendees';

			$columns = $this->attendees_table->get_columns();
			$hidden  = get_hidden_columns( $this->attendees_page );

			// We dont want to export html inputs or private data
			$hidden[] = 'cb';
			$hidden[] = 'provider';

			// remove the hidden fields from the final list of coulumns
			$hidden         = array_flip( $hidden );
			$export_columns = array_diff_key( $columns, $hidden );
			$columns_names  = array_filter( array_values( $export_columns ) );
			$export_columns = array_filter( array_keys( $export_columns ) );

			// Get the data
			$items = TribeEventsTickets::get_event_attendees( $event_id );

			$rows = array( $columns_names );
			//And echo the data
			foreach ( $items as $item ) {
				$row = array();
				foreach ( $item as $key => $data ) {
					if ( in_array( $key, $export_columns ) ) {
						if ( $key == 'check_in' && $data == 1 )
							$data = __( 'Yes' );
						$row[$key] = $data;
					}
				}
				$rows[] = array_values( $row );
			}

			return array_filter( $rows );
		}

		public function maybe_generate_attendees_cvs() {

			if ( empty( $_GET['attendees_csv'] ) || empty( $_GET['attendees_csv_nonce'] ) || empty( $_GET['event_id'] ) )
				return;

			if ( ! wp_verify_nonce( $_GET['attendees_csv_nonce'], 'attendees_csv_nonce' ) )
				return;


			$items = $this->_generate_filtered_attendees_list( $_GET['event_id'] );
			$event = get_post( $_GET['event_id'] );

			if ( ! empty( $items ) ) {

				$charset  = get_option( 'blog_charset' );
				$filename = sanitize_file_name( $event->post_title . '-' . __( 'attendees', 'tribe-events-calendar' ) );

				// output headers so that the file is downloaded rather than displayed
				header( "Content-Type: text/csv; charset=$charset" );
				header( "Content-Disposition: attachment; filename=$filename.csv" );

				// create a file pointer connected to the output stream
				$output = fopen( 'php://output', 'w' );

				//And echo the data
				foreach ( $items as $item ) {
					fputcsv( $output, $item );
				}

				fclose( $output );
				exit;
			}
		}

		final static public function get_event_attendees( $event_id ) {
			$attendees = array();
			foreach ( self::$active_modules as $class=> $module ) {
				$obj       = call_user_func( array( $class, 'get_instance' ) );
				$attendees = array_merge( $attendees, $obj->get_attendees( $event_id ) );
			}
			return $attendees;
		}

		final static public function get_event_checkedin_attendees_count( $event_id ) {

			$checkedin = TribeEventsTickets::get_event_attendees( $event_id );

			return array_reduce( $checkedin, array( "TribeEventsTickets", "_checkedin_attendees_array_filter" ), 0 );

		}

		private static function _checkedin_attendees_array_filter( $result, $item ) {
			if ( ! empty( $item['check_in'] ) )
				return $result + 1;

			return $result;
		}


		// end Attendees

		// start Helpers

		private function module_is_valid( $module ) {
			return array_key_exists( $module, self::$active_modules );
		}

		private function ticket_list_markup( $tickets = array() ) {
			if ( ! empty( $tickets ) )
				include $this->parentPath . 'admin-views/tickets-list.php';
		}

		private function get_ticket_list_markup( $tickets = array() ) {

			ob_start();
			$this->ticket_list_markup( $tickets );
			$return = ob_get_contents();
			ob_end_clean();

			return $return;
		}

		protected function tr_class() {
			echo "ticket_advanced ticket_advanced_" . $this->className;
		}

		public function modules() {
			return self::$active_modules;
		}

		final static public function get_event_tickets( $event_id ) {

			$tickets = array();

			foreach ( self::$active_modules as $class=> $module ) {
				$obj     = call_user_func( array( $class, 'get_instance' ) );
				$tickets = array_merge( $tickets, $obj->get_tickets( $event_id ) );
			}

			return $tickets;
		}

		public function getTemplateHierarchy( $template ) {

			if ( substr( $template, - 4 ) != '.php' ) {
				$template .= '.php';
			}

			if ( $theme_file = locate_template( array( 'events/' . $template ) ) ) {
				$file = $theme_file;
			} else {
				$file = $this->pluginPath . 'views/' . $template;
			}
			return apply_filters( 'tribe_events_tickets_template_' . $template, $file );
		}

		// end Helpers
	}
}
