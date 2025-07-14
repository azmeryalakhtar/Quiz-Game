<?php
$password = '@Azmeryal@123#'; // Replace with your desired password
$hash = password_hash($password, PASSWORD_DEFAULT);
echo "Generated hash: " . $hash;
?>