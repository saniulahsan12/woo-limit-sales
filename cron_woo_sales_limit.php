<?php
include 'wp-load.php';
if( (int)date('d') == 1 ):
    update_option('start_date', date("Y-m-d"));
    update_option('end_date', date("Y-m-t", strtotime(get_option('start_date'))));
else:
    echo date("Y-m-d");
endif;
