<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2547d122428b38a762b61eaa8c1232e0
{
    public static $prefixLengthsPsr4 = array (
        'S' => 
        array (
            'Stripe\\' => 7,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Stripe\\' => 
        array (
            0 => __DIR__ . '/..' . '/stripe/stripe-php/lib',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit2547d122428b38a762b61eaa8c1232e0::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2547d122428b38a762b61eaa8c1232e0::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit2547d122428b38a762b61eaa8c1232e0::$classMap;

        }, null, ClassLoader::class);
    }
}
