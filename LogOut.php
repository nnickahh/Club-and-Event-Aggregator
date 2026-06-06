<!--LogOut.php-->
<!--Leave the page-->
<?php
	session_start();
	session_unset();
	header("location: StudentLogin.php");
?>