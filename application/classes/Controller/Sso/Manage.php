<?php

defined('SYSPATH') or die('No direct script access.');

class Controller_Sso_Manage extends Controller_Sso_Master {
    public function before() {
        parent::before();
        
        if($this->session()->get("sso_token_lock", false) && ($this->_action == "display")){
            $this->redirect("/sso/auth/checks");
            exit();
        }
    }
    
    /**
     * Display the user's details if they're logged in.
     * 
     * If a user is not logged in, send them to the SSO system to login.
     */
    public function action_display(){
        // If they're not logged in, we'll treat this as an SSO login.
        if(!is_object($this->_current_account) OR !$this->_current_account->loaded()){
            require_once "/var/www/sharedResources/SSO.class.php";
            $SSO = new SSO("CORE", URL::site("/sso/manage/display", "http"), false, URL::site("/sso/token/auth"));
            $details = $SSO->member;
        }
        
        // Set the account details
        $this->_data["_account"] = $this->_current_account;
    }
    
    public function action_email_confirm(){
        // Submitted the form?
        if (HTTP_Request::POST == $this->request->method()) {
            // Is the email "valid"?
            $valid = false;
            try {
                if(Vatsim::factory("autotools")->confirm_email($this->_current_account->id, $this->request->post("email"))){
                    $valid = true;
                } else {
                    $this->_data["error"] = "This email address does not match your VATSIM registered one.  Please try again.";
                }
            } catch(Exception $e){
                $this->_data["error"] = "The VATSIM Certificate Server is currently offline and we are unable to validate your request.";
            }
            
            // Let's store it!
            if($valid){
                // Store and return to the site!
                try {
                    $this->_current_account->emails->set_primary($this->request->post("email"));
                } catch(Exception $e){
                    $valid = false;
                    $this->_data["error"] = "There seems to be an error.  Please contact web services.";
                }
                
                // Send the SSO welcome email.
                ORM::factory("Postmaster_Queue")->action_add("SSO_CREATED", $id, null, 
                    array(
                        "primary_email" => $this->_current_account->emails->get_active_primary()->email,
                        "account_state" => $this->_current_account->getState(),
                    ));
                
                // Send back to complete the checks!
                if($valid){
                    $this->redirect("/sso/auth/checks");
                    return;
                }
            }
        }
        
        $this->setTitle("Email Confirmation");
    }
    
    public function action_email_allocate(){
        
    }
    
}