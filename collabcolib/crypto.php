<?php

	define("COLLABCO_CRYPTO", "1.0.0");

	function generateKey($username) {
		global $CFG;
		return hash('sha256', mb_convert_encoding("!kQm*fF3pqcm642pv34y89Xe1Krc%9" . strtoupper($username), "UTF-16LE"), true );
		 
		//$salt = $CFG->passwordsaltmain;		
		
		//return hash('sha256', mb_convert_encoding($salt . strtoupper($username), "UTF-16LE"), true );
	}

	function encrypt($plaintext, $key) { 
		// Build $iv and $iv_base64.  We use a block size of 128 bits (AES compliant) and CBC mode.  (Note: ECB mode is inadequate as IV is not used.)
		srand(); $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC), MCRYPT_RAND);
		 
		if (strlen($iv_base64 = rtrim(base64_encode($iv), '=')) != 22)
		{	 
			return false;
		}
		
		//header("hub_verification_hash: ". md5(preg_replace("'\s+'",'',$plaintext)));
		header("hub_verification_hash: ". md5(trim($plaintext)));
		
		
		// Encrypt $plaintext and an MD5 of $plaintext using $key.  MD5 is fine to use here because it's just to verify successful decryption.
		$encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $plaintext, MCRYPT_MODE_CBC, $iv));
		// We're done!
		 
		//echo "K: " . base64_encode($key) . "<br />";
		//echo "I: " . base64_encode($iv) . "<br />";
		//echo "E: " . base64_encode($encrypted) . "<br />";
		  
		return $iv_base64 . $encrypted;
	 }	
?>
