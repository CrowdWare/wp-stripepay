<?php return array(
    'root' => array(
        'name' => 'crowdware/wp-stripepay',
        'pretty_version' => 'dev-main',
        'version' => 'dev-main',
        'reference' => '6644c4b02ac84f7f70f9d3b74a6829d6bc6e9a22',
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => true,
    ),
    'versions' => array(
        'crowdware/wp-stripepay' => array(
            'pretty_version' => 'dev-main',
            'version' => 'dev-main',
            'reference' => '6644c4b02ac84f7f70f9d3b74a6829d6bc6e9a22',
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'stripe/stripe-php' => array(
            'pretty_version' => 'v10.21.0',
            'version' => '10.21.0.0',
            'reference' => 'b4ab319731958077227fad1874a3671458c5d593',
            'type' => 'library',
            'install_path' => __DIR__ . '/../stripe/stripe-php',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
    ),
);
