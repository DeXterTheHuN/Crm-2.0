<?php
$password = 'admin123';
$hash = password_hash($password, PASSWORD_DEFAULT);

echo "Új jelszó hash:  
";
echo "<strong>$hash</strong>  
  
";
echo "Másold ki ezt a hash-t, és frissítsd az adatbázisban!";
?>
