<?php return array(
    'root' => array(
        'name' => 'bizexaminer/learndash-extension',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'wordpress-plugin',
        'install_path' => __DIR__ . '/../../',
        'aliases' => array(),
        'dev' => false,
    ),
    'versions' => array(
        'bizexaminer/learndash-extension' => array(
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'wordpress-plugin',
            'install_path' => __DIR__ . '/../../',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'league/container' => array(
            'pretty_version' => '4.2.0',
            'version' => '4.2.0.0',
            'reference' => '375d13cb828649599ef5d48a339c4af7a26cd0ab',
            'type' => 'library',
            'install_path' => __DIR__ . '/../league/container',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'orno/di' => array(
            'dev_requirement' => false,
            'replaced' => array(
                0 => '~2.0',
            ),
        ),
        'psr/container' => array(
            'pretty_version' => '2.0.2',
            'version' => '2.0.2.0',
            'reference' => 'c71ecc56dfe541dbd90c5360474fbc405f8d5963',
            'type' => 'library',
            'install_path' => __DIR__ . '/../psr/container',
            'aliases' => array(),
            'dev_requirement' => false,
        ),
        'psr/container-implementation' => array(
            'dev_requirement' => false,
            'provided' => array(
                0 => '^1.0',
            ),
        ),
    ),
);
