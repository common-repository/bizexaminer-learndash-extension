<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitb9259b92c1ceb8ec02950cbc224f3fe5
{
    public static $prefixLengthsPsr4 = array (
        'B' => 
        array (
            'BizExaminer\\LearnDashExtension\\Tests\\' => 37,
            'BizExaminer\\LearnDashExtension\\' => 31,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'BizExaminer\\LearnDashExtension\\Tests\\' => 
        array (
            0 => __DIR__ . '/../..' . '/tests',
        ),
        'BizExaminer\\LearnDashExtension\\' => 
        array (
            0 => __DIR__ . '/../..' . '/lib',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitb9259b92c1ceb8ec02950cbc224f3fe5::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitb9259b92c1ceb8ec02950cbc224f3fe5::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInitb9259b92c1ceb8ec02950cbc224f3fe5::$classMap;

        }, null, ClassLoader::class);
    }
}