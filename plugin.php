<?php 
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

/**
 * Plugin Name: Book A Flight
 * Plugin URI: https://twitter.com/gpnalin
 * Description: This is a plugin that allows us to use Ajax functionality in WordPress
 * Version: 1.0.0
 * Author: Nalin Perera
 * Author URI: https://twitter.com/gpnalin
 * License: GPL2
 */

//http://goodies.pixabay.com/jquery/auto-complete/demo.html
//https://www.devbridge.com/sourcery/components/jquery-autocomplete/
//http://designshack.net/articles/javascript/create-a-simple-autocomplete-with-html5-jquery/
//http://premium.wpmudev.org/blog/using-ajax-with-wordpress/


/**
 * Plugin Version
 *
 * @var string
 **/
global $book_a_flight_db_version;
$book_a_flight_db_version = '1.1';

/**
 * Create the DB Table
 *
 * @return void
 * @author Nalin Perera <gpnalin@gmail.com>
 **/
function book_a_flight_install() {
	global $wpdb;
	global $book_a_flight_db_version;

	$table_name = $wpdb->prefix . 'baf_airports';
	
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE $table_name (
		iata_code varchar(3) NOT NULL UNIQUE,
		airport varchar(100) NOT NULL,
		country varchar(50) NOT NULL
	) $charset_collate;";

	require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
	dbDelta( $sql ); //dbDelta WP function to execute SQL queries

	add_option( 'book_a_flight_db_version', $book_a_flight_db_version );
}

/**
 * Install airport data to table
 *
 * @return void
 * @author Nalin Perera <gpnalin@gmail.com>
 **/
function book_a_flight_install_data() {

	global $wpdb;

	$table_name = $wpdb->prefix . 'baf_airports';

	$csv_path = plugin_dir_path( __FILE__ ) . 'data'. DIRECTORY_SEPARATOR .'airports.csv';

	if (($handle = fopen($csv_path, "r")) !== FALSE) {
	    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {

    		$wpdb->insert( 
				$table_name, 
				array( 
					'iata_code' => mysql_escape_string($data[0]), 
					'airport' => mysql_escape_string($data[1]), 
					'country' => mysql_escape_string($data[2]), 
				) 
			);
	       
	    }
	    fclose($handle);
	}

}

/**
 * Call to the function that need to run on plugin activation.
 *
 * @return void
 **/
register_activation_hook( __FILE__, 'book_a_flight_install' );
register_activation_hook( __FILE__, 'book_a_flight_install_data' );

/**
 * Adding plugin scripts and stylesheets to site.
 *
 * @return void
 **/
add_action( 'wp_enqueue_scripts', 'ajax_worker_enqueue_scripts' );
function ajax_worker_enqueue_scripts() {
	wp_enqueue_script( 'jquery_autocomplete', plugins_url( '/assets/js/jquery.autocomplete.min.js', __FILE__ ), array('jquery'), '1.0', true );

	wp_enqueue_script( 'form-validator', plugins_url( '/assets/js/validator.min.js', __FILE__ ), array('jquery'), '1.0', true );

	wp_enqueue_script( 'ajax_worker', plugins_url( '/assets/js/worker.js', __FILE__ ), array('jquery'), '1.0', true );

	wp_localize_script( 'ajax_worker', 'admin_ajax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
    'mailchimp_api' => plugins_url( 'inc/store-address.php', __FILE__ )
	));		

	wp_enqueue_style( 'plugin-css', plugins_url( '/assets/css/plugin.css', __FILE__ ) );
}

/**
 * Airport search ajax autocomplete
 * 
 * @param    string  $_POST['key']
 * @return   json encoded array
 **/
