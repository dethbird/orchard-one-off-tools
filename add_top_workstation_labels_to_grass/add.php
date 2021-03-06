<?php   
// Get all the .csv exports for 5000 per page from Google Analytics https://goo.gl/J4XFGX
require 'vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

//load the feature file
$yml = Yaml::parse(file_get_contents('vendor/orchard/features/configs/features.yml'));
$orig = $yml['grass_analytics']['enabled']['users'];
echo "current label count: " . count($orig) . "\n";

// location of .csv files in the next args exported from Google Analytics for top analytics pageview labels
$i = 2;
$spreadsheet_files = array();
while(isset($argv[$i])) {
    $spreadsheet_files[] = $argv[$i];
    $i++;
}

echo "adding next top " . $argv[1] . " label(s) from:\n" . implode(",\n", $spreadsheet_files) ."\n";


// extract top labels
$top_labels = array();

foreach ($spreadsheet_files as $file) {
  $top_labels_spreadsheet = file_get_contents($argv[2]);
  $top_labels_spreadsheet = explode("\n", $top_labels_spreadsheet);

  foreach ($top_labels_spreadsheet as $line) {
     if( stripos($line, "/analytics/overview")===0 ) {
        $line = explode(",", $line);
        $top_labels[] = $line[1];
     }
  } 

}

$top_labels = array_unique($top_labels);

echo "unique labels found from .csv: " . count($top_labels) . "\n";

// add only if they don't already exist in features.yml
$count = 0;
$next = array();
foreach ($top_labels as $l) {
   if(!in_array($l, $orig)) {
      $count++;
      $next[] = $l;
      if($count==250) {
         break;
      }
   }
}

echo "labels plucked from .csv: " . count($next) . "\n";

$new = array_merge($orig,$next);

// var_dump(count($new));

echo "new count: " . count($new) . "\n";

// database config
$config = Yaml::parse(file_get_contents('config.yml'));
$link = mysql_connect($config['database']['host'], $config['database']['user'], $config['database']['password']);
mysql_select_db($config['database']['db_name'], $link);

// query for all labels
$query = "SELECT vendor_id, IF(name,name,company) as name, priority, overall_priority, label_identifier from vendor where vendor_id IN (" . implode(",", $new) . ") ORDER BY name ASC";

echo "\n" . $query . "\n";
$res = mysql_query($query);

$output = '';
$outputTabbed = '';
$outputTabbed .= "vendor_id\tname\tpriority\toverall_priority\tlabel_identifier\n";

while ($row = mysql_fetch_object($res)) {

   // var_dump($row);
    
    $output .= "# ".$row->name." | priority: ". $row->priority ."\n";
    $output .= "    - ".$row->vendor_id."\n";
    $outputTabbed .= $row->vendor_id . "\t" . $row->name. "\t" . $row->priority. "\t" . $row->overall_priority. "\t" . $row->label_identifier . "\n";
    // echo $row->name."\n";
}

// echo $output;
file_put_contents("grass.feature.labels.yml", $output);
file_put_contents("grass.feature.labels.tab", $outputTabbed);

// get a handful to test with that don't have grass
$query = "SELECT vendor_id, IF(name,name,company) as name, priority, overall_priority, label_identifier FROM vendor WHERE vendor_id NOT IN (" . implode(",", $new) . ") AND priority < 3 ORDER BY RAND() LIMIT 250";
$res = mysql_query($query);
$outputTabbed = '';
$outputTabbed .= "vendor_id\tname\tpriority\toverall_priority\tlabel_identifier\n";

echo "found " . mysql_num_rows($res) . " non grass labels with priority < 3\n";

while ($row = mysql_fetch_object($res)) {

   // var_dump($row);
    
    $output .= "# ".$row->name." | priority: ". $row->priority ."\n";
    $output .= "    - ".$row->vendor_id."\n";
    $outputTabbed .= $row->vendor_id . "\t" . $row->name. "\t" . $row->priority. "\t" . $row->overall_priority. "\t" . $row->label_identifier . "\n";
    // echo $row->name."\n";
}

file_put_contents("grass.feature.labels.off.tab", $outputTabbed);




