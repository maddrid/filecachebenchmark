<?php

error_reporting(E_ALL | E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'debug.log');

function p_print_r($data) {
    echo '<pre>';
    print_r($data);
    echo'</pre>';
}


require_once __DIR__ . '/Bench.php';

require __DIR__ . '/vendor/autoload.php';

$max = 500;
$string = 'Ipsum sollicitudin Ut laborum aptent mollit quam fermentum primis et lacinia penatibus Ante laoreet Quisque vulputate officia. Fames. Ridiculus nam sollicitudin Labore orci eiusmod dolor Mus non libero auctor etiam imperdiet. Malesuada reprehenderit ';
Bench::start('fastcache');

$cacheDir = __DIR__ . '/test/fastcache';
use Phpfastcache\CacheManager;
use Phpfastcache\Config\ConfigurationOption;

// Setup File Path on your config files
// Please note that as of the V6.1 the "path" config 
// can also be used for Unix sockets (Redis, Memcache, etc)
CacheManager::setDefaultConfig(new ConfigurationOption([
    'path' => $cacheDir, 
]));

// In your class, function, you can call the Cache
$InstanceCache = CacheManager::getInstance('files');

for ($i = 0; $i < $max; $i++) {
    $page = 'plugins_' . $i;

    $CachedString = $InstanceCache->getItem($page);


    if (!$CachedString->isHit()) {
        $CachedString->set($string)->expiresAfter(3600); //in seconds, also accepts Datetime
        $InstanceCache->save($CachedString); // Save the cache item just like you do with doctrine and entities
    }
	else {
		$id =   $CachedString->get();
	}
}

Bench::stop('fastcache',true);
Bench::start('doctrine');

$cacheDir = __DIR__ . '/test/doctrine';
$cache = new Doctrine\Common\Cache\FilesystemCache($cacheDir);

for ($i = 0; $i < $max; $i++) {
    $page = 'plugins_' . $i;
    if (!$cache->contains($page)) {
        $cache->save($page, $string, 3600);
    }
	$id = $cache->fetch($page);
}
unset($cache);
Bench::stop('doctrine',true);




Bench::start('codeigniter');
$cacheDir = __DIR__ . '/test/codeigniter';
if (!is_dir($cacheDir)){
if (!mkdir($cacheDir, 0755, true)) {
    die('Failed to create  folders...');
}
}
$config  = [
			'storePath' => $cacheDir = __DIR__ . '/test/codeigniter',
			'prefix'=> 'item'
			
		];
require __DIR__ . '/codeigniter/FileHandler.php';
$cache = new  CodeIgniter\Cache\Handlers\FileHandler((object)$config);
for ($i = 0; $i < $max; $i++) {
    $page = md5('plugins_' . $i);
$foo =  $cache->get($page);
   if ( ! $foo ){
       // Save into the cache for 5 minutes
          $cache->save($page, $string, 3600);
}else {
	$id = $foo ;
}


}
Bench::stop('codeigniter',true);

Bench::start('symfony');
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
$cacheDir = __DIR__ . '/test/symfony';
$symfonycache = new FilesystemAdapter(

    // a string used as the subdirectory of the root cache directory, where cache
    // items will be stored
    $namespace = '',

    // the default lifetime (in seconds) for cache items that do not define their
    // own lifetime, with a value 0 causing items to be stored indefinitely (i.e.
    // until the files are deleted)
    $defaultLifetime = 0,

    // the main cache directory (the application needs read-write permissions on it)
    // if none is specified, a directory is created inside the system temporary directory
    $directory = $cacheDir
);
for ($i = 0; $i < $max; $i++) {
    $page = 'plugins_' . $i;

    $SymfonyString = $symfonycache->getItem($page);


    if (!$SymfonyString->isHit()) {
        $SymfonyString->set($string); 
        $symfonycache->save($SymfonyString); 
    }
	else {
		$id =   $SymfonyString->get();
	}
	unset ($SymfonyString);
}

Bench::stop('symfony',true);

Bench::start('stash');
$cacheDir = __DIR__ . '/test/stash';

// Create Driver with default options
$driver = new Stash\Driver\FileSystem(array ('dirSplit'=>2,'path' => $cacheDir));

// Inject the driver into a new Pool object.
$pool = new Stash\Pool($driver);
 
for ($i = 0; $i < $max; $i++) {
    $page = 'plugins_' . $i;

    $item = $pool->getItem($page);


    if (!$item->isHit()) {
        $item->set($string); 
        $pool->save($item); 
    }
	else {
		$id =   $item->get();
	}
	
}
Bench::stop('stash',true);
p_print_r(Bench::getTimers());



?>