function airport_live_search() {

	global $wpdb;

	$key = $_POST['key'];

	$table_name = $wpdb->prefix . 'baf_airports';
	
	$query = "SELECT * FROM $table_name WHERE airport LIKE '%{$key}%' OR country LIKE '%{$key}%'";	
 
	$results = $wpdb->get_results($query);

	$suggestions = array();
 
	foreach($results as $result)
	{
		$suggestions[] = array(
		"value" => $result->airport . ' - ' . $result->country,
		"data" => $result->iata_code
		);
	}
	
	ob_clean();

	echo json_encode(array('suggestions' => $suggestions));
	
	wp_die();

}
add_action( 'wp_ajax_nopriv_airport_live_search', 'airport_live_search' );
add_action( 'wp_ajax_airport_live_search', 'airport_live_search' );


/**
 * Form to render on shortcode called
 *
 * @return void
 **/
function form_creation(){
?>
<form class="form-horizontal" id="raq-form" method="POST" data-toggle="validator" action="<?php echo site_url(); ?>">
  <h2>Request a Quote</h2>
<div class="form-group">
  <div class="col-xs-6">
    <div class="clearfix">
      <div class="button-holder">
        <input type="radio" id="trip-type-return" name="trip-type" class="regular-radio" value="return" checked><label for="trip-type-return"></label>
      </div>
      <div class="tag">Return</div>
    </div>
  </div>
  <div class="col-xs-6">
    <div class="clearfix">
      <div class="button-holder">
        <input type="radio" id="trip-type-oneway" name="trip-type" class="regular-radio" value="oneway"><label for="trip-type-oneway"></label>
      </div>
      <div class="tag">One Way</div>
    </div>
  </div>  
</div>
  <div class="form-group">
    <div class="col-xs-12">
      <input type="text" class="form-control select-airport" name="departure-from" id="departure-from" placeholder="Departure" style="background-repeat:no-repeat;" required>
      <div class="help-block with-errors"></div>
    </div>
  </div>
  <div class="form-group">
    <div class="col-xs-4 first">
      <select name="departure-day" id="departure-day" class="form-control" required>
      <?php 
      for ($i=1; $i <= 31; $i++) { 
        echo "<option value=\"$i\">$i</option>";
      }
      ?>
      </select>
    </div>
    <div class="col-xs-4 middle">
      <select name="departure-month" id="departure-month" class="form-control" required>
      <?php 
      for ($m=1; $m<=12; $m++) {
        $month = date('M', mktime(0,0,0,$m, 1, date('Y')));
        echo "<option value=\"$month\">$month</option>";
      }
      ?>
      </select>
    </div>
    <div class="col-xs-4 last">
      <select name="departure-year" id="departure-year" class="form-control" required>
        <?php 

        $start_year = date("Y");

        $end_year = $start_year + 5;

        while ( $start_year <= $end_year )
        { 
          echo "<option value=\"$start_year\">$start_year</option>";
          $start_year++;
        }

        ?>
      </select>
    </div>
  </div>
  <div class="form-group">
    <div class="col-xs-12">
      <input type="text" class="form-control select-airport" name="arrival-from" id="arrival-from" placeholder="Arrival" style="background-repeat:no-repeat;" required>
      <div class="help-block with-errors"></div>
    </div>
  </div>
  <div class="form-group">
    <div class="col-xs-4 first">
      <select name="arrival-day" id="arrival-day" class="form-control" required>
        <?php 
        for ($i=1; $i <= 31; $i++) { 
          echo "<option value=\"$i\">$i</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-xs-4 middle">
      <select name="arrival-month" id="arrival-month" class="form-control" required>
        <?php 
        for ($m=1; $m<=12; $m++) {
          $month = date('M', mktime(0,0,0,$m, 1, date('Y')));
          echo "<option value=\"$month\">$month</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-xs-4 last">
      <select name="arrival-year" id="arrival-year" class="form-control" required>
        <?php 

        $start_year = date("Y");

        $end_year = $start_year + 5;

        while ( $start_year <= $end_year )
        { 
          echo "<option value=\"$start_year\">$start_year</option>";
          $start_year++;
        }

        ?>
      </select>
    </div>
  </div>
  <div class="form-group">
    <div class="col-xs-4 first">
      <select name="adults" id="adults" class="form-control" required>
        <option value="">Adults</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
      </select>
    </div>
    <div class="col-xs-4 middle">
      <select name="child" id="child" class="form-control">
        <option value="">Child</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
      </select>
    </div>
    <div class="col-xs-4 last">
      <select name="infant" id="infant" class="form-control">
        <option value="">Infant</option>
        <option value="1">1</option>
        <option value="2">2</option>
        <option value="3">3</option>
        <option value="4">4</option>
        <option value="5">5</option>
      </select>
    </div>
  </div>
  <div class="form-group has-feedback">
    <div class="col-xs-12">
      <input type="email" class="form-control" name="email" id="email" placeholder="Email Address" data-error="Please enter a valid email" required>
      <span class="glyphicon glyphicon-asterisk form-control-feedback" aria-hidden="false"></span>
	    <div class="help-block with-errors"></div>
    </div>
  </div>
  <div class="form-group has-feedback">
    <div class="col-xs-12">
      <input pattern="^([0-9]){9,12}$" maxlength="12" type="tel" class="form-control" name="phone" id="phone" placeholder="Phone  Number" data-error="Please enter a valid phone number" required>
	    <span class="glyphicon glyphicon-asterisk form-control-feedback" aria-hidden="false"></span>
      <div class="help-block with-errors"></div>      
    </div>
  </div>
  <div class="form-group">
    <div class="col-xs-12">
      <button type="submit" class="btn btn-submit">SUBMIT</button>
    </div>
  </div>
</form>
<h2 id="success" class="form-msg text-center hide-this">Your quote request was sent successfully!</h2>
<h2 id="error" class="form-msg text-center hide-this">Your quote request wasn't sent successfully. Please try again!</h2>
<?php
}

/**
 * Regiter WP Shortcode to call on WordPress Templates.
 *
 * @return calls the form_creation function
 **/
add_shortcode('request_a_quote', 'form_creation');



/**
 * Sends the email with form data
 *
 * @return array
 **/
function submit_form() {

  $trip_type	= $_POST['trip_type'];
  $departure_from	= $_POST['departure_from'];
  $departure_date	= $_POST['departure_date'];
  $arrival_to	= $_POST['arrival_to'];
  $arrival_date	= $_POST['arrival_date'];
  $adults	= $_POST['adults'];
  $childs	= $_POST['childs'];
  $infants	= $_POST['infants'];
  $email	= $_POST['email'];
  $phone	= $_POST['phone'];

	$to = get_option('admin_email');

	$subject = 'Quote request from';

  $body  = "Tour Type: $trip_type";
	$body .= " \r \n";
	$body .= "Departure From: $departure_from";
  $body .= " \r \n";
	$body .= "Departure Date: $departure_date";
  $body .= " \r \n";
	$body .= "Arrival To: $arrival_to";
  $body .= " \r \n";
	$body .= "Arrival Date: $arrival_date";
  $body .= " \r \n";
	$body .= "No. of Adults: $adults";
  $body .= " \r \n";
	$body .= "No. of Childs: $childs";
  $body .= " \r \n";
	$body .= "No. of Infants: $infants";
  $body .= " \r \n";
	$body .= "Email: $email";
  $body .= " \r \n";
	$body .= "Phone: $phone";

	$isSent = wp_mail( $to, $subject, $body);
  if ($isSent) {
    wp_send_json_success();
  }else{
    wp_send_json_error();   
  }
}
add_action( 'wp_ajax_nopriv_submit_form', 'submit_form' );
add_action( 'wp_ajax_submit_form', 'submit_form' );

/**
 * Change the email sender name
 */
add_filter( 'wp_mail_from_name', 'custom_wp_mail_from_name' );
function custom_wp_mail_from_name( $original_email_from ) {
  return 'Webmaster';
}
