<?php
/*
 *  @author Martin Vseticka <vseticka.martin@gmail.com>
 *  
 *  GET parameter "force", force=1 if you don't want to use cache    
 */

require dirname(__FILE__) . "/AhoCorasick.php";
require dirname(__FILE__) . "/TreeNodes.php";

$filePath = dirname(__FILE__) . '/serializedData.dat';
$memoryWhole = memory_get_usage();
$inputText = "Hey one! How are you?";

function getKeywords() {
    return array("one", "two", "three", "four");
}

function memUsage($startMemory, $caption = "") {
    $bytes = memory_get_usage() - $startMemory;
    $kBytes = $bytes / 1024;
    echo "<b>$caption</b> {$kBytes}kB<br />";
}

function saveToCache($tree, $filePath) {
    $fh = fopen($filePath, 'w') or die("can't open file");
    //fwrite($fh, json_encode($tree));
    fwrite($fh, serialize($tree));
    fclose($fh);
    echo 'cache size: ' . (filesize($filePath) / 1024) . " kB<br />";
}

?>

<html>
<head>
    <meta http-equiv="content-type" content="text/html; charset=utf-8" />
</head>
<body>
  <?php
  
  if (!file_exists($filePath) || (isset($_GET['force']))) {
      $ac = new AhoCorasick();
      $ac->setCombineResults(false);
      memUsage($memoryWhole, "Memory (AC instantiated):");      
      $keywords = getKeywords();
      memUsage($memoryWhole, "Memory (keywords loaded):");
      $tree = $ac->buildTree($keywords);
      memUsage($memoryWhole, "Memory (tree built):");
      unset($keywords);
      memUsage($memoryWhole, "Memory (keywords unset):");
      saveToCache($ac, $filePath);
      memUsage($memoryWhole, "Memory (result cached):");                                    
  } else {
      $ac = unserialize(file_get_contents($filePath));
  }

  $res = $ac->FindAll($inputText);
  memUsage($memoryWhole, "Memory (after find all):");
  memUsage($memoryWhole, "Memory whole:");
  unset($ac);
  
  echo "<b>Results: </b><pre>";var_dump($res);echo "</pre>";
  
?>
</body>
</html>