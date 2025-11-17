<?php
echo exec("whoami");
chdir('/var/www/html/betapp');
exec('sudo screen -d -m npm start', $output, $return);
print_r($output);
print_r($return);
?>
