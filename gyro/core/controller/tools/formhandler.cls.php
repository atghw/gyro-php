<?php
Load::models('formvalidations');

/**
 * Wraps functionality related to forms
 *  
 * @author Gerd Riesselmann
 * @ingroup Controller
 */ 
class FormHandler {
	/**
	 * Name of form. 
	 */
	private $name = '';
	
	/**
	 * Url of form
	 */
	private $url = null;
	
	/**
	 * Create (and validate) a token?
	 */
	private $use_token = true;
		
	/**
	 * Constructor
	 * 
	 * @param string $name Name of form
	 * @param string $path (optional) Path of form , if != current path
	 * @param bool   $create_token True to create a unique token to identify Form
	 */	
 	public function __construct($name, $path = '', $use_token = true) {
 		$this->name = $name;
 		$this->use_token = $use_token;
 		$this->url = Url::current();
 		if (!empty($path)) {
 			$this->url->set_path($path);
 		}
 	}
 		
 	/**
 	 * Set data required on view
 	 * 
 	 * @param View The view to populate with data
 	 * @param mixed Array or Object containing key/value-pairs for default data
 	 */
 	public function prepare_view($view, $data = false) {
 		$token_html = '';
 		if ($this->use_token) {
	 		$token = $this->create_token();
			$token_html .= html::input('hidden', Config::get_value(Config::FORMVALIDATION_FIELD_NAME), array('value' => $token));
			$token_html .= html::input('hidden', Config::get_value(Config::FORMVALIDATION_HANDLER_NAME), array('value' => $this->name));
			
			if (Session::is_started()) {
				// Note that token is key and form id is value
				Session::push_to_array_assoc('formhandlertokens', $this->name, $token);
			}
 		}		
 		$view->assign('form_validation', $token_html);

		if (!empty($data)) {
 			$this->set_form_data_on_view((array)$data,$view);
		}
 		
 		$form_data = $this->restore_post_data();
 		$this->set_form_data_on_view($form_data, $view);
 	}
 	
 	/**
 	 * Set value of restores POST on view
 	 * 
 	 * Sets variables of form 'form_data_[POST-key]'
 	 * 
 	 * @param array $form_data
 	 * @param IView $view
 	 */
 	private function set_form_data_on_view($form_data, $view) {
 		$form_data_clean = Arr::force($view->retrieve('form_data'));
 		if (is_array($form_data)) {
 			foreach($form_data as $key => $value) {
 				if ($key != Config::get_value(Config::FORMVALIDATION_FIELD_NAME) && $key != Config::get_value(Config::FORMVALIDATION_HANDLER_NAME)) {
 					$form_data_clean[$key] = $value;
 				}
 			}
 		}
 		$view->assign('form_data', $form_data_clean);
 	}

 	/**
 	 * Create a new token
 	 */
 	private function create_token() {
 		$token = FormValidations::create_token($this->name);
 		return $token; 			
 	}
 	
 	/**
 	 * Validate a Form
 	 * 
 	 * @return Status 
 	 */
 	public function validate() {
 		$ret = new Status();
		$success = true;
 		if ($this->use_token) {
			$token = Arr::get_item($_POST, Config::get_value(Config::FORMVALIDATION_FIELD_NAME), '');
			// Validate if token is in DB
			$success = $success && ($this->name == Arr::get_item($_POST, Config::get_value(Config::FORMVALIDATION_HANDLER_NAME), ''));
	 		$success = $success && FormValidations::validate_token($this->name, $token);
	 		// Validate if token is in Session, too
	 		if (Session::is_started()) {
	 			$formtokens = Session::pull('formhandlertokens');
	 			$success = $success && ($this->name == Arr::get_item($formtokens, $token, ''));
	 			
	 			unset($formtokens[$token]);
	 			Session::push('formhandlertokens', $formtokens);
	 		}	 		
 		}
 		if ($success == false) {
 			$ret->append(tr('Form verification token is too old. Please try again.', 'core'));
 		}
 		return $ret;
 	}

	/**
 	 * Called after a form has been processed
 	 *
 	 * @param Status $status 
 	 * @param string $success_message Optional message to display on success
 	 */
 	public function finish($status, $success_message = '') {
 		$params = array(
 			'name' => $this->name,
 			'status' => $status
 		);
 		EventSource::Instance()->invoke_event_no_result('form_finished', $params);
 		
 		if ($status->is_error()) {
 			$this->error($status);
 		}
 		else {
 			$msg = ($status->is_empty()) ? $success_message : $status->message;
 			$this->success($msg);
 		}
 	}
 	
 	/**
 	 * Called if a form has been processed successfully
 	 *
 	 * @param Status|string $message
 	 */
 	public function success($message) {
 		History::go_to(0, $message);
 		exit;
 	}
 	
 	/**
 	 * Called if form has finished unsucessfully
 	 *
 	 * @param Status|string $status
 	 */
 	public function error($status) {
 		if (!($status instanceof Status)) {
 			$status = new Status($status);
 		}
 		$this->fix_post_history($status);
 		exit;
 	}
 	
 	/**
 	 * Allows back button in browser even after POST
 	 * 
 	 * Does a redirect. Requires open session.
 	 * Stores POST data (restored in constructor)
 	 * 
 	 * @param Status $status
 	 */
 	public function fix_post_history($status = null) {
 		$this->store_post_data();
 		if ($status) {
 			$status->persist();
 		}
 		$this->url->redirect();
 		exit;
 	}
 		
 	/**
 	 * Stores POST in associate array '
 	 */ 
 	private function store_post_data() {
 		Session::push('form_data', $_POST); 
 	}
 	
 	/**
 	 * Restores former saved POST data
 	 */ 
 	private function restore_post_data() {
 		return Session::pull('form_data'); 
 	} 	
} 

