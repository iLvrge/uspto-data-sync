<?php
echo exec("whoami");
chdir('/mnt/volume_sfo2_12/');
exec('sudo wget https://bulkdata.uspto.gov/data/patent/maintenancefee/MaintFeeEvents.zip',$output, $return);

print_r($output);
print_r($return);

exec('sudo unzip MaintFeeEvents.zip');

?>