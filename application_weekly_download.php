<?php

echo exec("whoami");
chdir('/mnt/volume_sfo2_12/applications/DOWNLOAD');
//exec('sudo wget https://bulkdata.uspto.gov/data/applications/grant/redbook/2023/I20230502.tar',$output, $return);

//print_r($output);
//print_r($return);

exec('sudo find -iname \*.tar -exec tar -xvf {} \;',$output, $return);

print_r($output);
print_r($return);
exec('find . -name "*.ZIP" -exec unzip -o {} \;',$output, $return);

print_r($output);
print_r($return);
exec('find . -name "*.XML" -exec mv -t /mnt/volume_sfo2_12/applications/XML2/ {} +',$output, $return);

print_r($output);
print_r($return);
exec('find . -name "*.TIF" -exec mv -t /mnt/volume_sfo2_12/applications/IMAGES/ {} +',$output, $return);

print_r($output);
print_r($return);
chdir('/mnt/volume_sfo2_12/applications/IMAGES/');

/* exec('find . -name "*.TIF" | while read f; do echo "Converting ${f}"; tiff2png ${f}; done',$output, $return);
exec('find . -name "*.png" -exec mv -t /mnt/volume_sfo2_12/applications/PNG/ {} +',$output, $return);
print_r($output);
print_r($return); */

/* chdir('/var/www/html/script');

exec(
  '/home/uzi/.nvm/versions/node/v22.17.0/bin/node '
  . '/var/www/html/script/node_modules/env-cmd/bin/env-cmd.js '
  . '-f /var/www/html/script/.env '
  . '/var/www/html/script/application_read_applicant_assignee_from_xml.js '
  . '2>&1',
  $output,
  $return
);
print_r($output);
print_r($return);
exec(
  '/home/uzi/.nvm/versions/node/v22.17.0/bin/node '
  . '/var/www/html/script/node_modules/env-cmd/bin/env-cmd.js '
  . '-f /var/www/html/script/.env '
  . '/var/www/html/script/read_inventor_from_xml.js '
  . '2>&1',
  $output,
  $return
);
print_r($output);
print_r($return); */


/* chdir('/mnt/volume_sfo2_12/applications/XML2/');
exec('find . -name "*.XML" -exec mv -t /mnt/volume_sfo2_12/applications/XML/ {} +',$output, $return);

print_r($output);
print_r($return); */


//exec('export AWS_ACCESS_KEY_ID=AKIAYD2CUN6OLDBPT4SY; export AWS_SECRET_ACCESS_KEY=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; export AWS_DEFAULT_REGION=us-west-1; aws s3 cp /mnt/volume_sfo2_12/applications/PNG s3://static.patentrack.com/figures/ --recursive  --acl public-read-write --include "*.png"',$output, $return);

/* chdir('/mnt/volume_sfo2_12/applications/PNG/');
exec('find . -name "*.png" -exec mv -t /mnt/data/s3/ {} +',$output, $return);


print_r($output);
print_r($return); */

chdir('/mnt/volume_sfo2_12/applications/');

exec('sudo find DOWNLOAD -mindepth 1 -delete');
/* exec('find IMAGES -mindepth 1 -delete');
exec('find PNG -mindepth 1 -delete'); */
/* exec('rm -R DOWNLOAD');
exec('rm -R IMAGES');
exec('rm -R PNG');
exec('mkdir DOWNLOAD');
exec('mkdir IMAGES');
exec('mkdir PNG'); */

//exec('php -f /var/www/html/trash/insert_unique_applicant_temp.php')

?>