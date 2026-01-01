<?php

echo exec("whoami");
chdir('/mnt/volume_sfo2_12/patent/DOWNLOAD');
//exec('sudo wget https://bulkdata.uspto.gov/data/patent/grant/redbook/2023/I20230502.tar',$output, $return);

//print_r($output);
//print_r($return);

exec('sudo find -iname \*.tar -exec tar -xvf {} \;',$output, $return);

print_r($output);
print_r($return);
exec('find . -name "*.ZIP" -exec unzip -o {} \;',$output, $return);

print_r($output);
print_r($return);
exec('find . -name "*.XML" -exec mv -t /mnt/volume_sfo2_12/patent/XML2/ {} +',$output, $return);

/* print_r($output);
print_r($return);
exec('find . -name "*.TIF" -exec mv -t /mnt/volume_sfo2_12/patent/IMAGES/ {} +',$output, $return);

print_r($output);
print_r($return);
chdir('/mnt/volume_sfo2_12/patent/IMAGES/');

exec('find . -name "*.TIF" | while read f; do echo "Converting ${f}"; tiff2png ${f}; done',$output, $return);
print_r($output);
print_r($return); */

chdir('/var/www/html/script');
exec('(./node_modules/.bin/env-cmd node patent_xml_file_read.js & ./node_modules/.bin/env-cmd node grant_read_lawyer_from_xml.js & ./node_modules/.bin/env-cmd node grant_read_applicant_assignee_from_xml.js & ./node_modules/.bin/env-cmd node grant_extension_xml.js) & wait',$output, $return);
print_r($output);
print_r($return);

chdir('/mnt/volume_sfo2_12/patent/XML2/');
exec('find . -name "*.XML" -exec mv -t /mnt/volume_sfo2_12/patent/XML/ {} +',$output, $return);

print_r($output);
print_r($return);

/* chdir('/mnt/volume_sfo2_12/patent/IMAGES/');

exec('find . -name "*.TIF" | while read f; do echo "Converting ${f}"; tiff2png ${f}; done');

exec('find . -name "*.png" -exec mv -t /mnt/volume_sfo2_12/patent/PNG/ {} +',$output, $return);

print_r($output);
print_r($return);


exec('export AWS_ACCESS_KEY_ID=AKIAYD2CUN6OLDBPT4SY; export AWS_SECRET_ACCESS_KEY=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; export AWS_DEFAULT_REGION=us-west-1; aws s3 cp /mnt/volume_sfo2_12/patent/PNG s3://static.patentrack.com/figures/ --recursive  --acl public-read-write --include "*.png"',$output, $return);



print_r($output);
print_r($return);

chdir('/mnt/volume_sfo2_12/patent/');

exec('rm -R DOWNLOAD');
exec('rm -R IMAGES');
exec('rm -R PNG');
exec('mkdir DOWNLOAD');
exec('mkdir IMAGES');
exec('mkdir PNG'); */

chdir('/mnt/volume_sfo2_12/patent/');
exec('sudo find DOWNLOAD -mindepth 1 -delete');

//exec('php -f /var/www/html/trash/insert_unique_applicant_temp.php')

?>