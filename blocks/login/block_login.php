<?php

class block_login extends block_base {
    function init() {
        $this->title = get_string('pluginname', 'block_login');
    }

    function applicable_formats() {
        return array('site' => true);
    }

    function get_content () {
        global $USER, $CFG, $SESSION, $OUTPUT;
        $wwwroot = '';
        $signup = '';

        if ($this->content !== NULL) {
            return $this->content;
        }

        if (empty($CFG->loginhttps)) {
            $wwwroot = $CFG->wwwroot;
        } else {
            // This actually is not so secure ;-), 'cause we're
            // in unencrypted connection...
            $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
        }

        if (!empty($CFG->registerauth)) {
            $authplugin = get_auth_plugin($CFG->registerauth);
            if ($authplugin->can_signup()) {
                $signup = $wwwroot . '/login/signup.php';
            }
        }
        // TODO: now that we have multiauth it is hard to find out if there is a way to change password
        $forgot = $wwwroot . '/login/forgot_password.php';

        if (empty($CFG->xmlstrictheaders) and !empty($CFG->loginpasswordautocomplete)) {
            $autocomplete = 'autocomplete="off"';
        } else {
            $autocomplete = '';
        }

        $username = get_moodle_cookie();

        $this->content = new stdClass();
        $this->content->footer = '';
        $this->content->text = '';

        if (!isloggedin() or isguestuser()) {   // Show the block

            $this->content->text .= "\n".'<div id="cookie_info">Cookies must be enabled '.$OUTPUT->help_icon('cookiesenabled').'</div>';

            $this->content->text .= '<div id="sso_button">';
            $this->content->text .= '<a href="'.get_login_url().'" title="B&FC Web Applications Single Sign-on">Web Apps Single Sign-on</a>';
            $this->content->text .= '</div>';

            $this->content->text .= '<div id="sso_intro">';
            $this->content->text .= '<p>Click the button above to single sign into all college web applications, or use the form below to login to Moodle only.</p>';
            $this->content->text .= '</div>';

            $this->content->text .= '<form class="loginform" id="login" method="post" action="'.get_no_sso_login_url().'" '.$autocomplete.'>';

            $this->content->text .= '<div class="c1 fld username"><label for="login_username">'.get_string('username').'</label>';
            $this->content->text .= '<input type="text" name="username" id="login_username" value="'.s($username).'" /></div>';

            $this->content->text .= '<div class="c1 fld password"><label for="login_password">'.get_string('password').'</label>';

            $this->content->text .= '<input type="password" name="password" id="login_password" value="" '.$autocomplete.' /></div>';

            if (isset($CFG->rememberusername) and $CFG->rememberusername == 2) {
                $checked = $username ? 'checked="checked"' : '';
                $this->content->text .= '<div class="c1 rememberusername"><input type="checkbox" name="rememberusername" id="rememberusername" value="1" '.$checked.'/>';
                $this->content->text .= ' <label for="rememberusername">'.get_string('rememberusername', 'admin').'</label></div>';
            }

            $this->content->text .= '<div class="c1 btn"><input type="submit" value="'.get_string('login').'" /></div>';

            $this->content->text .= "</form>\n";

            if (!empty($signup)) {
                $this->content->footer .= '<div><a href="'.$signup.'">'.get_string('startsignup').'</a></div>';
            }
            if (!empty($forgot)) {
                $this->content->footer .= '<div><a href="'.$forgot.'">'.get_string('forgotaccount').'</a></div>';
            }
        }

        return $this->content;
    }
}


