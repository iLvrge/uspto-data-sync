<?php
echo exec("whoami");
chdir('/root/terminal_ac');
exec('sudo php -f execute_admin.php',$output, $return);
print_r($output);
print_r($return);
?>