<?php
if (PHP_SAPI !== "cli") {
    header('HTTP/1.1 403 Forbidden');
    exit('error: 403 Access Denied');
}

$mTime     = explode(' ', microtime());
$startTime = $mTime[1] + $mTime[0];

define('DBHost', '127.0.0.1');
define('DBPort', 3306);
define('DBName', 'test');
define('DBUser', 'root');
define('DBPassword', '');
require( __DIR__ . "/src/PDO.class.php");
$DB = new Db(DBName, DBUser, DBPassword, DBHost, DBPort);

$DB->query("DROP TABLE IF EXISTS `fruit`;");

$DB->query("CREATE TABLE IF NOT EXISTS `fruit` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(32) NOT NULL,
  `color` varchar(32) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;");

$AffectedRows = $DB->query("INSERT INTO `fruit` (`id`, `name`, `color`) VALUES
(1, 'apple', 'red'),
(2, 'banana', 'yellow'),
(3, 'watermelon', 'green'),
(4, 'pear', 'yellow'),
(5, 'strawberry', 'red');
");

var_dump($AffectedRows);

var_export($DB->query("SELECT * FROM fruit WHERE name=:name and color=:color", ['name'=>'apple','color'=>'red']));

var_export($DB->query("SELECT * FROM fruit WHERE name IN (?)", ['apple','banana']));

var_export($DB->column("SELECT color FROM fruit WHERE name IN (?)", ['apple','banana','watermelon']));

var_export($DB->row("SELECT * FROM fruit WHERE name=? and color=?", ['apple','red']));

echo $DB->single("SELECT color FROM fruit WHERE name=? ", ['watermelon']);

$it = $DB->iterator("SELECT * FROM fruit limit 0, 1000000;");
$colorCountMap = [
    'red' => 0,
    'yellow' => 0,
    'green' => 0
];
foreach($it as $key => $value) {
    // sendDataToElasticSearch($key, $value);
    var_export($key);
    var_export($value);
    $colorCountMap[$value['color']]++;
}
var_export($colorCountMap);

// Delete
$DB->query("DELETE FROM fruit WHERE id = :id", ["id"=>"1"]);
$DB->query("DELETE FROM fruit WHERE id = ?", ["1"]); // Update
$DB->query("UPDATE fruit SET color = :color WHERE name = :name", ["name"=>"strawberry","color"=>"yellow"]);
$DB->query("UPDATE fruit SET color = ? WHERE name = ?",["yellow","strawberry"]);
// Insert
$DB->query("INSERT INTO fruit(id,name,color) VALUES(?,?,?)", [null,"mango","yellow"]);//Parameters must be ordered
$DB->query("INSERT INTO fruit(id,name,color) VALUES(:id,:name,:color)", ["color"=>"yellow","name"=>"mango","id"=>null]);//Parameters order free

echo $DB->querycount;

$mTime     = explode(' ', microtime());
echo '<br>'.(number_format(($mTime[1] + $mTime[0] - $startTime), 6)*1000).'ms';
echo '<br>'.(memory_get_usage(false)/1024).'KiB';