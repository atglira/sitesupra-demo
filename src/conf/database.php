<?php

use Doctrine\ORM\EntityManager,
    Doctrine\ORM\Configuration,
	Supra\Database\Doctrine;

$config = new Configuration;

// Doctrine cache (array cache for development)
$cache = new \Doctrine\Common\Cache\ArrayCache();
$config->setMetadataCacheImpl($cache);
$config->setQueryCacheImpl($cache);

// Metadata driver
$entityPaths = array(
	SUPRA_LIBRARY_PATH . 'Supra/Controller/Pages/Entity/'
);
$driverImpl = $config->newDefaultAnnotationDriver($entityPaths);
//$driverImpl = new \Doctrine\ORM\Mapping\Driver\YamlDriver(SUPRA_LIBRARY_PATH . 'Supra/yaml/');
$config->setMetadataDriverImpl($driverImpl);

// Proxy configuration
$config->setProxyDir(SUPRA_LIBRARY_PATH . 'Supra/Proxy');
$config->setProxyNamespace('Supra\\Proxy');

// SQL logger
$sqlLogger = new \Supra\Log\Logger\Sql();
$config->setSQLLogger($sqlLogger);

$connectionOptions = array(
	'driver' => 'pdo_mysql',
	'user' => 'root',
	'password' => '1qaz',
	'dbname' => 'supra7'
);

$em = EntityManager::create($connectionOptions, $config);

Doctrine::getInstance()->setDefaultEntityManager($em);