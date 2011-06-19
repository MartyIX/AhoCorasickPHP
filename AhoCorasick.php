<?php

/**
 * Aho-Corasick algorithm implementation 
 * @author Martin Vseticka <vseticka.martin@gmail.com>
 * @note The code is based on the code http://www.codeproject.com/KB/recipes/ahocorasick.aspx by Tomas Petricek 
 */
class AhoCorasick {

// Searches passed text and returns all occurrences of any keyword
// Returns array containing positions of found keywords

    private $_root = null;
    /** @var TreeNodes */
    private $treeNodes;
    /** @var PhpQuickProfiler */
    private $profiler;
    private $combineResults = true;
    private $debug = 0;

    public function __construct() {
        $this->treeNodes = new TreeNodes();
    }

    private function memUsage($startMemory, $caption = "") {
        if ($this->debug === 1) {
            $bytes = memory_get_usage() - $startMemory;
            $kBytes = $bytes / 1024;
            echo "<b>$caption</b> {$kBytes}kB<br />";
        }
    }    

    private function memVariableUsage($variable, $caption = "") {
        if ($this->debug === 1) {
            $bytes = strlen(serialize($variable));
            $kBytes = $bytes / 1024;
            echo "<b>$caption</b> {$kBytes}kB<br />";
        }
    }    
    
    private function debug($message)
    {
        if ($this->debug === 1) {
            echo $messsage . "<br />";
        }
    }
    
    public function dump()
    {        
        var_dump("nodes", $this->treeNodes->nodes);
        var_dump("chars", $this->treeNodes->chars);
        var_dump("results", $this->treeNodes->results);        
    }

    public function setCombineResults($cr) {
        $this->combineResults = $cr;
    }

    public function buildTree($keywords, $multibyte = 1) {
        // Build keyword tree and transition function        
        $memoryStart = memory_get_usage();
        $tn = $this->treeNodes;
        $rootChar = ' ';
        $this->_root = $tn->addNode(null, $rootChar);        
        
        foreach ($keywords as $k) {
            // add pattern to tree
            $nd = $this->_root;
            
            $p = (is_object($k) || is_array($k)) ? $k['keyword'] : $k;
            
            $chars = ($multibyte === 0) ? str_split($p) : preg_split('//u', $p, -1, PREG_SPLIT_NO_EMPTY);
            $lastChar = $rootChar;                        

            foreach ($chars as $c) {
                if ($tn->containsTransition($nd, $c) === false) {
                    $ndNew = $tn->addNode($nd, $c);
                    $tn->addTransition($nd, $c, $ndNew);
                } else {
                    $ndNew = $tn->getTransition($nd, $c, 1);
                }
                $nd = $ndNew;
                $lastChar = $c;
            }
            if ($this->combineResults) {                
                $tn->addResult($nd, $k); // we expects the words from input to be distinct
            } else {
                //unset($k['keyword']);
                $tn->setResult($nd, $k);
            }            
            $this->memUsage($memoryStart, "Memory (keyword: {$p}):");
        }

        $this->memUsage($memoryStart, "Memory (first loop):");
        
        // Find failure functions
        $nodes = array();
        // level 1 nodes - fail to root node
        foreach ($tn->getTransitions($this->_root) as $nd) {
            $tn->setFailure($nd, $this->_root);
            array_merge($nodes, $tn->getTransitions($nd));
        }

        // other nodes - using BFS
        while (count($nodes)) {
            $newNodes = array();
            foreach ($nodes as $nd) {
                $r = $tn->getFailure($tn->getParentId($nd));
                $c = $tn->getChar($nd);

                while ($r != null && !$tn->containsTransition($r, $c)) {
                    $r = $tn->getFailure($r);
                }

                if ($r == null)
                    $tn->setFailure($nd, $this->_root);
                else {
                    $tn->setFailure($nd, $tn->getTransition($r, $c));
                    if ($this->combineResults) {
                        $tn->addAppendResults($nd, $tn->getResults($tn->getFailure($nd)));
                    }
                }

                // add child nodes to BFS list 
                foreach ($tn->getTransitions($nd) as $child) {
                    $newNodes[] = $child;
                }
            }
            $nodes = $newNodes;
        }
        unset($tn->nodesTransitionsVals);
        $tn->setFailure($this->_root, $this->_root);
        
        return $tn;
    }

    public function FindAll($text, $encoding = "UTF8") {
        $ret = array(); // List containing results
        $tn = $this->treeNodes;
        $ptr = $this->_root;          // Current node (state)
        $index = 0;                   // Index in text
        
        $len = mb_strlen($text, $encoding);
        while ($index < $len) {
            // Find next state (if no transition exists, fail function is used)
            // walks through tree until transition is found or root is reached

            $trans = null;
            while ($trans == null) {

                $char = mb_substr($text, $index, 1, $encoding);
                $trans = $tn->getTransition($ptr, $char);

                if ($ptr == $this->_root) {
                    break;
                } elseif ($trans == null) {
                    $ptr = $tn->getFailure($ptr);
                }
            }

            if ($trans != null) {
                $ptr = $trans;
            }

            if ($this->debug === 1) {
                echo "move:" . $ptr . "[" . $tn->getChar($ptr) . "]; ";
            }

            // Add results from node to output array and move to next character
            if ($this->combineResults) {
                foreach ($tn->getResults($ptr) as $found) {
                    $ret[] = $this->addResult($found, $index, $encoding);
                }
            } else {
                $found = $tn->getResult($ptr);
                if ($found != null) {
                    $ret[] = $this->addResult($found, $index, $encoding);
                }
            }

            $index++;
        }
        return $ret;
    }
    
    private function addResult($found, $pos, $encoding = '')
    {
        if (is_array($found) || is_object($found)) {
            $word = $found['keyword'];
            return array($pos - mb_strlen($word, $encoding) + 1, $found);
        } else {
            $word = $found;
            return array($pos - mb_strlen($word, $encoding) + 1, $found);
        }        
    }

}

